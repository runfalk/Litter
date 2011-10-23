<?php
namespace Litter;
/**
 *	A Litter object provides conveinient ways of iterating, traversing and accessing
 *	underlying objects. It's intended use is a full fledged template engine. Note that
 *	no methods edit the value in place.
 */
class Litter implements \OuterIterator, \Countable, \ArrayAccess {
	private
		/** Holds original creation time value */
		$value,

		/** Iterator if available for data type of $this->value */
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

		/** Keep track of which level of extending that's currently active, for template inheritance */
		$extending_level = 0,

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
		$this->value    = $value;
	}
	/**
	 *	Create a new instance with $value as value and $parent as parent
	 */
	private function _asSelf($value, $parent = NULL) {
		return new self(
			$value, $this->iterators, $this->encoding, $parent === NULL ? $this->parent : $parent);
	}

	/**
	 *	Same as ->_asSelf with $parent = NULL
	 */
	function __clone() {
		return $this->_asSelf($this->value);
	}

	/**
	 *	Print $this->value as HTML escaped string
	 */
	function __toString() {
		return $this->encode("HTML-ENTITIES")->raw();
	}

	/**
	 *	Current onebound iteration index unless argument is true
	 */
	function index($zerobound = FALSE) {
		if (!isset($this->parent)) {
			throw new Exception("Not currently iterating");
		}
		return $this->_asSelf($this->parent->index + (1 - (int)(bool)$zerobound));
	}
	/**
	 *	Alias for ->count()
	 */
	function length() {
		return $this->count();
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
			throw new Exception("Nothing to cycle");
		}
		return $this->_asSelf($cycle[$this->parent->index % count($cycle)], $this->parent);
	}

	// String manipulation
	/**
	 *
	 */
	function encode($encoding) {
		if (!in_array($encoding, mb_list_encodings())) {
			throw new \InvalidArgumentException("$encoding is not a known encoding");
		}
		list($internal_encoding, $val) = $this->encoding == "HTML-ENTITIES" ?
			array("UTF-8", html_entity_decode($this->value, ENT_COMPAT, "UTF-8")) :
			array($this->encoding, $this->value);

		$reencoded = $this->_asSelf($encoding == "HTML-ENTITIES" ?
			htmlentities($val, ENT_COMPAT, $internal_encoding) :
			mb_convert_encoding($val, $encoding, $internal_encoding));
		$reencoded->encoding = $encoding;
		return $reencoded;
	}
	/**
	 *	Treat current value as string and uppercase it
	 */
	function upper() {
		return $this->_asSelf(mb_convert_case($this->value, MB_CASE_UPPER, $this->encoding));
	}
	/**
	 *	Treat current value as string and lowercase it
	 */
	function lower() {
		return $this->_asSelf(mb_convert_case($this->value, MB_CASE_LOWER, $this->encoding));
	}
	/**
	 *	Replace occurences of keys with values
	 */
	function replace(array $map) {
		return $this->_asSelf(strtr($this->value, $map));
	}
	/**
	 *	Truncate string to specified $length respecting encoding. If $append is
	 *	specified that string will be appended to the end of the string if
	 *	truncation is needed. If that is the case the resulting string will be
	 *	$length characters long even with $append
	 */
	function truncate($length, $append = "") {
		if (mb_strlen($this->value) <= $length) {
			$out = $this->value;
		} else {
			$out = mb_substr($this->value, 0, $length - mb_strlen($append)) . $append;
		}
		return $this->_asSelf($out);
	}

	/**
	 *	Treat current value as an array and join them
	 */
	function join($delimiter) {
		return $this->_asSelf(join($delimiter, iterator_to_array($this, FALSE)));
	}

	/**
	 *	Raw value of $this->value
	 */
	function raw() {
		return $this->value;
	}

	/**
	 *	Return new instance of Litter with current iterator wrapped in a LimitIterator
	 */
	function slice($offset = 0, $count = -1) {
		return $this->_asSelf(new \LimitIterator($this, $offset, $count), $this->parent);
	}

	/**
	 *	Produce an iterator of self that gives a new iteretor with the length of
	 *	$size each time it's iterated
	 */
	function group($size) {
		return $this->_asSelf(new GroupIterator($this, $size));
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
		$this->extending_level++;
		$l = $this;
		require $src;
		$this->extending_level--;

		return $this;
	}
	/**
	 *	Declares the start of a block. This turns on output buffering and captures
	 *	until ->end() is called. Blocks can only be nested in the topmost template.
	 */
	function block($name) {
		if ($this->current_blocks && $this->extending) {
			throw new Exception("Blocks can't be nested when extending");
		}

		// Start buffering upcoming output
		ob_start();

		// Track this block
		$this->current_blocks[] = $name;

		// Make sure there's an array to save block output to
		if (!isset($this->block_data[$name])) {
			$this->block_data[$name] = array();
		}

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

		if ($this->extending) {
			// If we haven't set this block higher up in the inheritance tree we'll do it now
			if (!isset($this->block_data[$block][$this->extending_level])) {
				$this->block_data[$block][$this->extending_level] = ob_get_clean();
			// We still want to save this if super has been called once for this block
			} elseif (
				is_array($this->block_data[$block]) &&
				is_array($this->block_data[$block][$this->extending_level]) &&
				count($this->block_data[$block][$this->extending_level]) === 1)
			{
				$this->block_data[$block][$this->extending_level][] = ob_get_clean();
			}
		} else {
			// Get current output buffer and save in our block data array
			$this->block_data[$block][$this->extending_level] = ob_get_clean();

			// Are parents relevant at all? (Did topmost block call super()?)
			if (is_array(reset($this->block_data[$block]))) {
				// Fill $out with a "fake" extending child with no content of its own
				$out = array("", "");

				foreach ($this->block_data[$block] as $saved_block) {
					// Does the current saved block care about its parents?
					if (!is_array($saved_block)) {
						// Here $saved_block is string which means further iteration
						// is pointless, hence we echo what we've got and break the loop
						echo join($saved_block, $out);
						break;
					} else {
						// Merge parent block to current
						$out[0] .= $saved_block[0];
						$out[1] = $saved_block[1] . $out[1];
					}
				}
			} else {
				echo reset($this->block_data[$block]);
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
		}

		$block = end($this->current_blocks);

		if (
			isset($this->block_data[$block][$this->extending_level]) &&
			is_array($this->block_data[$block][$this->extending_level]) &&
			count($this->block_data[$block][$this->extending_level]))
		{
			throw new Exception("Super can only be called once per block");
		}
		// Turn the buffering into an array
		$this->block_data[$block][$this->extending_level] = array(ob_get_clean());

		// Restart output buffering
		ob_start();

		return $this;
	}

	/**
	 *	Converts $php_string to a string suitable for direct use with ->extending()
	 */
	static function stringSource($php_string) {
		return "data:text/plain;base64," . base64_encode($php_string);
	}

	// Implement outer iterator properties
	/**
	 *	Try to make an iterator out of current value
	 */
	function getInnerIterator() {
		// Is iterator not yet gotten?
		if (!isset($this->iterator)) {
			if ($this->value instanceof \Iterator) {
				$this->iterator = $this->value;
			} elseif ($this->value instanceof \Traversable) {
				$this->iterator = new \IteratorIterator($this->value);
			} elseif (is_string($this->value)) {
				$iter = $this->iterators["string"];
				$this->iterator = new $iter($this->value, $this->encoding);
			} elseif (is_int($this->value)) {
				$iter = $this->iterators["integer"];
				$this->iterator = new $iter($this->value);
			} elseif (is_array($this->value)) {
				$iter = $this->iterators["array"];
				$this->iterator = new $iter($this->value);
			} else {
				foreach ($this->iterators as $obj => $iter) {
					if ($this->value instanceof $obj) {
						$this->iterator = new $iter($this->value);
					}
				}
			}

			// If $this->iterator is still not set value is considered intraversable
			if (!isset($this->iterator)) {
				throw new Exception(sprintf(
					"Can't iterate current value, type is intraversable (%s)",
					is_object($this->value) ? get_class($this->value) : gettype($this->value)));
			}
		}

		return $this->iterator;
	}

	// Countable
	function count() {
		return $this->getInnerIterator()->count();
	}

	// Implement iterator properties
	function rewind() {
		$this->index = 0;
		$this->getInnerIterator()->rewind();
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
		if (is_array($this->value)) {
			return $this->_asSelf($this->value[$offset]);
		} elseif (is_object($this->value)) {
			return $this->_asSelf($this->value->{$offset});
		} elseif (is_string($this->value)) {
			if (!is_int($offset)) {
				throw new \InvalidArgumentException(
					"Non integer offsets are not supported for string values");
			}
			return $this->_asSelf(mb_substr($this->value, $offset, 1, $this->encoding));
		}
		throw new NotImplementedException(sprintf(
			"Getting by offset is not supported for the current type of value (%s)",
			is_object($this->value) ? get_class($this->value) : gettype($this->value)));
	}
	function offsetExists($offset) {
		if (is_array($this->value)) {
			return isset($this->value[$offset]);
		} elseif (is_object($this->value)) {
			return isset($this->value->{$offset});
		} elseif (is_string($this->value)) {
			if (!is_int($offset)) {
				throw new \InvalidArgumentException(
					"Non integer offsets are not supported for string values");
			}
			return $offset < $this->count();
		}
		throw new NotImplementedException(sprintf(
			"isset() is not supported for the current type of value (%s)",
			is_object($this->value) ? get_class($this->value) : gettype($this->value)));
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
		return $this->offsetSet($k, $v);
	}
	function __isset($k) {
		return $this->offsetExists($k);
	}
	function __unset($k) {
		return $this->offsetUnset($k);
	}
}
