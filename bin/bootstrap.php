<?php
// This file is fake boostrap for our scripts.
// It searches for real bootstrap in /app directory

if(!isset($bootstrapSearchPath))
	$bootstrapSearchPath = array();

// Path from ENV variable
if(isset($_SERVER['APP_DIR']))
	$bootstrapSearchPath[] = $_SERVER['APP_DIR'] . DIRECTORY_SEPARATOR . 'bootstrap.php';

// Default search path

// Note: we don't want to use __DIR__ because composer automatically creates
// symlinks for scripts (__DIR__ returns actual path).
$bootstrapSearchPath[] =
	dirname(getcwd() . DIRECTORY_SEPARATOR . $argv[0])
	. DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'app'
	. DIRECTORY_SEPARATOR . 'bootstrap.php';

foreach($bootstrapSearchPath as $path) {
	if(file_exists($path)) {
		unset($bootstrapSearchPath);
		return require $path;
	}
}

echo "\nBootstrap not found\n";
echo "Search paths:\n";
echo "\t" . implode($bootstrapSearchPath, "\n\t") . "\n";
echo "\n";

exit(1);
