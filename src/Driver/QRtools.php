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

/**
 * Utility functions and simple tooling for the QR encoder.
 *
 * Includes helpers for frame binarization, logging, simple benchmarking,
 * and optional cache pre-building for mask patterns.
 */
class QRtools {
    // Config constants moved from merged_config.php
    public const bool CACHEABLE = false; // use cache - more disk reads but less CPU power
    public const string|false CACHE_DIR = false; // used when CACHEABLE === true (set to a writable dir string)
    public const string|false LOG_DIR = false;   // default error logs dir

    /**
     * Internal cache placeholder (used by clearCache/buildCache helpers)
     */
    private static array $frames = [];

    /**
     * Convert a frame of bytes into a frame of '0'/'1' characters.
     *
     * @param array<int,string> $frame
     *
     * @return array<int,string>
     */
    public static function binarize(array $frame): array {
        $len = count($frame);
        foreach ($frame as &$frameLine) {
            for ($i = 0; $i < $len; $i++) {
                $frameLine[$i] = (ord($frameLine[$i]) & 1) ? '1' : '0';
            }
        }

        return $frame;
    }

    /**
     * Build an array structure compatible with TCPDF's 2D barcode renderer.
     */
    public static function tcpdfBarcodeArray(string $code, string|array $mode = 'QR,L', string $tcPdfVersion = '4.5.037'): array {
        $barcode_array = [];

        if (!is_array($mode)) $mode = explode(',', $mode);

        $eccLevel = 'L';

        if (count($mode) > 1) {
            $eccLevel = $mode[1];
        }

        $qrTab = QRcode::text($code, false, $eccLevel);
        $size = count($qrTab);

        $barcode_array['num_rows'] = $size;
        $barcode_array['num_cols'] = $size;
        $barcode_array['bcode'] = [];

        foreach ($qrTab as $line) {
            $arrAdd = [];
            foreach (str_split($line) as $char) $arrAdd[] = ($char == '1') ? 1 : 0;
            $barcode_array['bcode'][] = $arrAdd;
        }

        return $barcode_array;
    }

    /** Clear in-memory frame/mask cache. */
    public static function clearCache(): void {
        self::$frames = [];
    }

    /**
     * Pre-build frames/masks for all versions and optionally store PNGs.
     * Useful for environments where runtime generation must be minimized.
     */
    public static function buildCache(): void {
        self::markTime('before_build_cache');

        $mask = new QRmask();
        for ($a = 1; $a <= QRspec::VERSION_MAX; $a++) {
            $frame = QRspec::newFrame($a);
            if (QRimage::IMAGE) {
                $fileName = (self::CACHE_DIR ?: '') . 'frame_' . $a . '.png';
                QRimage::png(self::binarize($frame), $fileName, 1, 0);
            }

            $width = count($frame);
            $bitMask = array_fill(0, $width, array_fill(0, $width, 0));
            for ($maskNo = 0; $maskNo < 8; $maskNo++) $mask->makeMaskNo($maskNo, $width, $frame, $bitMask, true);
        }

        self::markTime('after_build_cache');
    }

    /**
     * Append an error message to a log file when LOG_DIR is configured.
     */
    public static function log(bool|string $outfile, string $err): void {
        if (self::LOG_DIR !== false) {
            if ($err !== '') {
                if ($outfile !== false) {
                    file_put_contents(self::LOG_DIR . basename($outfile) . '-errors.txt', date('Y-m-d H:i:s') . ': ' . $err, FILE_APPEND);
                } else {
                    file_put_contents(self::LOG_DIR . 'errors.txt', date('Y-m-d H:i:s') . ': ' . $err, FILE_APPEND);
                }
            }
        }
    }

    /** Debug helper: echo out the frame values as CSV. */
    public static function dumpMask(array $frame): void {
        $width = count($frame);
	    foreach($frame as $yValue) {
	        for ($x = 0; $x < $width; $x++) {
	            echo ord($yValue[$x]) . ',';
	        }
	    }
    }

    /** Record a time marker (used by timeBenchmark()). */
    public static function markTime(string $markerId): void {
        [$uSec, $sec] = explode(" ", microtime());
        $time = ((float)$uSec + (float)$sec);

        if (!isset($GLOBALS['qr_time_bench'])) $GLOBALS['qr_time_bench'] = [];

        $GLOBALS['qr_time_bench'][$markerId] = $time;
    }

    /**
     * Output a simple HTML table of timing markers and totals.
     */
    public static function timeBenchmark(): void {
        self::markTime('finish');

        $lastTime = 0;
        $startTime = 0;
        $p = 0;

        echo '<table cellpadding="3" cellspacing="1">
                    <thead><tr style="border-bottom:1px solid silver"><td colspan="2" style="text-align:center">BENCHMARK</td></tr></thead>
                    <tbody>';

        foreach ($GLOBALS['qr_time_bench'] as $markerId => $thisTime) {
            if ($p > 0) {
                echo '<tr><th style="text-align:right">till ' . $markerId . ': </th><td>' . number_format($thisTime - $lastTime, 6) . 's</td></tr>';
            } else {
                $startTime = $thisTime;
            }

            $p++;
            $lastTime = $thisTime;
        }

        echo '</tbody><tfoot>
                <tr style="border-top:2px solid black"><th style="text-align:right">TOTAL: </th><td>' . number_format($lastTime - $startTime, 6) . 's</td></tr>
            </tfoot>
            </table>';
    }
}

QRtools::markTime('start');
