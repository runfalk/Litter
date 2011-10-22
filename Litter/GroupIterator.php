<?php
namespace Litter;

/**
 *	Take an iterator and group it into smaller iterators to create things like
 *	a 3x3 grid
 */
class GroupIterator implements \Iterator, \Countable {
	private $iter, $size, $i;
	function __construct($iter, $size) {
		if (!$iter instanceof \Traversable && !$iter instanceof \Iterator) {
			throw new \InvalidArgumentException("\$iter must be a valid iterator, remember that arrays aren't");
		} elseif (!is_int($size)) {
			throw new \InvalidArgumentException("\$size must be integer");
		}

		$this->iter = $iter;
		$this->size = $size;
	}

	function count() {
		return ceil($this->iter->count() / $this->size);
	}

	function rewind() {
		$this->i = 0;
	}
	function current() {
		return new \LimitIterator($this->iter, $this->i * $this->size, $this->size);
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
		return $this->i * $this->size < $this->iter->count();
	}
}
