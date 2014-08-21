<?php

use vBuilder\Utils\CliArgsParser,
	vBuilder\Utils\FileSystem;

$container = require __DIR__ . '/bootstrap.php';

// -----------------------------------------------------------------------------
// ARGUMENTS
// -----------------------------------------------------------------------------

$args = new CliArgsParser();
$args
	->addSwitch('purge', 'Purge all data')
	->addSwitch('data', 'Setup with data')
	->addSwitch('help', 'Help');

if(!$args->parse() || $args->get('help')) {
	if($args->get('help')) echo "\n";
	else echo "\n" . $args->getErrorMsg() . "\n\n";
	$args->printUsage();
	echo "\n";
	exit;
}

// -----------------------------------------------------------------------------
// INIT
// -----------------------------------------------------------------------------

$tm = $container->getByType('vBuilder\\Database\\TableManager');
$db = $container->getByType('DibiConnection');
$workPath = realpath(getcwd());

// Gather all explicitly required tables
$requiredTables = $tm->getTables();

// CMS: Gather all redaction types and add their tables
if($container->hasService('redaction')) {
	foreach($container->redaction->documentTypes as $class) {
		$table = $class::getMetadata()->getTableName();

		if(!in_array($table, $requiredTables))
			$requiredTables[] = $table;
	}
}

echo "\n";

// Find associated table scripts
$tables = array();
foreach($requiredTables as $table) {
	$tables[$table] = $tm->getDdlScript($table);

	if(!file_exists($tables[$table])) {
		echo "Error: ";
		echo "SQL script for table $table does not exist. Expected path: " . $tables[$table] . ".";
		echo "\n\n";
		exit(1);
	}
}

// -----------------------------------------------------------------------------
// DB
// -----------------------------------------------------------------------------

$existingTables = $db->getDatabaseInfo()->getTableNames();

foreach($tables as $table => $script) {
	echo "\033[1;36m$table:\033[0m ";

	// Check for data script
	if(!$args->get('data') || !($dataScript = $tm->getDataScript($table)) || !file_exists($dataScript)) {
		$dataScript = FALSE;
	}

	if(in_array($table, $existingTables)) {
		echo "exists";

		// TODO: some table syntax check
		/* $r = $db->query("SHOW CREATE TABLE %n", $table)->fetch();
		if($r) {
			$createSyntax = $r->{'Create Table'};
			d($createSyntax);
		} else
			throw new Nette\InvalidStateException("Cannot gather create table syntax for: $table"); */

		// Purge all data if requested
		if($args->get('purge')) {
			echo ", purging all data";
			$db->query('TRUNCATE TABLE %n', $table);
		}

		// Check if any data exists otherwise
		elseif($dataScript) {
			if($db->query('SELECT COUNT(*) FROM %n', $table)->fetchSingle() > 0) {
				echo ", skipping data (table not empty)";
				$dataScript = FALSE;
			}
		}
	}

	else {
		echo "creating from \033[0;36m";
		echo FileSystem::getRelativePath($workPath, $script);
		echo "\033[0m";

		$db->loadFile($script);
	}

	if($dataScript) {
		echo ", importing data from \033[0;36m";
		echo FileSystem::getRelativePath($workPath, $dataScript);
		echo "\033[0m";

		$db->loadFile($dataScript);
	}

	echo "\n";
}

echo "\nDone :-)\n\n";


