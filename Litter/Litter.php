<?php
namespace Litter;
/**
 *	A Litter object provides conveinient ways of iterating, traversing and accessing
 *	underlying objects. It's intended use is a full fledged template engine. Note that
 *	no methods edit the value in place.
 */
class Litter implements \Iterator, \Countable, \ArrayAccess {
	private
		/** Holds original creation time value */
		$val,

		/** Iterator if available for data type of $this->val */
		$iterator,

		/** Array of type to Iterator class mappings */
		$iterators,

		/** Text encoding for this instance. Children inherit this */
		$encoding,

		/** Current instance's parent instance. Useful for knowing current loop index */
		$parent,

		/** Naive current index. Resets on ->rewind(), increases on ->next() */
		$index = 0,

		/** Remembers which source file we're currently extending, for template inheritance */
		$extending = NULL,

		/** Keeps track of currently open blocks. Used for template inheritance */
		$current_blocks = array(),

		/** Rembembers what each block outputted, when extending. Used for template inheritance */
		$block_data = array();

	/** Type to Iterator class mapping. This is inherited to objects spawned from this one */
	static $iterator_map = array(
		"array"    => "\ArrayIterator",
		"integer"  => "Litter\IntegerIterator",
		"string"   => "Litter\StringIterator",
		"stdClass" => "\ArrayIterator");

	/**
	 *	Constructs a new Litter object. $value is the value to operate on. $iterators
	 *	is a list of type to iterator mappings, with the default list being
	 *	self::$iterator_map. $encoding specifies expected input and output encoding.
	 *	$parent is for internal use.
	 */
	function __construct($value = NULL, $iterators = NULL, $encoding = "UTF-8", $parent = NULL) {
		if ($iterators === NULL) {
			$this->iterators = self::$iterator_map;
		} else {
			$this->iterators = (array)$iterators;
		}
		$this->encoding = $encoding;
		$this->parent   = $parent;

		$this->val = $value;
		if ($value instanceof Traversable) {
			$this->iterator = new IteratorIterator($value);
		} elseif (is_string($value)) {
			$iter = $this->iterators["string"];
			$this->iterator = new $iter($value, $this->encoding);
		} elseif (is_int($value)) {
			$iter = $this->iterators["integer"];
			$this->iterator = new $iter($value);
		} elseif (is_array($value)) {
			$iter = $this->iterators["array"];
			$this->iterator = new $iter($value);
		} else {
			foreach ($this->iterators as $obj => $iter) {
				if ($value instanceof $obj) {
					$this->iterator = new $iter($value);
				}
			}
		}
	}
	/**
	 *	Create a new instance with $value as value and $parent as parent
	 */
	private function _asSelf($value, $parent = NULL) {
		return new self($value, $this->iterators, $this->encoding, $parent);
	}

	/**
	 *	Same as ->_asSelf with $parent = NULL
	 */
	function __clone() {
		return $this->_asSelf($this->val);
	}

	/**
	 *	Current zerobound iteration index
	 */
	function index() {
		return $this->index;
	}

	/**
	 *	When inside a loop this cycles between items in $cycle
	 *
	 *	Example
	 *		foreach (new Litter("123") as $c) {
	 *			echo $c->cycle(array("a", "b")), ":$c ";
	 *		}
	 *		// Result: "a:1 b:2 a:3"
	 *
	 */
	function cycle(array $cycle) {
		if (!isset($this->parent)) {
			var_dump($this);
			throw new Exception("Nothing to cycle");
		}
		return $this->_asSelf($cycle[$this->parent->index % count($cycle)], $this->parent);
	}

	// String manipulation
	/**
	 *	Treat current value as string and uppercase it
	 */
	function upper() {
		return $this->_asSelf(mb_convert_case($this->val, MB_CASE_UPPER, $this->encoding));
	}
	/**
	 *	Treat current value as string and lowercase it
	 */
	function lower() {
		return $this->_asSelf(mb_convert_case($this->val, MB_CASE_LOWER, $this->encoding));
	}
	/**
	 *	Replace occurences of keys with values
	 */
	function replace(array $map) {
		return $this->_asSelf(strtr($this->val, $map));
	}

	/**
	 *	Print $this->val as HTML escaped string
	 */
	function __toString() {
		return htmlentities($this->val, NULL, $this->encoding);
	}
	/**
	 *	Raw value of $this->val
	 */
	function raw() {
		return $this->val;
	}

	/**
	 *	Return new instance of Litter with current iterator wrapped in a LimitIterator
	 */
	function slice($offset = 0, $count = -1) {
		return $this->_asSelf(new LimitIterator($this, $offset, $count), $this->parent);
	}

