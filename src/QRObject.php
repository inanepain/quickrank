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

namespace Inane\QR;

use Inane\QR\Enum\QRType;

/**
 * Represents a QR Code with configurable text content.
 * This class allows the creation and manipulation of a QR Code by setting or getting its text content.
 * WIFI:
 *  WIFI:T:<AuthenticationType>;S:<SSID>;P:<Password>;;
 */
class QRObject {
	protected(set) QRType $type {
		get => $this->type ?? ($this->type = QRType::identifyType($this->text));
		set => $this->type = $value;
	}

	public function __construct(
		protected(set) ?string $text {
			get => $this->text ?? null;
			set => $this->text = $value;
		},
	) {
		$this->type = QRType::identifyType($this->text);
	}

	/**
	 * QRCode raw image data
	 *
	 * @return string raw binary image data of QRCode
	 */
	public function getImageData(): string {
		$tmp = tempnam(sys_get_temp_dir(), 'qr-code-');
		QRcode::png($this->text, $tmp, Driver\qrstr::ECLEVEL_H, 10, 1);
		$data = file_get_contents($tmp);
		unlink($tmp);

		return $data;
	}

	/**
	 * QRCode as a base64 image
	 *
	 * @return string base64 string of QRCode
	 */
	public function getImageBase64(): string {
		return 'data:image/png;base64,' . base64_encode($this->getImageData());
	}
}