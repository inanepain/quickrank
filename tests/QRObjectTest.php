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
use Inane\QR\QRObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `Inane\QR\QRObject`.
 */
final class QRObjectTest extends TestCase {
    /**
     * Ensures object type inference is derived from provided text.
     *
     * @return void
     */
    public function testConstructInfersTypeFromText(): void {
        $wifi = new QRObject('WIFI:T:WPA;S:MyNetwork;P:Secret;;');
        $url = new QRObject('https://example.com');
        $text = new QRObject('Plain text payload');

        $this->assertSame(QRType::WiFi, $wifi->type);
        $this->assertSame(QRType::URL, $url->type);
        $this->assertSame(QRType::Text, $text->type);
    }

    /**
     * Verifies the generated base64 image format prefix and decodable payload.
     *
     * @return void
     */
    public function testGetImageBase64ReturnsDataUriPngPayload(): void {
        if (!function_exists('imagecreate')) {
            $this->markTestSkipped('GD extension is required for PNG rendering tests.');
        }

        $object = new QRObject('https://example.com/image-test');
        $dataUri = $object->getImageBase64();

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $payload = substr($dataUri, strlen('data:image/png;base64,'));
        $binary = base64_decode($payload, true);

        $this->assertNotFalse($binary);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $binary);
    }
}
