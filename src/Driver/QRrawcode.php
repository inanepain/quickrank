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

use Inane\Stdlib\Exception\Exception;

/**
 * Interleaves data and ECC codewords into the raw code stream.
 *
 * Builds Reed–Solomon blocks based on spec and provides sequential access
 * to interleaved bytes via {@see QRrawcode::getCode()}.
 */
class QRrawcode {
	public int $version;
	public array $dataCode = [];
	public array $eccCode = [];
	public int $blocks;
	public array $rsBlocks = []; // of QRrsblock
	public int $count;
	public int $dataLength;
	public int $eccLength;
	public int $b1;

	/**
	 * @throws Exception When input byte stream is null or RS blocks fail to init
	 */
	public function __construct(QRinput $input) {
		$spec = [0, 0, 0, 0, 0];

		$this->dataCode = $input->getByteStream();

		QRspec::getEccSpec($input->getVersion(), $input->getErrorCorrectionLevel(), $spec);

		$this->version = $input->getVersion();
		$this->b1 = QRspec::rsBlockNum1($spec);
		$this->dataLength = QRspec::rsDataLength($spec);
		$this->eccLength = QRspec::rsEccLength($spec);
		$this->eccCode = array_fill(0, $this->eccLength, 0);
		$this->blocks = QRspec::rsBlockNum($spec);

		$ret = $this->init($spec);
		if ($ret < 0) {
			throw new Exception('block alloc error');
		}

		$this->count = 0;
	}

	/**
	 * Initialize RS blocks and populate parity buffers for both block groups.
	 *
	 * @return int 0 on success, -1 on failure
	 */
	public function init(array $spec): int {
		$dl = QRspec::rsDataCodes1($spec);
		$el = QRspec::rsEccCodes1($spec);
		$rs = QRrs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);

		$blockNo = 0;
		$dataPos = 0;
		$eccPos = 0;
		for($i = 0; $i < QRspec::rsBlockNum1($spec); $i++) {
			[$dataPos, $eccPos, $blockNo] = $this->parseRsBlock($eccPos, $dl, $dataPos, $el, $rs, $blockNo);
		}

		if (QRspec::rsBlockNum2($spec) === 0) return 0;

		$dl = QRspec::rsDataCodes2($spec);
		$el = QRspec::rsEccCodes2($spec);
		$rs = QRrs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);

		if ($rs === null) return -1;

		for($i = 0; $i < QRspec::rsBlockNum2($spec); $i++) {
			[$dataPos, $eccPos, $blockNo] = $this->parseRsBlock($eccPos, $dl, $dataPos, $el, $rs, $blockNo);
		}

		return 0;
	}

	/**
	 * Get next interleaved byte from data+ECC streams.
	 *
	 * @return int Byte value or 0 when beyond range
	 */
	public function getCode(): int {
		$ret = 0;

		if ($this->count < $this->dataLength) {
			$row = $this->count % $this->blocks;
			$col = (int)($this->count / $this->blocks);
			if ($col >= $this->rsBlocks[0]->dataLength) {
				$row += $this->b1;
			}
			$ret = $this->rsBlocks[$row]->data[$col];
		}
		elseif ($this->count < $this->dataLength + $this->eccLength) {
			$row = ($this->count - $this->dataLength) % $this->blocks;
			$col = (int)(($this->count - $this->dataLength) / $this->blocks);
			$ret = $this->rsBlocks[$row]->ecc[$col];
		}
		else {
			return 0;
		}
		$this->count++;

		return $ret;
	}

	/**
	 * @param mixed         $eccPos
	 * @param mixed         $dl
	 * @param mixed         $dataPos
	 * @param mixed         $el
	 * @param null|QRrsItem $rs
	 * @param int           $blockNo
	 *
	 * @return array
	 */
	/**
	 * Build a single RS block object and update running positions.
	 *
	 * @return array{0:int,1:int,2:int} Updated [$dataPos, $eccPos, $blockNo]
	 */
	protected function parseRsBlock(mixed $eccPos, mixed $dl, mixed $dataPos, mixed $el, ?QRrsItem $rs, int $blockNo): array {
		$ecc = array_slice($this->eccCode, $eccPos);
		$this->rsBlocks[$blockNo] = new QRrsblock($dl, array_slice($this->dataCode, $dataPos), $el, $ecc, $rs);
		$this->eccCode = array_merge(array_slice($this->eccCode, 0, $eccPos), $ecc);

		$dataPos += $dl;
		$eccPos += $el;
		$blockNo++;

		return [$dataPos, $eccPos, $blockNo];
	}
}
