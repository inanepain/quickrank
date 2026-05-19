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

use GdImage;

/**
 * Image rendering utilities based on GD.
 *
 * Converts a binary frame (array of '0'/'1' strings) into PNG/JPEG images.
 */
class QRimage {
	public const bool IMAGE            = true;
	public const int  PNG_MAXIMUM_SIZE = 1024;

	/**
	 * Render a frame to PNG.
	 *
	 * @param array<int,string> $frame         Binary frame
	 * @param false|string      $filename      Output filename or false to print
	 * @param int               $pixelPerPoint Pixel scale
	 * @param int               $outerFrame    Quiet zone (modules)
	 * @param bool              $saveAndPrint  Save and also output
	 */
	public static function png(array $frame, false|string $filename = false, int $pixelPerPoint = 4, int $outerFrame = 4, bool $saveAndPrint = false): void {
		$image = self::image($frame, $pixelPerPoint, $outerFrame);

		if ($filename === false) {
			Header('Content-type: image/png');
			ImagePng($image);
		} else {
			if ($saveAndPrint === true) {
				ImagePng($image, $filename);
				header('Content-type: image/png');
				ImagePng($image);
			} else {
				ImagePng($image, $filename);
			}
		}
	}

	/**
	 * Render a frame to JPEG.
	 *
	 * @param array<int,string> $frame         Binary frame
	 * @param false|string      $filename      Output filename or false to print
	 * @param int               $pixelPerPoint Pixel scale
	 * @param int               $outerFrame    Quiet zone (modules)
	 * @param int               $q             JPEG quality
	 */
	public static function jpg(array $frame, bool|string $filename = false, int $pixelPerPoint = 8, int $outerFrame = 4, int $q = 85): void {
		$image = self::image($frame, $pixelPerPoint, $outerFrame);

		if ($filename === false) {
			Header("Content-type: image/jpeg");
			ImageJpeg($image, null, $q);
		} else {
			ImageJpeg($image, $filename, $q);
		}
	}

	/**
	 * Create a scaled GD image from the frame.
	 *
	 * @param array<int,string> $frame         Binary frame
	 * @param int               $pixelPerPoint Pixel scale
	 * @param int               $outerFrame    Quiet zone (modules)
	 *
	 * @return GdImage|false Base image scaled to final size
	 */
	private static function image(array $frame, int $pixelPerPoint = 4, int $outerFrame = 4): GdImage|false {
		$h = count($frame);
		$w = strlen($frame[0]);

		$imgW = $w + 2 * $outerFrame;
		$imgH = $h + 2 * $outerFrame;

		$base_image = ImageCreate($imgW, $imgH);

		$col[0] = ImageColorAllocate($base_image, 255, 255, 255);
		$col[1] = ImageColorAllocate($base_image, 0, 0, 0);

		imagefill($base_image, 0, 0, $col[0]);

		foreach ($frame as $y => $yValue) {
			for ($x = 0; $x < $w; $x++) {
				if ($yValue[$x] === '1') {
					ImageSetPixel($base_image, $x + $outerFrame, $y + $outerFrame, $col[1]);
				}
			}
		}

		$target_image = ImageCreate($imgW * $pixelPerPoint, $imgH * $pixelPerPoint);
		ImageCopyResized($target_image, $base_image, 0, 0, 0, 0, $imgW * $pixelPerPoint, $imgH * $pixelPerPoint, $imgW, $imgH);

		return $target_image;
	}
}
