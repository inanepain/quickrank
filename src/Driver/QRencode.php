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

use Inane\QR\QRcode;
use Inane\Stdlib\Exception\Exception;

/**
 * High-level encoder facade.
 *
 * Provides convenience helpers to encode input strings into QR frames
 * and render them as text (binary matrix) or PNG images.
 */
class QRencode {
    /** Treat input case sensitively during mode detection. */
    public bool $caseSensitive = true;
    /** Force 8-bit mode for input. */
    public bool $eightbit = false;
    /** QR version (0 for auto). */
    public int $version = 0;
    /** Module pixel size when rendering. */
    public int $size = 3;
    /** Quiet zone size around the code (in modules). */
    public int $margin = 4;
    /** Structured append (not supported yet). */
    public int $structured = 0; // not supported yet
    /** Error correction level. */
    public int $level = qrstr::ECLEVEL_L;
    /** Input mode hint. */
    public int $hint = qrstr::MODE_8;

    /**
     * Factory for common configuration.
     *
     * @param int|string $level  Error correction level (L/M/Q/H or 0..3)
     * @param int        $size   Pixel size per module
     * @param int        $margin Quiet zone in modules
     */
    public static function factory(int|string $level = qrstr::ECLEVEL_L, int $size = 3, int $margin = 4): QRencode {
        $enc = new QRencode();
        $enc->size = $size;
        $enc->margin = $margin;

        switch ($level . '') {
            case '0':
            case '1':
            case '2':
            case '3':
                $enc->level = $level;
                break;
            case 'l':
            case 'L':
                $enc->level = qrstr::ECLEVEL_L;
                break;
            case 'm':
            case 'M':
                $enc->level = qrstr::ECLEVEL_M;
                break;
            case 'q':
            case 'Q':
                $enc->level = qrstr::ECLEVEL_Q;
                break;
            case 'h':
            case 'H':
                $enc->level = qrstr::ECLEVEL_H;
                break;
        }

        return $enc;
    }

    /**
     * Encode input into raw frame data (no rendering), returning the internal
     * frame array used by lower-level components.
     *
     * @param string           $intext  Input text
     * @param bool|string|null $outfile Ignored (kept for BC)
     *
     * @return mixed Internal frame data
     */
    public function encodeRAW(string $intext, bool|string $outfile = false): mixed {
        $code = new QRcode();

        if ($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->caseSensitive);
        }

        return $code->data;
    }

    /**
     * Encode input into a binary matrix of '0'/'1' strings.
     * Optionally write the textual representation to a file.
     *
     * @return array<int,string> Binary matrix lines
     */
    public function encode(string $intext, bool|string $outfile = false): mixed {
        $code = new QRcode();

        if ($this->eightbit) {
            $code->encodeString8bit($intext, $this->version, $this->level);
        } else {
            $code->encodeString($intext, $this->version, $this->level, $this->hint, $this->caseSensitive);
        }

        QRtools::markTime('after_encode');

        if ($outfile !== false) {
            file_put_contents($outfile, join("\n", QRtools::binarize($code->data)));
        }

		return QRtools::binarize($code->data);
    }

    /**
     * Encode and render to PNG using GD.
     *
     * @param string           $intext       Input text
     * @param bool|string      $outfile      File path or false to output directly
     * @param bool             $saveandprint When true, save to file and print to output
     */
    public function encodePNG(string $intext, bool|string $outfile = false, bool $saveandprint = false): void {
        try {
            ob_start();
            $tab = $this->encode($intext);
            $err = ob_get_clean();

	        if ($err !== '') QRtools::log($outfile, $err);

            $maxSize = (int)(QRimage::PNG_MAXIMUM_SIZE / (count($tab) + 2 * $this->margin));

            QRimage::png($tab, $outfile, min(max(1, $this->size), $maxSize), $this->margin, $saveandprint);
        } catch (Exception $e) {
            QRtools::log($outfile, $e->getMessage());
        }
    }
}
