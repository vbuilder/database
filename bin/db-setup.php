<?php

// -----------------------------------------------------------------------------
// INIT
// -----------------------------------------------------------------------------

$container = require __DIR__ . '/bootstrap.php';
$tm = $container->getByType('vBuilder\\Database\\TableManager');
$db = $container->getByType('DibiConnection');

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

	if(in_array($table, $existingTables)) {
		echo "exists";

		// TODO: some table syntax check
		/* $r = $db->query("SHOW CREATE TABLE %n", $table)->fetch();
		if($r) {
			$createSyntax = $r->{'Create Table'};
			d($createSyntax);
		} else
			throw new Nette\InvalidStateException("Cannot gather create table syntax for: $table"); */
	}

	else {
		echo "creating from \033[0;36m";
		if(Nette\Utils\Strings::startsWith($script, __DIR__ . '/../')) echo mb_substr($script, mb_strlen(__DIR__ . '/../'));
		else echo $script;
		echo "\033[0m";

		$db->loadFile($script);
	}

	echo "\n";
}

echo "\nDone :-)\n\n";


