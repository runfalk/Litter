<?php
namespace Litter\Tests;

use Litter\IntegerIterator;

class IntegerIteratorTest extends \PHPUnit_Framework_TestCase {
	function testCount() {
		$iter = new IntegerIterator(5);
		$this->assertEquals(5, $iter->count());
	}
	function testIterating() {
		$n = 5;
		$i = 0;

		foreach (new IntegerIterator($n) as $k => $v) {
			$this->assertEquals($i++, $v);
			$this->assertEquals($k, $v);
		}

		$this->assertEquals($i, $n);
	}
	/**
	 *	@expectedException \InvalidArgumentException
	 */
	function testInput() {
		new IntegerIterator("1");
	}
}
