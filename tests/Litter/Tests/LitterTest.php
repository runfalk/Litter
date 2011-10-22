<?php
namespace Litter\Tests;

use Litter\Litter;

class LitterTest extends \PHPUnit_Framework_TestCase {
	function testCount() {
		$this->assertEquals(5, $this->provider()->count());
	}
	function testLength() {
		$l = $this->provider();
		$this->assertEquals($l->count(), $l->length());
	}
	/**
	 *	Remember that suhosin.executor.include.whitelist must allow data as protocol
	 *	and allow_url_include must be turned on in php.ini
	 */
	function testStringSource() {
		$this->assertEquals(
			include Litter::stringSource("<?php return 5; ?>"),
			5);
	}
	function provider() {
		return new Litter(5);
	}
}
