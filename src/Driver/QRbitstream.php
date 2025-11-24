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

/**
 * Simple bitstream helper used to build QR payloads.
 *
 * Stores an array of bits (0/1) and provides helpers to append
 * numbers/bytes and convert back to a byte array.
 */
class QRbitstream {
    /**
     * Array of bits (0/1).
     *
     * @var array<int,int>
     */
    public array $data = [];

    /**
     * Current number of bits in the stream.
     */
    public function size(): int {
        return count($this->data);
    }

    /**
     * Allocate the stream with a given length, filled with zeros.
     *
     * @return int 0 on success
     */
    public function allocate(int $setLength): int {
        $this->data = array_fill(0, $setLength, 0);

        return 0;
    }

    /**
     * Create a bitstream from a fixed-size integer value.
     *
     * @param int $bits Number of bits to emit
     * @param int $num  Value
     */
    public static function newFromNum(int $bits, int $num): QRbitstream {
        $bstream = new QRbitstream();
        $bstream->allocate($bits);

        $mask = 1 << ($bits - 1);
        for ($i = 0; $i < $bits; $i++) {
            if ($num & $mask) {
                $bstream->data[$i] = 1;
            } else {
                $bstream->data[$i] = 0;
            }
            $mask = $mask >> 1;
        }

        return $bstream;
    }

    /**
     * Create a bitstream from a byte array.
     *
     * @param int          $size Number of bytes
     * @param array<int,int> $data Bytes (0..255)
     */
    public static function newFromBytes(int $size, array $data): QRbitstream {
        $bstream = new QRbitstream();
        $bstream->allocate($size * 8);
        $p = 0;

        for ($i = 0; $i < $size; $i++) {
            $mask = 0x80;
            for ($j = 0; $j < 8; $j++) {
                if ($data[$i] & $mask) {
                    $bstream->data[$p] = 1;
                } else {
                    $bstream->data[$p] = 0;
                }
                $p++;
                $mask = $mask >> 1;
            }
        }

        return $bstream;
    }

    /**
     * Append another bitstream to this one.
     *
     * @return int 0 on success, -1 on invalid arg
     */
    public function append(QRbitstream $arg): int {
	    if ($arg->size() === 0) {
            return 0;
        }

        if ($this->size() === 0) {
            $this->data = $arg->data;

            return 0;
        }

        $this->data = array_values(array_merge($this->data, $arg->data));

        return 0;
    }

    /**
     * Append a fixed-width integer value.
     *
     * @return int 0 on success, -1 on allocation error
     */
    public function appendNum(int $bits, int $num): int {
        if ($bits === 0) return 0;

        $b = self::newFromNum($bits, $num);

	    $ret = $this->append($b);
        unset($b);

        return $ret;
    }

    /**
     * Append an array of bytes (size specifies how many to take from $data).
     *
     * @param int            $size Number of bytes to append
     * @param array<int,int> $data Source bytes
     *
     * @return int 0 on success, -1 on allocation error
     */
    public function appendBytes(int $size, array $data): int {
        if ($size === 0) return 0;

        $b = self::newFromBytes($size, $data);

	    $ret = $this->append($b);
        unset($b);

        return $ret;
    }

    /**
     * Convert the bit array to a byte array.
     * Bits are grouped left-to-right into bytes.
     *
     * @return array<int,int> Bytes
     */
    public function toByte(): array {
        $size = $this->size();

        if ($size === 0) {
            return [];
        }

        $data = array_fill(0, (int)(($size + 7) / 8), 0);
        $bytes = (int)($size / 8);

        $p = 0;

        for ($i = 0; $i < $bytes; $i++) {
            $v = 0;
            for ($j = 0; $j < 8; $j++) {
                $v <<= 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$i] = $v;
        }

        if ($size & 7) {
            $v = 0;
            for ($j = 0; $j < ($size & 7); $j++) {
                $v <<= 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$bytes] = $v;
        }

        return $data;
    }
}
