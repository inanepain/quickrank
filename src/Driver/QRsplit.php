<?php

/**
 * Inane: QuickRank
 *
 * QR Code Generator for PHP.
 *
 * $Id$
 * $Date$
 *
 * PHP version 8.4
 *
 * @author Philip Michael Raab <philip@cathedral.co.za>
 * @package inanepain\quickrank
 * @category quickrank
 *
 * @license UNLICENSE
 * @license https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types = 1);

namespace Inane\QR\Driver;

use Inane\Stdlib\Exception\Exception;

/**
 * Splits input string into optimal QR segments for encoding.
 *
 * Scans the input to decide which mode (numeric, alphanumeric, 8-bit, Kanji)
 * to use for each run and appends corresponding entries to the QRinput.
 */
class QRsplit {
    public string $dataStr = '';
    public QRinput $input;
    public int $modeHint;

    public function __construct(string $dataStr, QRinput $input, int $modeHint) {
        $this->dataStr = $dataStr;
        $this->input = $input;
        $this->modeHint = $modeHint;
    }

    public static function isDigitat(string $str, int $pos): bool {
        if ($pos >= strlen($str)) return false;

        return ((ord($str[$pos]) >= ord('0')) && (ord($str[$pos]) <= ord('9')));
    }

    public static function isAlnumat(string $str, int $pos): bool {
        if ($pos >= strlen($str)) return false;

        return (QRinput::lookAnTable(ord($str[$pos])) >= 0);
    }

    public function identifyMode(int $pos): int {
        if ($pos >= strlen($this->dataStr)) return qrstr::MODE_NUL;

        $c = $this->dataStr[$pos];

        if (self::isDigitat($this->dataStr, $pos)) {
            return qrstr::MODE_NUM;
        } elseif (self::isAlnumat($this->dataStr, $pos)) {
            return qrstr::MODE_AN;
        } elseif ($this->modeHint == qrstr::MODE_KANJI) {
            if ($pos + 1 < strlen($this->dataStr)) {
                $d = $this->dataStr[$pos + 1];
                $word = (ord($c) << 8) | ord($d);
                if (($word >= 0x8140 && $word <= 0x9ffc) || ($word >= 0xe040 && $word <= 0xebbf)) {
                    return qrstr::MODE_KANJI;
                }
            }
        }

        return qrstr::MODE_8;
    }

