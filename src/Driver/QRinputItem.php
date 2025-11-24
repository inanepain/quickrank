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
 * Represents a single input segment for QR encoding.
 *
 * Each item holds input data in a specific mode and can build its own
 * bitstream according to the QR specification for that mode.
 */
class QRinputItem {
    /** Input mode (see qrstr::MODE_*). */
    public int $mode;
    /** Number of bytes/chars in $data used for this segment. */
    public int $size;
    /** Raw input data (bytes as characters). @var array<int,string> */
    public array $data;
    /** Encoded bitstream for this segment. */
    public ?QRbitstream $bstream;

    /**
     * @param int                 $mode    Mode constant
     * @param int                 $size    Number of bytes from $data to use
     * @param array<int,string>   $data    Source data (will be sliced/padded)
     * @param null|QRbitstream    $bstream Optional prebuilt bitstream
     *
     * @throws Exception When mode/size/data combination is invalid
     */
    public function __construct(int $mode, int $size, array $data, ?QRbitstream $bstream = null) {
        $setData = array_slice($data, 0, $size);

        if (count($setData) < $size) {
            $setData = array_merge($setData, array_fill(0, $size - count($setData), 0));
        }

        if (!QRinput::check($mode, $size, $setData)) {
            throw new Exception('Error m:' . $mode . ',s:' . $size . ',d:' . join(',', $setData));
        }

        $this->mode = $mode;
        $this->size = $size;
        $this->data = $setData;
        $this->bstream = $bstream;
    }

