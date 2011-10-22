<?php
namespace Litter\Tests;

use Litter\StringIterator;

class StringIteratorTest extends \PHPUnit_Framework_TestCase {
	function testCount() {
		$this->assertEquals(4, $this->provider()->count());
	}
	function testIterating() {
		$this->assertEquals(
			array("田", "中", "で", "す"),
			iterator_to_array($this->provider()));
	}
	/**
	 *	@expectedException \InvalidArgumentException
	 */
	function testInvalidStringInput() {
		new StringIterator(1);
	}
	/**
	 *	@expectedException \InvalidArgumentException
	 */
	function testInvalidEncodingInput() {
		new StringIterator("test", "klingon");
	}
	function provider() {
		return new StringIterator("田中です", "UTF-8");
	}
}