	// Block inheritance
	/**
	 *	Takes a PHP file to extend. All blocks defined within this
	 */
	function extending($src) {
		// Start output buffering to discard whitespace later
		ob_start();

		if ($this->current_blocks) {
			throw new Exception("Can't extend within a block. This must be top level");
		} elseif ($this->extending) {
			throw new Exception(
				"I heard you like extending so we.. No we didn't. " .
				"You can't extend while extending");
		}
		$this->extending = $src;

		return $this;
	}
	/**
	 *	Marks that we're done extending on this level. This triggers the inclusion
	 *	of the parent file we're extending.
	 */
	function done() {
		// Discard current output buffer
		ob_get_clean();

		if (!$this->extending) {
			throw new Exception(
				"There is nothing we're extending right now. Did you call ->extending(\$src)?");
		} elseif ($this->current_blocks) {
			throw new Exception(sprintf(
				"You're not done extending, blocks (%s) are still open",
				join(", ", $this->current_blocks)));
		}
		$src = $this->extending;
		$this->extending = NULL;

		// Make $this available as $l for included file
		$l = $this;
		require $src;

		return $this;
	}
	/**
	 *	Declares the start of a block. This turns on output buffering and captures
	 *	until ->end() is called. Blocks can only be nested in the topmost template.
	 */
	function block($name) {
		// Start buffering upcoming output
		ob_start();

		// Track this block
		$this->current_blocks[] = $name;

		return $this;
	}
	/**
	 *	Stops capturing the current block
	 */
	function end() {
		if (!$this->current_blocks) {
			throw new Exception("Can't end block since none are open");
		}

		// Get current block
		$block = array_pop($this->current_blocks);

		if ($this->current_blocks && $this->extending) {
			throw new Exception("Blocks can't be nested when extending");
		}

		if ($this->extending) {
			// If we haven't set this block higher up in the inheritance tree we'll do it now
			if (!isset($this->block_data)) {
				$this->block_data[$block] = ob_get_clean();
			// We still want to save this if super has been called once for this block
			} elseif (count($this->block_data[$block]) === 1) {
				$this->block_data[$block][] = ob_get_clean();
			}
		} else {
			// If a block was set on a higher level we want to use it
			if (isset($this->block_data[$block])) {
				// Get current output buffer
				$current_block = ob_get_clean();
				if (is_array($this->block_data[$block])) {
					echo join($current_block, $this->block_data[$block]);
				} else {
					echo $this->block_data[$block];
				}
			} else {
				// Here we know we're in the base template so flushing is safe
				ob_end_flush();
			}
		}
		return $this;
	}
	/**
	 *	This is a placeholder for the parent block's output. This can only be called
	 *	in an extending block
	 */
	function super() {
		if (!$this->current_blocks) {
			throw new Exception("Super must be called within a block");
		} elseif (!$this->extending) {
			throw new Exception("Super can't be called when not extending");
		} elseif (
			isset($this->block_data[end($this->current_blocks)]) &&
			count($this->block_data[end($this->current_blocks)]) > 1)
		{
			throw new Exception("Super can only be called once per block");
		}
		// Turn the buffering into an array
		$this->block_data[end($this->current_blocks)] = array(ob_get_clean());

		// Restart output buffering
		ob_start();

		return $this;
	}

	// Countable
	function count() {
		return $this->iterator->count();
	}

	// Implement iterator properties
	function rewind() {
		$this->index = 0;

		if (!isset($this->iterator)) {
			throw new Exception(sprintf(
				"Can't iterate current value, type is intraversable (%s)",
				is_object($this->iterator) ? get_class($this->iterator) : gettype($this->iterator)));
		}

		$this->iterator->rewind();
	}
	function current() {
		return $this->_asSelf($this->iterator->current(), $this);
	}
	function key() {
		return $this->iterator->key();
	}
	function next() {
		$this->index++;
		return $this->_asSelf($this->iterator->next(), $this);
	}
	function valid() {
		return $this->iterator->valid();
	}

	// Implement ArrayAccess
	function offsetGet($offset) {
		if (is_array($this->val)) {
			return new self($this->val[$offset], $this->iterators);
		} elseif (is_object($this->val)) {
			return new self($this->val->{$offset}, $this->iterators);
		} elseif (is_string($this->val)) {
			return new self(mb_substr($this->val, $offset, 1, $this->encoding), $this->iterators);
		}
		throw new NotImplementedException;
	}
	function offsetExists($offset) {
		throw new NotImplementedException;
	}
    function offsetSet($offset, $value) {
	    throw new ReadOnlyException;
	}
    function offsetUnset($offset) {
	    throw new ReadOnlyException;
	}

	// Magic methods
	function __get($k) {
		return $this->offsetGet($k);
	}
	function __set($k, $v) {
		return $this->offsetSet($k);
	}
	function __isset($k) {
		return $this->offsetExists($k);
	}
	function __unset($k) {
		return $this->offsetUnset($k);
	}
}
