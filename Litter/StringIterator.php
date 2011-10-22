<?php
namespace Litter;

/**
 *	Iterate each individual character of a string respecting $encoding
 */
class StringIterator implements \Iterator, \Countable {
	private $str, $i = 0, $encoding;
	function __construct($str, $encoding = "auto") {
		if (!is_string($str)) {
			throw new \InvalidArgumentException("\$str is not string");
		}
		if (!in_array($encoding, mb_list_encodings())) {
			throw new \InvalidArgumentException("$encoding is not a known encoding");
		}
		$this->str = $str;


		$this->encoding = $encoding;
	}

	function count() {
		return mb_strlen($this->str, $this->encoding);
	}

	function rewind() {
		$this->i = 0;
	}
	function current() {
		return mb_substr($this->str, $this->i, 1, $this->encoding);
	}
	function key() {
		return $this->i;
	}
	function next() {
		return mb_substr($this->str, $this->i++, 1, $this->encoding);
	}
	function valid() {
		return $this->i < $this->count();
	}
}
