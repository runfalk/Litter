<?php
namespace Litter;

/**
 *	Take an iterator and group it into smaller iterators to create things like
 *	a 3x3 grid
 */
class GroupIterator implements \Iterator, \Countable {
	private $iter, $size, $i;
	function __construct($iter, $size) {
		$this->iter = $iter;
		$this->size = $size;
	}

	function count() {
		return $this->iter->count();
	}

	function rewind() {
		$this->i = 0;
	}
	function current() {
		return new LimitIterator($this->iter, $this->i * $this->size, $this->size);
	}
	function key() {
		return $this->i;
	}
	function next() {
		$iter = $this->current();
		$this->i++;
		return $iter;
	}
	function valid() {
		return $this->i * $this->size < $this->count();
	}
}