    public function eatNum(): int {
        $ln = QRspec::lengthIndicator(qrstr::MODE_NUM, $this->input->getVersion());

        $p = 0;
        while (self::isDigitat($this->dataStr, $p)) {
            $p++;
        }

        $run = $p;
        $mode = $this->identifyMode($p);

        if ($mode === qrstr::MODE_8) {
            $dif = QRinput::estimateBitsModeNum($run) + 4 + $ln + QRinput::estimateBitsMode8(1)         // + 4 + l8
                - QRinput::estimateBitsMode8($run + 1); // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }
        if ($mode === qrstr::MODE_AN) {
            $dif = QRinput::estimateBitsModeNum($run) + 4 + $ln + QRinput::estimateBitsModeAn(1)        // + 4 + la
                - QRinput::estimateBitsModeAn($run + 1); // - 4 - la
            if ($dif > 0) {
                return $this->eatAn();
            }
        }

        $ret = $this->input->append(qrstr::MODE_NUM, $run, str_split($this->dataStr));
        if ($ret < 0) return -1;

        return $run;
    }

    public function eatAn(): int {
        $la = QRspec::lengthIndicator(qrstr::MODE_AN, $this->input->getVersion());
        $ln = QRspec::lengthIndicator(qrstr::MODE_NUM, $this->input->getVersion());

        $p = 0;

        while (self::isAlnumat($this->dataStr, $p)) {
            if (self::isDigitat($this->dataStr, $p)) {
                $q = $p;
                while (self::isDigitat($this->dataStr, $q)) {
                    $q++;
                }

                $dif = QRinput::estimateBitsModeAn($p) // + 4 + la
                    + QRinput::estimateBitsModeNum($q - $p) + 4 + $ln - QRinput::estimateBitsModeAn($q); // - 4 - la

                if ($dif < 0) {
                    break;
                }

	            $p = $q;
            } else {
                $p++;
            }
        }

        $run = $p;

        if (!self::isAlnumat($this->dataStr, $p)) {
            $dif = QRinput::estimateBitsModeAn($run) + 4 + $la + QRinput::estimateBitsMode8(1) // + 4 + l8
                - QRinput::estimateBitsMode8($run + 1); // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }

        $ret = $this->input->append(qrstr::MODE_AN, $run, str_split($this->dataStr));
        if ($ret < 0) return -1;

        return $run;
    }

    public function eatKanji(): int {
        $p = 0;

        while ($this->identifyMode($p) === qrstr::MODE_KANJI) {
            $p += 2;
        }

        $ret = $this->input->append(qrstr::MODE_KANJI, $p, str_split($this->dataStr));
        if ($ret < 0) return -1;

        return $p;
    }

    public function eat8(): int {
        $la = QRspec::lengthIndicator(qrstr::MODE_AN, $this->input->getVersion());
        $ln = QRspec::lengthIndicator(qrstr::MODE_NUM, $this->input->getVersion());

        $p = 1;
        $dataStrLen = strlen($this->dataStr);

        while ($p < $dataStrLen) {
            $mode = $this->identifyMode($p);
            if ($mode === qrstr::MODE_KANJI) {
                break;
            }
            if ($mode === qrstr::MODE_NUM) {
                $q = $p;
                while (self::isDigitat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = QRinput::estimateBitsMode8($p) // + 4 + l8
                    + QRinput::estimateBitsModeNum($q - $p) + 4 + $ln - QRinput::estimateBitsMode8($q); // - 4 - l8
                if ($dif < 0) {
                    break;
                }

	            $p = $q;
            } elseif ($mode === qrstr::MODE_AN) {
                $q = $p;
                while (self::isAlnumat($this->dataStr, $q)) {
                    $q++;
                }
                $dif = QRinput::estimateBitsMode8($p)  // + 4 + l8
                    + QRinput::estimateBitsModeAn($q - $p) + 4 + $la - QRinput::estimateBitsMode8($q); // - 4 - l8
                if ($dif < 0) {
                    break;
                }

	            $p = $q;
            } else {
                $p++;
            }
        }

        $run = $p;
        $ret = $this->input->append(qrstr::MODE_8, $run, str_split($this->dataStr));

        if ($ret < 0) return -1;

        return $run;
    }

    public function splitString(): int {
        while ($this->dataStr !== '') {
	        $mode = $this->identifyMode(0);

            switch ($mode) {
                case qrstr::MODE_NUM:
                    $length = $this->eatNum();
                    break;
                case qrstr::MODE_AN:
                    $length = $this->eatAn();
                    break;
                case qrstr::MODE_KANJI:
                    if ($this->modeHint === qrstr::MODE_KANJI) $length = $this->eatKanji();
                    else    $length = $this->eat8();
                    break;
                default:
                    $length = $this->eat8();
                    break;
            }

            if ($length === 0) return 0;
            if ($length < 0) return -1;

            $this->dataStr = substr($this->dataStr, $length);
        }

        return 0;
    }

    public function toUpper(): string {
        $stringLen = strlen($this->dataStr);
        $p = 0;

        while ($p < $stringLen) {
            $mode = $this->identifyMode(substr($this->dataStr, $p), $this->modeHint);
            if ($mode === qrstr::MODE_KANJI) {
                $p += 2;
            } else {
                if (ord($this->dataStr[$p]) >= ord('a') && ord($this->dataStr[$p]) <= ord('z')) {
                    $this->dataStr[$p] = chr(ord($this->dataStr[$p]) - 32);
                }
                $p++;
            }
        }

        return $this->dataStr;
    }

    public static function splitStringToQRInput(string $string, QRinput $input, int $modeHint, bool $caseSensitive = true): int {
        if ($string === '\0' || $string === '') {
            throw new Exception('empty string!!!');
        }

        $split = new QRsplit($string, $input, $modeHint);

        if (!$caseSensitive) $split->toUpper();

        return $split->splitString();
    }
}
