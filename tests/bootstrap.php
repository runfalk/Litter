<?php
spl_autoload_register(function($class) {

	$file = dirname(__FILE__)."/../" . str_replace("\\", "/", $class) . ".php";
	if (file_exists($file)) {
		require $file;
	}
});
