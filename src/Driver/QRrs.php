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
 * Reed–Solomon codec factory/cache.
 *
 * Provides cached initialisation for QRrsItem instances with matching
 * parameters to avoid recomputing lookup tables.
 */
class QRrs {
    public static array $items = [];

    /**
     * Get or create an RS codec instance for the given parameters.
     *
     * @return QRrsItem|null Cached or newly created instance
     */
    public static function init_rs(int $symSize, int $gfPoly, int $fcr, int $prim, int $nRoots, int $pad): ?QRrsItem {
        foreach (self::$items as $rs) {
            if ($rs->pad !== $pad) continue;
            if ($rs->nRoots !== $nRoots) continue;
            if ($rs->mm !== $symSize) continue;
            if ($rs->gfPoly !== $gfPoly) continue;
            if ($rs->fcr !== $fcr) continue;
            if ($rs->prim !== $prim) continue;

            return $rs;
        }

        $rs = QRrsItem::init_rs_char($symSize, $gfPoly, $fcr, $prim, $nRoots, $pad);
        array_unshift(self::$items, $rs);

        return $rs;
    }
}
