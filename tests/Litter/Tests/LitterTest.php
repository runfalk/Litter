<?php
namespace Litter\Tests;

use Litter\Litter;

class LitterTest extends \PHPUnit_Framework_TestCase {
	function testArrayIteration() {
		$array = array("foo" => "bar", "baz" => "buz");
		$l = new Litter($array);
		$this->assertEquals($array, array_map("strval", iterator_to_array($l)));
	}
	function testTraversableIteration() {
		$dom = simplexml_load_string('
			<root>
				<child>value1</child>
				<child>value2</child>
			</root>');
		$l = new Litter($dom->child);
		$this->assertEquals(
			array("value1", "value2"),
			array_map("strval", iterator_to_array($l, FALSE)));
	}
	function testStdClassIteration() {
		$array = array("foo" => "bar", "baz" => "buz");
		$l = new Litter((object)$array);
		$this->assertEquals($array, array_map("strval", iterator_to_array($l)));
	}
	function testCount() {
		$this->assertEquals(5, $this->provider()->count());

		$l = new Litter("foobar");
		$this->assertEquals(6, $l->count());
	}
	function testLength() {
		$l = $this->provider();
		$this->assertEquals($l->count(), $l->length());
	}
	function testIndex() {
		$i = 0;
		foreach ($this->provider() as $v) {
			$this->assertEquals(++$i, $v->index()->raw());
		}

		$i = 0;
		foreach ($this->provider() as $v) {
			$this->assertEquals($i++, $v->index(TRUE)->raw());
		}
	}
	/**
	 *	@expectedException Litter\Exception
	 */
	function testIndexOutsideLoopError() {
		$this->provider()->index();
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
	function testCycle() {
		$r = array();
		foreach ($this->provider() as $p) {
			$r[] = $p->cycle(array("a", "b", "c"))->raw();
		}
		$this->assertEquals($r, array("a", "b", "c", "a", "b"));
	}
	/**
	 *	@expectedException Litter\Exception
	 */
	function testCycleOutsideLoopError() {
		$this->provider()->cycle(array(1, 2));
	}
	function testUpper() {
		$l = new Litter("Törst");
		$this->assertEquals("TÖRST", $l->upper()->raw());
	}
	function testLower() {
		$l = new Litter("TÖRST");
		$this->assertEquals("törst", $l->lower()->raw());
	}
	function testReplace() {
		$l = new Litter("Törst");
		$this->assertEquals(
			"Torsk",
			$l->replace(array("ö" => "o", "t" => "k"))->raw());
	}
	function testSlice() {
		$l = $this->provider();
		$this->assertEquals(
			array("3", "4"),
			array_map("strval", iterator_to_array($l->slice(3), FALSE)));
	}
	function testEncode() {
		$l = new Litter("<&");
		$l = $l->encode("HTML-ENTITIES");
		$this->assertEquals("&lt;&amp;", $l->raw());
		$this->assertEquals("<&", $l->encode("UTF-8")->raw());

		$l = new Litter("åäö");
		$latin = $l->encode("ISO-8859-1");
		$this->assertEquals(3, strlen($latin->raw()));
		$this->assertEquals(6, strlen($latin->encode("UTF-8")->raw()));
	}
	/**
	 *	@expectedException \InvalidArgumentException
	 */
	function testInvalidEncodingException() {
		$this->provider()->encode("klingon");
	}
	function testToString() {
		$l = new Litter("<täst/>");
		$this->assertEquals("&lt;t&auml;st/&gt;", (string)$l);
	}
	/**
	 *	@depends testCount
	 */
	function testTruncate() {
		$l = new Litter("ABCDEFGHIJKLMNOPQRSTUVWXYZ");
		$this->assertEquals("ABCDEF", $l->truncate(6)->raw());
		$this->assertEquals("ABC...", $l->truncate(6, "...")->raw());
		$this->assertEquals($l->count(), $l->truncate($l->count())->count());
	}
	function testJoin() {
		$l = new Litter("Test");
		$this->assertEquals("T,e,s,t", $l->join(",")->raw());

		$this->assertEquals("0,1,2,3,4", $this->provider()->join(",")->raw());
	}
	function testClone() {
		$l = $this->provider();
		$this->assertEquals($l, clone $l);
	}
	function testInnerIterator() {
		$iter = new \ArrayIterator(array(1, 2, 3));
		$l = new Litter($iter);
		$this->assertEquals($l->getInnerIterator(), $iter);
	}
	function testGroup() {
		$r = array();
		foreach($this->provider()->group(2) as $v) {
			$r[] = array_map("strval", iterator_to_array($v, FALSE));
		}
		$this->assertEquals(
			$r,
			array(
				array("0", "1"),
				array("2", "3"),
				array("4")));
	}
	/**
	 *	@expectedException Litter\Exception
	 */
	function testNonIterableValueError() {
		$l = new Litter(FALSE);
		foreach ($l as $v) { }
	}

	// Test array access
	function testArrayAccessGet() {
		$l = new Litter(array("test" => "foobar"));
		$this->assertEquals("foobar", $l["test"]->raw());

		$l = new Litter("foobar");
		$this->assertEquals("b", $l[3]->raw());

		$obj = new \stdClass;
		$obj->test = "foobar";
		$l = new Litter($obj);
		$this->assertEquals("foobar", $l["test"]->raw());
	}
	function testArrayAccessIsset() {
		$l = new Litter(array("test" => "foobar"));
		$this->assertTrue(isset($l["test"]));
		$this->assertTrue(!isset($l["baz"]));

		$l = new Litter("foobar");
		$this->assertTrue(isset($l[3]));
		$this->assertTrue(!isset($l[6]));

		$obj = new \stdClass;
		$obj->test = "foobar";
		$l = new Litter($obj);
		$this->assertTrue(isset($l["test"]));
		$this->assertTrue(!isset($l["baz"]));
	}
	/**
	 *	@expectedException Litter\ReadOnlyException
	 */
	function testArrayAccessSet() {
		$l = new Litter(array("test" => "foobar"));
		$l["test"] = "baz";
	}
	/**
	 *	@expectedException Litter\ReadOnlyException
	 */
	function testArrayAccessUnset() {
		$l = new Litter(array("test" => "foobar"));
		unset($l["test"]);
	}
	/**
	 *	@expectedException \InvalidArgumentException
	 */
	function testArrayAccessStringNonIntegerIssetError() {
		$l = new Litter("foobar");
		isset($l["3"]);
	}
	/**
	 *	@expectedException \InvalidArgumentException
	 */
	function testArrayAccessStringNonIntegerGetError() {
		$l = new Litter("foobar");
		$l["3"];
	}
	/**
	 *	@expectedException Litter\NotImplementedException
	 */
	function testArrayAccessUnsupportedGetError() {
		$l = new Litter(FALSE);
		$l[0];
	}
	/**
	 *	@expectedException Litter\NotImplementedException
	 */
	function testArrayAccessUnsupportedIssetError() {
		$l = new Litter(FALSE);
		isset($l[0]);
	}

	// Test object access
	function testObjectGet() {
		$l = new Litter(array("test" => "foobar"));
		$this->assertEquals("foobar", $l->test->raw());

		$obj = new \stdClass;
		$obj->test = "foobar";
		$l = new Litter($obj);
		$this->assertEquals("foobar", $l->test->raw());
	}
	function testObjectIsset() {
		$l = new Litter(array("test" => "foobar"));
		$this->assertTrue(isset($l->test));
		$this->assertTrue(!isset($l->foo));
	}
	/**
	 *	@expectedException Litter\ReadOnlyException
	 */
	function testObjectSet() {
		$l = new Litter(array("test" => "foobar"));
		$l->test = "baz";
	}
	/**
	 *	@expectedException Litter\ReadOnlyException
	 */
	function testObjectUnset() {
		$l = new Litter(array("test" => "foobar"));
		unset($l->test);
	}
	/**
	 *	@expectedException Litter\NotImplementedException
	 */
	function testObjectUnsupportedGetError() {
		$l = new Litter(FALSE);
		$l->foo;
	}

	/**
	 *	@group   templating
	 *	@depends testStringSource
	 */
	function testTemplatingBaseFile() {
		ob_start();
		$l = new Litter;

		include Litter::stringSource(
			'foo<?php $l->block("baz"); ?>bar<?php $l->end(); ?>');

		$this->assertEquals("foobar", ob_get_clean());
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 */
	function testTemplatingSingleLevelInheritance() {
		ob_start();
		$l = new Litter;
		$base_src = Litter::stringSource(
			'foo<?php $l->block("baz"); ?>bar<?php $l->end(); ?>');

		$l->extending($base_src);
			$l->block("baz");
				echo "biz";
			$l->end();
		$l->done();

		$this->assertEquals("foobiz", ob_get_clean());
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 */
	function testTemplatingSingleLevelSuperInheritance() {
		ob_start();
		$l = new Litter;
		$base_src = Litter::stringSource(
			'foo<?php $l->block("baz"); ?>bar<?php $l->end(); ?>');

		$l->extending($base_src);
			$l->block("baz");
				echo "biz";
				$l->super();
			$l->end();
		$l->done();
		$this->assertEquals("foobizbar", ob_get_clean());
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 */
	function testTemplatingMultipleLevelInheritance() {
		ob_start();
		$l = new Litter;
		$second_src = Litter::stringSource(
			'<?php
				$base_src = $l->stringSource(
					\'foo<?php $l->block("baz"); ?>bar<?php $l->end(); ?>\');
				$l->extending($base_src);
					$l->block("baz");
						echo "biz";
					$l->end();
				$l->done();
			?>');

		$l->extending($second_src);
			$l->block("baz");
				echo "baz";
			$l->end();
		$l->done();

		$this->assertEquals("foobaz", ob_get_clean());
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 */
	function testTemplatingMultipleLevelSuperInheritance() {
		ob_start();
		$l = new Litter;
		$second_src = Litter::stringSource(
			'<?php
				$base_src = $l->stringSource(
					\'foo<?php $l->block("baz"); ?>bar<?php $l->end(); ?>\');
				$l->extending($base_src);
					$l->block("baz");
						echo "biz";
						$l->super();
					$l->end();
				$l->done();
			?>');

		$l->extending($second_src);
			$l->block("baz");
				$l->super();
				echo "baz";
			$l->end();
		$l->done();

		$this->assertEquals("foobizbarbaz", ob_get_clean());
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testStrayEndError() {
		$l = new Litter;
		$l->end();
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testStrayDoneError() {
		$l = new Litter;
		$l->done();
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testNestedBlocksWhenExtendingError() {
		$l = new Litter;
		$l->extending(Litter::stringSource(""))->block("parent")->block("child");
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testDoneWhenOpenBlockError() {
		$l = new Litter;
		$l->extending(Litter::stringSource(""))->block("parent")->done();
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testExtendWhenExtendingError() {
		$l = new Litter;
		$l->extending(Litter::stringSource(""))->extending(Litter::stringSource(""));
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testExtendWithinBlockError() {
		$l = new Litter;
		$l->block("dummy")->extending(Litter::stringSource(""));
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testMultipleSuperWithinBlockError() {
		$l = new Litter;
		$l->extending(Litter::stringSource(""))->block("dummy")->super()->super();
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testBaseTemplateSuperError() {
		$l = new Litter;
		$l->block("dummy")->super();
	}
	/**
	 *	@group   templating
	 *	@depends testStringSource
	 *	@expectedException Litter\Exception
	 */
	function testStraySuperError() {
		$l = new Litter;
		$l->super();
	}

	function provider() {
		return new Litter(5);
	}
}
