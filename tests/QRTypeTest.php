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
 * @author   Philip Michael Raab <philip@cathedral.co.za>
 * @package  inanepain\quickrank
 * @category quickrank
 *
 * @license  UNLICENSE
 * @license  https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types = 1);

namespace Inane\QR\Tests;

use Inane\QR\Enum\QRType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `Inane\QR\Enum\QRType`.
 */
final class QRTypeTest extends TestCase {
    /**
     * Verifies name-based case resolution with both strict and ignore-case
     * matching.
     *
     * @return void
     */
    public function testTryFromNameSupportsCaseSensitivityOptions(): void {
        $this->assertSame(QRType::WiFi, QRType::tryFromName('WiFi'));
        $this->assertNull(QRType::tryFromName('wifi'));
        $this->assertSame(QRType::WiFi, QRType::tryFromName('wifi', true));
        $this->assertNull(QRType::tryFromName('DoesNotExist'));
    }

    /**
     * Ensures QR type detection identifies known prefixes and falls back to
     * text for unknown or invalid formats.
     *
     * @return void
     */
    public function testIdentifyTypeDetectsCommonInputs(): void {
        $this->assertSame(QRType::WiFi, QRType::identifyType('WIFI:T:WPA;S:MyNetwork;P:Secret;;'));
        $this->assertSame(QRType::Email, QRType::identifyType('mailto:person@example.com?subject=Hi'));
        $this->assertSame(QRType::URL, QRType::identifyType('https://example.com/path?x=1'));
        $this->assertSame(QRType::Text, QRType::identifyType('Hello world'));
        $this->assertSame(QRType::Text, QRType::identifyType('not a valid uri:with spaces in it'));
    }
}
