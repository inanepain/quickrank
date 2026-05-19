<?php

/**
 * Inane: QuickRank
 *
 * QR Code Generator for PHP.
 *
 * $Id$
 * $Date$
 *
 * PHP version 8.5
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

namespace Inane\QR;

use Inane\QR\Driver\FrameFiller;
use Inane\QR\Driver\QRencode;
use Inane\QR\Driver\QRinput;
use Inane\QR\Driver\QRmask;
use Inane\QR\Driver\QRrawcode;
use Inane\QR\Driver\QRspec;
use Inane\QR\Driver\QRsplit;
use Inane\QR\Driver\qrstr;
use Inane\QR\Driver\QRtools;
use Inane\Stdlib\Exception\Exception;

/**
 * High-level QR code object representing an encoded symbol.
 *
 * This class coordinates the final stages of QR encoding:
 * - Creates the raw interleaved data+ECC stream from a {@see Driver\QRinput}
 *   via {@see Driver\QRrawcode}
 * - Fills the base frame using {@see Driver\FrameFiller} in the QR-specified
 *   zig-zag pattern, including remainder bits
 * - Applies a mask pattern using {@see Driver\QRmask} (either selected or
 *   auto-chosen based on demerit evaluation)
 *
 * The resulting symbol information is exposed via the public properties
 * {@see QRcode::$version}, {@see QRcode::$width}, and {@see QRcode::$data}.
 *
 * Notes
 * - Methods throw {@see Exception} for invalid versions/levels or bad hints.
 * - All mutating operations are internal; callers typically use
 *   {@see Driver\QRencode} as a facade for convenience.
 */
class QRcode {
    /**
     * QR version number (1..40) of the produced symbol.
     */
    public int $version;
    /**
     * Module width/height (in modules) of the produced symbol.
     */
    public int $width;
    /**
     * Masked frame buffer as an array of strings (each char is a byte/module).
     * The least significant bit of each byte encodes the module (0=white,1=black).
     *
     * @var array<int,string>
     */
    public array $data;

