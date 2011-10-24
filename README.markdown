Litter
======
Litter is a simple yet powerful templating aid. Its aim is to let PHP be the templating language it was designed to be. It output escapes strings for direct use in HTML and provides other useful utility functions, such as uppercasing, replacing, etc. It provides a powerful way of iterating things. Anything that makes sense is iterable when it comes to Litter. Check out the unit tests or examples to see it in action.

To allow code reuse in templates Litter supports template inheritance similar to that in Jinja2 and Django.

Requirements
------------
Litter requires PHP >= 5.3.0 and is actively tested with 5.3.3.

Installation
------------
Installation is preferably done through PEAR.

	pear channel-discover pear.runfalk.se
	pear install runfalk/Litter-alpha

Remember that this is alpha software and is expected to be unstable. The API may change.

After being installed including using the autoloader is the easiest way.

```php
<?php require_once "Litter/Autoloader.php"; ?>
```

Tests
-----
Litter is covered by a [PHPUnit](https://github.com/sebastianbergmann/phpunit) test suite.

Examples
--------
### String
Strings are one of many iterable things when it comes to Litter.

```php
<ul>
<?php foreach (new Litter("foo") as $c): ?>
	<li><?=$c?></li>
<?php endforeach; ?>
</ul>
```

The output is the list

*	f
*	o
*	o

Litter will always try to treat the value as an iterator. Note that $c in the loop is a new Litter object containing a single character, but is cast into string and therefore HTML escaped. All methods but ->raw() returns its value encapsulated in a new Litter object.

### Basic inheritance
*	base.php

	```php
	foo<?php $l->block("baz"); ?>bar<?php $l->end(); ?>
	```

*	template.php

	```php
	<?php $l = new Litter; ?>
	<?php $l->extending("base.php"); ?>
		<?php $l->block("baz"); ?>buz<?php $l->end(); ?>
	<?php $l->done(); ?>
	```

The output in this case will be `foobuz` since the extending _template.php_ overrides the block _baz_ in _base.php_. Note that when a template is extended the Litter object is always available as `$l` and it has no access to the surrounding scope.

### Basic inheritance with parent
*	base.php

	```php
	foo<?php $l->block("baz"); ?>bar<?php $l->end(); ?>
	```

*	template.php

	```php
	<?php $l = new Litter; ?>
	<?php $l->extending("base.php"); ?>
		<?php $l->block("baz"); ?>biz<?php $l->super(); ?>buz<?php $l->end(); ?>
	<?php $l->done(); ?>
	```

The output in this case will be `foobizbarbuz` since the extending _template.php_ overrides the block _baz_ in _base.php_ but includes its value inbetween _biz_ and _buz_.
