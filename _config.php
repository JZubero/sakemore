<?php
/**
 * Adds the current working directory to the list.
 */
global $_FILE_TO_URL_MAPPING;
$basepath = dirname(getcwd());
$_FILE_TO_URL_MAPPING[$basepath] = 'http://localhost';
if (!array_key_exists('HTTP_HOST', $_SERVER)) {
  $_SERVER['HTTP_HOST'] = 'localhost';
}

// Ensure compatibility with PHP 7.2 ("object" is a reserved word),
// with SilverStripe 3.6 (using Object) and SilverStripe 3.7 (using SS_Object)
// https://docs.silverstripe.org/en/3/changelogs/3.7.0/#for-module-authors
if (!class_exists('SS_Object')) class_alias('Object', 'SS_Object');