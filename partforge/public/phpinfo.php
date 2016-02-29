<?php

function show_message($msg,$ok = false) {
	echo '<p style="'.($ok ? 'color: #000;' : 'color: #F00;').'">'.$msg.'</p>';
}

function php_ini_error($ini_param, $msg) {
	$value = ini_get($ini_param);
	show_message("PHP Configuration Setting '{$ini_param}' = '{$value}' is incorrect.  {$msg}");
}

function check_extension($ext) {
	if (!extension_loaded($ext)) {
		show_message("The PHP extension '{$ext}' is required, but not loaded.");
		return false;
	}
	return true;
}

$error = false;

if (!ini_get('date.timezone')) {
	php_ini_error('date.timezone','It must be set to something.');
	$error = true;
}
if (intval(ini_get('memory_limit'))<256) {
	php_ini_error('memory_limit','It is recommended to be at least 256M.');
	$error = true;
}
if (intval(ini_get('post_max_size'))<40) {
	php_ini_error('post_max_size','It is recommended to be at least 40M.');
	$error = true;
}

$minversion = '5.2.9';
$toolargeversion = '6.0.0';
if (version_compare(PHP_VERSION, $minversion) < 0) {
	show_message("The PHP version (".PHP_VERSION.") is too low.  PartForge has only been tested to versions less than ".$toolargeversion." but at least version ".$minversion."." );
	$error = true;
}
if (version_compare(PHP_VERSION, $toolargeversion) >= 0) {
	show_message("The PHP version (".PHP_VERSION.") is too high.  PartForge has only been tested to versions less than ".$toolargeversion." but at least version ".$minversion."." );
	$error = true;
}

// check extensions.

if (!check_extension('curl')) $error = true;
if (!check_extension('gd')) $error = true;   // supposedly 
if (!check_extension('tidy')) $error = true;
if (!check_extension('mysql')) $error = true;
if (!check_extension('json')) $error = true;

if (!$error) {
	show_message('PHP Appears to be configured correctly.',true);
}

phpinfo();