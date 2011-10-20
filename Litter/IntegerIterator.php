<?php
namespace Litter;

/**
 *	Iterates until it reaces the number specified in constructor
 */
class IntegerIterator implements \Iterator, \Countable {
	private
		/** Limit */
		$n,

		/** Current index */
		$i = 0;

	/**
	 *	Create iterator that iterates $n times
	 */
	function __construct($n) {
		if (!is_int($n)) {
			new InvalidArgumentException(sprintf("\$n must be integer %s given", gettype($n)));
		}
		$this->n = $n;
	}

	function count() {
		return $this->n;
	}

	function rewind() {
		$this->i = 0;
	}
	function current() {
		return $this->i;
	}
	function key() {
		return $this->i;
	}
	function next() {
		return $this->i++;
	}
	function valid() {
		return $this->i < $this->n;
	}
}
