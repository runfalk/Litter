<?php
// Be sure to include this with require_once or we'll have multiple things
// autoloaders loitering about
$dir = dirname(__FILE__) . "/..";
spl_autoload_register(function($class) use ($dir) {
	$path = explode("\\", $class);
	$file = "$dir/" . join("/", $path) . ".php";
	if ($path[0] != "Litter") {
		return;
	} elseif (file_exists($file)) {
		require $file;
	}
});