    /**
     * Encode the provided input into a masked QR frame using a specific mask.
     *
     * Steps performed:
     * - Validate version and error correction level against spec
     * - Build raw interleaved data/ECC stream ({@see Driver\QRrawcode})
     * - Fill modules into the base frame following the zig-zag pattern
     *   ({@see Driver\FrameFiller}) and append remainder bits
     * - Apply either the given mask or the best mask by evaluation
     *   ({@see Driver\QRmask})
     *
     * @param QRinput $input Prepared input segments and configuration
     * @param int     $mask  Mask number [0..7]; pass a negative value to auto-pick
     *
     * @return QRcode|null Returns $this on success, null on failure
     *
     * @throws Exception When version/level are out of range
     */
    public function encodeMask(QRinput $input, int $mask): ?QRcode {
        if ($input->getVersion() < 0 || $input->getVersion() > QRspec::VERSION_MAX) {
            throw new Exception('wrong version');
        }
        if ($input->getErrorCorrectionLevel() > qrstr::ECLEVEL_H) {
            throw new Exception('wrong level');
        }

        $raw = new QRrawcode($input);

        QRtools::markTime('after_raw');

        $version = $raw->version;
        $width = QRspec::getWidth($version);
        $frame = QRspec::newFrame($version);

        $filler = new FrameFiller($width, $frame);
        if (is_null($filler)) {
            return null;
        }

        // Place interleaved data and ECC codewords bit-by-bit into the frame
        for ($i = 0; $i < $raw->dataLength + $raw->eccLength; $i++) {
            $code = $raw->getCode();
            $bit = 0x80;
            for ($j = 0; $j < 8; $j++) {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }

        QRtools::markTime('after_filler');

        unset($raw);

        // Append required remainder bits for this version
        $j = QRspec::getRemainder($version);
        for ($i = 0; $i < $j; $i++) {
            $addr = $filler->next();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);

        // Apply mask (specific or best available depending on $mask)
        $maskObj = new QRmask();
        if ($mask < 0) {
            if (QRmask::FIND_BEST_MASK) {
                $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
            } else {
                $masked = $maskObj->makeMask($width, $frame, (QRmask::DEFAULT_MASK % 8), $input->getErrorCorrectionLevel());
            }
        } else {
            $masked = $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
        }

	    QRtools::markTime('after_mask');

        $this->version = $version;
        $this->width = $width;
        $this->data = $masked;

        return $this;
    }

    /**
     * Encode the provided input selecting the best mask automatically.
     *
     * @param QRinput $input Prepared input
     *
     * @return QRcode|null Encoded symbol or null on failure
     */
    public function encodeInput(QRinput $input): ?QRcode {
        return $this->encodeMask($input, -1);
    }

    /**
     * Encode a plain string using 8-bit byte mode (MODE_8).
     *
     * @param string $string  Input text (treated as bytes)
     * @param int    $version Target version (0/auto not supported here)
     * @param int    $level   Error correction level (0..3)
     *
     * @return QRcode|null Encoded symbol or null on failure
     *
     * @throws Exception When the string is empty
     */
    public function encodeString8bit(string $string, int $version, int $level): ?QRcode {
        if ($string == null) {
            throw new Exception('empty string!');

            return null;
        }

        $input = new QRinput($version, $level);

	    $ret = $input->append($input, qrstr::MODE_8, strlen($string), str_split($string));
        if ($ret < 0) {
            unset($input);

            return null;
        }

        return $this->encodeInput($input);
    }

    /**
     * Encode a plain string using optimal mode split or Kanji per hint.
     *
     * @param string $string         Input text
     * @param int    $version        Target version (0/auto allowed via QRinput estimation)
     * @param int    $level          Error correction level (0..3)
     * @param int    $hint           Mode hint: {@see Driver\qrstr::MODE_8} or {@see Driver\qrstr::MODE_KANJI}
     * @param bool   $caseSensitive  Whether to treat input case sensitively while splitting
     *
     * @return QRcode|null Encoded symbol or null on failure
     *
     * @throws Exception When an invalid hint is provided
     */
    public function encodeString(string $string, int $version, int $level, int $hint, bool $caseSensitive): ?QRcode {
        if ($hint != qrstr::MODE_8 && $hint !== qrstr::MODE_KANJI) {
            throw new Exception('bad hint');

            return null;
        }

        $input = new QRinput($version, $level);

	    $ret = QRsplit::splitStringToQRInput($string, $input, $hint, $caseSensitive);
        if ($ret < 0) {
            return null;
        }

        return $this->encodeInput($input);
    }

    /**
     * Convenience: encode and render to PNG.
     *
     * @param string           $text         Input text
     * @param bool|string      $outfile      File path or false to output directly
     * @param int              $level        Error correction level (0..3 or qrstr::ECLEVEL_*)
     * @param int              $size         Pixel size per module
     * @param int              $margin       Quiet zone in modules
     * @param bool             $saveAndPrint When true, save and also output
     */
    public static function png(string $text, bool|string $outfile = false, int $level = qrstr::ECLEVEL_L, int $size = 3, int $margin = 4, bool $saveAndPrint = false): void {
        $enc = QRencode::factory($level, $size, $margin);

        $enc->encodePNG($text, $outfile, $saveAndPrint = false);
    }

    /**
     * Convenience: encode and return textual matrix representation.
     * Optionally save to a file.
     *
     * @param string      $text    Input text
     * @param bool|string $outfile File path or false to not write
     * @param int         $level   Error correction level
     * @param int         $size    Pixel size per module (used by png, kept for API symmetry)
     * @param int         $margin  Quiet zone in modules (used by png, kept for API symmetry)
     *
     * @return mixed Array of strings for the binary matrix
     */
    public static function text(string $text, bool|string $outfile = false, int $level = qrstr::ECLEVEL_L, int $size = 3, int $margin = 4): mixed {
	    return QRencode::factory($level, $size, $margin)
		    ->encode($text, $outfile);
    }

    /**
     * Convenience: encode and return raw internal frame structure (no rendering).
     *
     * @param string      $text    Input text
     * @param bool|string $outfile Ignored (kept for BC)
     * @param int         $level   Error correction level
     * @param int         $size    Pixel size per module (kept for API symmetry)
     * @param int         $margin  Quiet zone in modules (kept for API symmetry)
     *
     * @return mixed Internal frame data structure
     */
    public static function raw(string $text, bool|string $outfile = false, int $level = qrstr::ECLEVEL_L, int $size = 3, int $margin = 4): mixed {
	    return QRencode::factory($level, $size, $margin)
		    ->encodeRAW($text, $outfile);
    }
}
