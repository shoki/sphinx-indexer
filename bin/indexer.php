#!/usr/bin/env php
<?php
ini_set('include_path', dirname(dirname(__FILE__)).'/lib:/usr/lib/sphinxIndexer:.');

function __autoload($class_name) {
    require_once $class_name . '.php';
}

$si = new Sphinx_Indexer();
$si->run(array_slice($argv,1));	/* skip argv[0] */


?>
