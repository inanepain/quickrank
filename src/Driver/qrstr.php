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

namespace Inane\QR\Driver;

/**
 * QR string helpers and constants.
 *
 * Provides mode/level constants and a small string replacement utility
 * used while building the textual representation of QR frames.
 */
class qrstr {
    #region Modes
    public const int MODE_NUL = -1;
    public const int MODE_NUM = 0;
    public const int MODE_AN  = 1;
    public const int MODE_8  = 2;
    public const int MODE_KANJI = 3;
    public const int MODE_STRUCTURE = 4;
    #endregion Modes

    #region Levels of error correction.
    public const int ECLEVEL_L = 0;
    public const int ECLEVEL_M = 1;
    public const int ECLEVEL_Q = 2;
    public const int ECLEVEL_H = 3;
    #endregion Levels of error correction.

    #region Supported output formats
    public const FORMAT_TEXT = 0;
    public const FORMAT_PNG = 1;
    #endregion Supported output formats

    /**
     * Replace a substring inside a given row of the frame buffer.
     *
     * @param array<int,string> $srcTab  Frame buffer (array of strings) passed by reference
     * @param int               $x       X offset in the row
     * @param int               $y       Y row index
     * @param string            $repl    Replacement string
     * @param int|false         $replLen Optional explicit length of replacement segment
     */
    public static function set(array &$srcTab, int $x, int $y, string $repl, int|false $replLen = false): void {
        $srcTab[$y] = substr_replace(
            $srcTab[$y],
            ($replLen !== false) ? substr($repl, 0, $replLen) : $repl,
            $x,
            ($replLen !== false) ? $replLen : strlen($repl)
        );
    }
}