    /**
     * Encode a numeric segment.
     *
     * @return int 0 on success, -1 on failure
     */
    public function encodeModeNum(int $version): int {
        try {
            $words = (int)($this->size / 3);
            $bs = new QRbitstream();

            $val = 0x1;
            $bs->appendNum(4, $val);
            $bs->appendNum(QRspec::lengthIndicator(qrstr::MODE_NUM, $version), $this->size);

            for ($i = 0; $i < $words; $i++) {
                $val = (ord($this->data[$i * 3]) - ord('0')) * 100;
                $val += (ord($this->data[$i * 3 + 1]) - ord('0')) * 10;
                $val += (ord($this->data[$i * 3 + 2]) - ord('0'));
                $bs->appendNum(10, $val);
            }

            if ($this->size - $words * 3 == 1) {
                $val = ord($this->data[$words * 3]) - ord('0');
                $bs->appendNum(4, $val);
            } elseif ($this->size - $words * 3 == 2) {
                $val = (ord($this->data[$words * 3]) - ord('0')) * 10;
                $val += (ord($this->data[$words * 3 + 1]) - ord('0'));
                $bs->appendNum(7, $val);
            }

            $this->bstream = $bs;

            return 0;
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * Encode an alphanumeric segment.
     *
     * @return int 0 on success, -1 on failure
     */
    public function encodeModeAn(int $version): int {
        try {
            $words = (int)($this->size / 2);
            $bs = new QRbitstream();

            $bs->appendNum(4, 0x02);
            $bs->appendNum(QRspec::lengthIndicator(qrstr::MODE_AN, $version), $this->size);

            for ($i = 0; $i < $words; $i++) {
                $val = (int)QRinput::lookAnTable(ord($this->data[$i * 2])) * 45;
                $val += (int)QRinput::lookAnTable(ord($this->data[$i * 2 + 1]));

                $bs->appendNum(11, $val);
            }

            if ($this->size & 1) {
                $val = QRinput::lookAnTable(ord($this->data[$words * 2]));
                $bs->appendNum(6, $val);
            }

            $this->bstream = $bs;

            return 0;
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * Encode an 8-bit byte segment.
     *
     * @return int 0 on success, -1 on failure
     */
    public function encodeMode8(int $version): int {
        try {
            $bs = new QRbitstream();

            $bs->appendNum(4, 0x4);
            $bs->appendNum(QRspec::lengthIndicator(qrstr::MODE_8, $version), $this->size);

            for ($i = 0; $i < $this->size; $i++) {
                $bs->appendNum(8, ord($this->data[$i]));
            }

            $this->bstream = $bs;

            return 0;
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * Encode a Kanji segment (Shift JIS).
     *
     * @return int 0 on success, -1 on failure
     */
    public function encodeModeKanji(int $version): int {
        try {
            $bs = new QRbitstream();

            $bs->appendNum(4, 0x8);
            $bs->appendNum(QRspec::lengthIndicator(qrstr::MODE_KANJI, $version), (int)($this->size / 2));

            for ($i = 0; $i < $this->size; $i += 2) {
                $val = (ord($this->data[$i]) << 8) | ord($this->data[$i + 1]);
                if ($val <= 0x9ffc) {
                    $val -= 0x8140;
                } else {
                    $val -= 0xc140;
                }

                $h = ($val >> 8) * 0xc0;
                $val = ($val & 0xff) + $h;

                $bs->appendNum(13, $val);
            }

            $this->bstream = $bs;

            return 0;
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * Encode a structured append header segment.
     *
     * @return int 0 on success, -1 on failure
     */
    public function encodeModeStructure(): int {
        try {
            $bs = new QRbitstream();

            $bs->appendNum(4, 0x03);
            $bs->appendNum(4, ord($this->data[1]) - 1);
            $bs->appendNum(4, ord($this->data[0]) - 1);
            $bs->appendNum(8, ord($this->data[2]));

            $this->bstream = $bs;

            return 0;
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * Estimate bit size for this entry at given version.
     *
     * @return int|false Number of bits or false on unsupported mode
     */
    public function estimateBitStreamSizeOfEntry(int $version): int|false {
        $bits = 0;

        if ($version == 0) $version = 1;

        switch ($this->mode) {
            case qrstr::MODE_NUM:
                $bits = QRinput::estimateBitsModeNum($this->size);
                break;
            case qrstr::MODE_AN:
                $bits = QRinput::estimateBitsModeAn($this->size);
                break;
            case qrstr::MODE_8:
                $bits = QRinput::estimateBitsMode8($this->size);
                break;
            case qrstr::MODE_KANJI:
                $bits = QRinput::estimateBitsModeKanji($this->size);
                break;
            case qrstr::MODE_STRUCTURE:
                return QRinput::STRUCTURE_HEADER_BITS;
            default:
                return 0;
        }

        $l = QRspec::lengthIndicator($this->mode, $version);
        $m = 1 << $l;
        $num = (int)(($this->size + $m - 1) / $m);

        $bits += $num * (4 + $l);

        return $bits;
    }

    /**
     * Build the bitstream for this entry (may split into sub-entries).
     *
     * @return int Size in bits, or -1 on failure
     */
    public function encodeBitStream(int $version): int {
        try {
            unset($this->bstream);
            $words = QRspec::maximumWords($this->mode, $version);

            if ($this->size > $words) {
                $st1 = new QRinputItem($this->mode, $words, $this->data);
                $st2 = new QRinputItem($this->mode, $this->size - $words, array_slice($this->data, $words));

                $st1->encodeBitStream($version);
                $st2->encodeBitStream($version);

                $this->bstream = new QRbitstream();
                $this->bstream->append($st1->bstream);
                $this->bstream->append($st2->bstream);

                unset($st1);
                unset($st2);
            } else {
                $ret = 0;

                switch ($this->mode) {
                    case qrstr::MODE_NUM:
                        $ret = $this->encodeModeNum($version);
                        break;
                    case qrstr::MODE_AN:
                        $ret = $this->encodeModeAn($version);
                        break;
                    case qrstr::MODE_8:
                        $ret = $this->encodeMode8($version);
                        break;
                    case qrstr::MODE_KANJI:
                        $ret = $this->encodeModeKanji($version);
                        break;
                    case qrstr::MODE_STRUCTURE:
                        $ret = $this->encodeModeStructure();
                        break;

                    default:
                        break;
                }

                if ($ret < 0) return -1;
            }

            return $this->bstream->size();
        } catch (Exception $e) {
            return -1;
        }
    }
}
