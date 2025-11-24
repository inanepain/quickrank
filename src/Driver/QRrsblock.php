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
 * Container for a single Reed–Solomon block: data + parity.
 */
class QRrsblock {
    /** Number of data codewords in this block. */
    public int $dataLength;
    /** Data codewords. @var array<int,int> */
    public array $data = [];
    /** Number of ECC codewords in this block. */
    public int $eccLength;
    /** ECC codewords (parity). @var array<int,int> */
    public array $ecc = [];

    /**
     * @param int        $dl   Data codewords count
     * @param array<int,int> $data Data bytes
     * @param int        $el   ECC codewords count
     * @param array<int,int> $ecc  Reference to global ECC buffer slice (will be mutated)
     * @param QRrsItem   $rs   RS codec
     */
    public function __construct(int $dl, array $data, int $el, array &$ecc, QRrsItem $rs) {
        $rs->encode_rs_char($data, $ecc);

        $this->dataLength = $dl;
        $this->data = $data;
        $this->eccLength = $el;
        $this->ecc = $ecc;
    }
}
