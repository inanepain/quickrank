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
 * Iterates over a QR frame to place data bits.
 *
 * This helper encapsulates the zig-zag traversal used when filling the
 * data portion of the QR matrix. It skips over reserved modules that are
 * already marked in the frame (modules with the highest bit set).
 */
class FrameFiller {
    /**
     * Width/height of the square QR frame.
     */
    public int $width;

    /**
     * 2D frame buffer (array of strings), mutated in-place.
     * Each element is a string of bytes representing module flags/bits.
     *
     * @var array<int, string>
     */
    public array $frame;

    /** Current x position (column index). */
    public int $x;
    /** Current y position (row index). */
    public int $y;
    /** Current vertical direction: 1 down, -1 up. */
    public int $dir;
    /** Sub-bit toggle within a 2-column step: -1 uninitialized, 0/1 active. */
    public int $bit;

    /**
     * @param int                $width Size of the frame (width == height).
     * @param array<int, string> $frame Reference to the frame buffer.
     */
    public function __construct(int $width, array &$frame) {
        $this->width = $width;
        $this->frame = $frame;
        $this->x = $width - 1;
        $this->y = $width - 1;
        $this->dir = -1;
        $this->bit = -1;
    }

    /**
     * Set a single module in the frame.
     *
     * @param array{x:int,y:int} $at  Coordinates.
     * @param int                $val Byte value to set (will be chr()-cast).
     */
    public function setFrameAt(array $at, int $val): void {
        $this->frame[$at['y']][$at['x']] = chr($val);
    }

    /**
     * Get a single module from the frame.
     *
     * @param array{x:int,y:int} $at Coordinates.
     *
     * @return int Byte value (0-255).
     */
    public function getFrameAt(array $at): int {
        return ord($this->frame[$at['y']][$at['x']]);
    }

    /**
     * Advance to the next fillable module following QR spec zig-zag pattern.
     *
     * Reserved modules (MSB set) are skipped automatically.
     *
     * @return null|array{x:int,y:int} Next coordinate or null if finished.
     */
    public function next(): ?array {
        do {
            if ($this->bit === -1) {
                $this->bit = 0;

                return ['x' => $this->x, 'y' => $this->y];
            }

            $x = $this->x;
            $y = $this->y;
            $w = $this->width;

            if ($this->bit === 0) {
                $x--;
                $this->bit++;
            } else {
                $x++;
                $y += $this->dir;
                $this->bit--;
            }

            if ($this->dir < 0) {
                if ($y < 0) {
                    $y = 0;
                    $x -= 2;
                    $this->dir = 1;
                    if ($x === 6) {
                        $x--;
                        $y = 9;
                    }
                }
            } else {
                if ($y === $w) {
                    $y = $w - 1;
                    $x -= 2;
                    $this->dir = -1;
                    if ($x === 6) {
                        $x--;
                        $y -= 8;
                    }
                }
            }
            if ($x < 0 || $y < 0) return null;

            $this->x = $x;
            $this->y = $y;
        } while (ord($this->frame[$y][$x]) & 0x80);

        return ['x' => $x, 'y' => $y];
    }
}
