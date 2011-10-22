<?php
namespace Litter\Tests;

use Litter\GroupIterator;

class GroupIteratorTest extends \PHPUnit_Framework_TestCase {
	function testCount() {
		$iter = new GroupIterator($this->provider(), 2);
		$this->assertEquals(3, $iter->count());
	}
	function testIterating() {
		$n = 0;
		$r = array();
		foreach (new GroupIterator($this->provider(), 2) as $k => $v) {
			$r[$n] = iterator_to_array($v, FALSE);
			$this->assertEquals($k, $n++);
		}
		$this->assertEquals(3, $n);
		$this->assertEquals(
			$r,
			array(
				array(1, 2),
				array(3, 4),
				array(5)));
	}
	/**
	 *	@expectedException \InvalidArgumentException
	 */
	function testInvalidSizeInput() {
		new GroupIterator($this->provider(), "1");
	}
	/**
	 *	@expectedException \InvalidArgumentException
	 */
	function testInvalidIteratorInput() {
		new GroupIterator(array(), 3);
	}
	function provider() {
		return new \ArrayIterator(array(1, 2, 3, 4, 5));
	}
}
