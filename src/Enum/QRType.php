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

namespace Inane\QR\Enum;

use Uri\Rfc3986\Uri;

use function strcasecmp;

use const false;
use const null;

/**
 * QRCodeType Enum
 * - URL: 'https://example.com'
 * - Text: 'Hello world'
 * - vCard: "BEGIN:VCARD\nVERSION:3.0\nN:Doe;John\nFN:John Doe\nTEL:123456789\nEMAIL:john@example.com\nEND:VCARD"
 * - Email: 'mailto:info@example.com?subject=Hi'
 * - SMS: 'SMSTO:123456789:Message here'
 * - WiFi: 'WIFI:T:WPA;S:mynetwork;P:mypassword;;'
 * - Geo: 'geo:37.7749,-122.4194'
 * - Phone: 'tel:+123456789'
 */
enum QRType: string {
	case WiFi  = 'WIFI';
	case Email = 'mailto';
	case SMS   = 'SMSTO';
	case Text  = ''; // NONE OF THE OTHERS
	case URL   = '*protocal*:';
	case Geo   = 'geo';
	case Phone = 'tel';
	case vCard = 'BEGIN';
	//	case vCard = 'BEGIN:VCARD';

	/**
	 * Attempts to create an instance of the enum from the given name.
	 *
	 * @param string $name       The name of the enum case to match.
	 * @param bool   $ignoreCase Whether to perform a case-insensitive match. Defaults to false.
	 *
	 * @return QRType|null Returns an instance of the enum if a match is found, or null otherwise.
	 */
	public static function tryFromName(string $name, bool $ignoreCase = false): ?QRType {
		foreach(self::cases() as $case) if (($ignoreCase && strcasecmp($case->name, $name) === 0) || $case->name === $name) return $case;

		return null;
	}

	/**
	 * Identifies the QR Type of the given string based on its format.
	 *
	 * @param string $text The input string to be analysed.
	 *
	 * @return QRType Returns the identified type as a static value.
	 */
	public static function identifyType(string $text): self {
		$pos = strpos($text, ':');
		if ($pos === false) return self::Text;

		$key = substr($text, 0, $pos);
		if ($type = self::tryFrom($key)) return $type;

		try {
			new Uri($text);
		} catch (\Exception) {
			return self::Text;
		}
		return self::URL;
	}
}
