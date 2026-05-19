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
 * Reed–Solomon codec tables and encoding routines (char symbols).
 *
 * Encapsulates GF(2^mm) lookup tables and generator polynomial for
 * systematic RS encoding. Instances are created via
 * {@see QRrs::init_rs()} which caches them by parameters.
 */
class QRrsItem {
    /** Bits per symbol. */
    public int $mm;                    // Bits per symbol
    /** Symbols per block (= (1<<mm)-1). */
    public int $nn;                    // Symbols per block (= (1<<mm)-1)
    /** log lookup table. @var array<int,int> */
    public array $alpha_to = [];       // log lookup table
    /** Antilog lookup table. @var array<int,int> */
    public array $index_of = [];       // Antilog lookup table
    /** Generator polynomial (index form). @var array<int,int> */
    public array $genPoly = [];        // Generator polynomial
    /** Number of generator roots = number of parity symbols. */
    public int $nRoots;                // Number of generator roots = number of parity symbols
    /** First consecutive root, index form. */
    public int $fcr;                   // First consecutive root, index form
    /** Primitive element, index form. */
    public int $prim;                  // Primitive element, index form
    /** prim-th root of 1, index form. */
    public int $iPrim;                 // prim-th root of 1, index form
    /** Padding bytes in a shortened block. */
    public int $pad;                   // Padding bytes in a shortened block
    /** Field generator polynomial. */
    public int $gfPoly;

    /**
     * Modulo operation in index space for RS arithmetic.
     */
    public function modnn(int $x): int {
        while ($x >= $this->nn) {
            $x -= $this->nn;
            $x = ($x >> $this->mm) + ($x & $this->nn);
        }

        return $x;
    }

    /**
     * Initialize RS codec for given parameters (char symbols).
     *
     * Based on Phil Karn's LGPL implementation.
     *
     * @return QRrsItem|null Null when parameters are invalid or field poly is not primitive
     */
    public static function init_rs_char(int $symSize, int $gfPoly, int $fcr, int $prim, int $nRoots, int $pad): ?QRrsItem {
        // Common code for initialising a Reed-Solomon control block (char or int symbols)
        // Copyright 2004 Phil Karn, KA9Q
        // May be used under the terms of the GNU Lesser General Public License (LGPL)

        $rs = null;

        // Check parameter ranges
        if ($symSize < 0 || $symSize > 8) return $rs;
        if ($fcr < 0 || $fcr >= (1 << $symSize)) return $rs;
        if ($prim <= 0 || $prim >= (1 << $symSize)) return $rs;
        if ($nRoots < 0 || $nRoots >= (1 << $symSize)) return $rs;           // Can't have more roots than symbol values!
        if ($pad < 0 || $pad >= ((1 << $symSize) - 1 - $nRoots)) return $rs; // Too much padding

        $rs = new QRrsItem();
        $rs->mm = $symSize;
        $rs->nn = (1 << $symSize) - 1;
        $rs->pad = $pad;

        $rs->alpha_to = array_fill(0, $rs->nn + 1, 0);
        $rs->index_of = array_fill(0, $rs->nn + 1, 0);

        // PHP style macro replacement ;)
        $NN = &$rs->nn;
        $A0 = &$NN;

        // Generate Galois field lookup tables
        $rs->index_of[0] = $A0;                                              // log(zero) = -inf
        $rs->alpha_to[$A0] = 0;                                              // alpha**-inf = 0
        $sr = 1;

        for ($i = 0; $i < $rs->nn; $i++) {
            $rs->index_of[$sr] = $i;
            $rs->alpha_to[$i] = $sr;
            $sr <<= 1;
            if ($sr & (1 << $symSize)) {
                $sr ^= $gfPoly;
            }
            $sr &= $rs->nn;
        }

        if ($sr !== 1) {
            // field generator polynomial is not primitive!
            $rs = null;

            return $rs;
        }

        /* Form RS code generator polynomial from its roots */
        $rs->genPoly = array_fill(0, $nRoots + 1, 0);

        $rs->fcr = $fcr;
        $rs->prim = $prim;
        $rs->nRoots = $nRoots;
        $rs->gfPoly = $gfPoly;

        /* Find prim-th root of 1, used in decoding */
        $iprim = 1;
        while (($iprim % $prim) !== 0) {
            $iprim += $rs->nn;
        }

        $rs->iPrim = (int)($iprim / $prim);
        $rs->genPoly[0] = 1;

        for ($i = 0, $root = $fcr * $prim; $i < $nRoots; $i++, $root += $prim) {
            $rs->genPoly[$i + 1] = 1;

            // Multiply rs->genPoly[] by  @**(root + x)
            for ($j = $i; $j > 0; $j--) {
                if ($rs->genPoly[$j] !== 0) {
                    $rs->genPoly[$j] = $rs->genPoly[$j - 1] ^ $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genPoly[$j]] + $root)];
                } else {
                    $rs->genPoly[$j] = $rs->genPoly[$j - 1];
                }
            }
            // rs->genPoly[0] can never be zero
            $rs->genPoly[0] = $rs->alpha_to[$rs->modnn($rs->index_of[$rs->genPoly[0]] + $root)];
        }

        // convert rs->genPoly[] to index form for quicker encoding
        for ($i = 0; $i <= $nRoots; $i++) $rs->genPoly[$i] = $rs->index_of[$rs->genPoly[$i]];

        return $rs;
    }

    /**
     * Encode parity symbols for the provided data using this codec tables.
     *
     * @param array<int,int> $data   Data bytes
     * @param array<int,int> $parity Output parity bytes (size = nRoots)
     */
    public function encode_rs_char(array $data, array &$parity): void {
        $MM = &$this->mm;
        $NN = &$this->nn;
        $ALPHA_TO = &$this->alpha_to;
        $INDEX_OF = &$this->index_of;
        $GENPOLY = &$this->genPoly;
        $NROOTS = &$this->nRoots;
        $FCR = &$this->fcr;
        $PRIM = &$this->prim;
        $IPRIM = &$this->iPrim;
        $PAD = &$this->pad;
        $A0 = &$NN;

        $parity = array_fill(0, $NROOTS, 0);

        for ($i = 0; $i < ($NN - $NROOTS - $PAD); $i++) {
            $feedback = $INDEX_OF[$data[$i] ^ $parity[0]];
            if ($feedback !== $A0) {
                // feedback term is non-zero

                // This line is unnecessary when GENPOLY[NROOTS] is unity, as it must
                // always be for the polynomials constructed by init_rs()
                $feedback = $this->modnn($NN - $GENPOLY[$NROOTS] + $feedback);

                for ($j = 1; $j < $NROOTS; $j++) {
                    $parity[$j] ^= $ALPHA_TO[$this->modnn($feedback + $GENPOLY[$NROOTS - $j])];
                }
            }

            // Shift 
            array_shift($parity);
            if ($feedback !== $A0) {
                $parity[] = $ALPHA_TO[$this->modnn($feedback + $GENPOLY[0])];
            } else {
                $parity[] = 0;
            }
        }
    }
}
