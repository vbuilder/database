<?php
use vBuilder\Utils\CliArgsParser,
	vBuilder\Utils\FileSystem;

// -----------------------------------------------------------------------------
// INIT
// -----------------------------------------------------------------------------

$container = require __DIR__ . '/bootstrap.php';
$tm = $container->getByType('vBuilder\\Database\\TableManager');
$db = $container->getByType('DibiConnection');

$workPath = realpath(getcwd());

// -----------------------------------------------------------------------------
// ARGUMENTS
// -----------------------------------------------------------------------------

$args = new CliArgsParser();
$args
	->addSwitch('data', 'Dump data')
	->addSwitch('help', 'Help');

if(!$args->parse() || $args->get('help')) {
	if($args->get('help')) echo "\n";
	else echo "\n" . $args->getErrorMsg() . "\n\n";
	$args->printUsage();
	echo "\n";
	exit;
}


// -----------------------------------------------------------------------------
// ROUTINES
// -----------------------------------------------------------------------------

/**
 * @param DibiConnection
 * @param string table name
 * @param string file path
 *
 * @return int
 * @throws DibiException
 * @throws Nette\IOException
 * @throws Nette\UnexpectedValueException
 */
function dumpStructure(DibiConnection $db, $table, $file) {

	// Return code
	// 0: Not changed
	// 1: Created
	// 2: Updated
	$result = 0;

	$row = $db->query("SHOW CREATE TABLE %n", $table)->fetch();
	if(!$row) throw new Nette\UnexpectedValueException("Cannot gather create table syntax for: $table");

	if(isset($row->{'Create Table'})) {
		$createSyntax = $row->{'Create Table'};
		$createSyntax = preg_replace("/\\sAUTO_INCREMENT=([0-9]+)\\s/", " ", $createSyntax);

	} elseif(isset($row->{'Create View'})) {
		$createSyntax = $row->{'Create View'};
		$createSyntax = preg_replace("/\\sDEFINER=(\\S+)\\s/", " ", $createSyntax);
	} else {
		throw new Nette\InvalidStateException('Expected table or view');
	}

	// Check for existing SQL script
	if(file_exists($file)) {
		$lines = file($file);
		if($lines === FALSE)
			throw new Nette\IOException("Cannot read file $file");

		$oldCreateSyntax = "";
		foreach($lines as $line) {
			if(!Nette\Utils\Strings::startsWith($line, '--')) {
				$oldCreateSyntax .= "$line\n";
			}
		}

		$new = rtrim(trim(preg_replace('/[\s\r\n]+/', ' ', $createSyntax)), ';');
		$old = rtrim(trim(preg_replace('/[\s\r\n]+/', ' ', $oldCreateSyntax)), ';');

		$result = ($old != $new) ? 2 : 0;

	// No existing SQL script
	} else {
		$result = 1;
	}

	// ----

	// If we need to create new dump
	if($result) {
		$createSyntax = "" .
			"--\n" .
			"-- Create table: $table\n" .
			"-- Generated: " . date("Y-m-d H:i:s") ."\n" .
			"--\n" . $createSyntax . ";";

		if(file_put_contents($file, $createSyntax) === FALSE)
			throw new Nette\IOException("Cannot write to file '$file'");
	}

	return $result;
}

/**
 * @param DibiConnection
 * @param string table name
 * @param string file path
 *
 * @return int
 * @throws DibiException
 * @throws Nette\IOException
 */
function dumpData(DibiConnection $db, $table, $file) {
	$rows = $db->query("SELECT * FROM %n", $table)->fetchAll();
	if(count($rows) == 0) {
		if(file_exists($file) && !unlink($file))
			throw new Nette\IOException("Failed to delete $file");

		return ;
	}

	$sql = "" .
			"--\n" .
			"-- Data for table: $table\n" .
			"-- Generated: " . date("Y-m-d H:i:s") ."\n" .
			"--\n" .
			"START TRANSACTION;\n";

	$perCommand = 10;
	for($i = 0; $i < ceil(count($rows) / $perCommand); $i++) {
		$slice = array_slice($rows, $i * $perCommand, $perCommand);
		$sql .= $db->translate("INSERT INTO %n %ex", $table, $slice) . ";\n";
	}

	$sql .= "COMMIT;\n";

	if(file_put_contents($file, $sql) === FALSE)
		throw new Nette\IOException("Cannot write to file '$file'");
}

// -----------------------------------------------------------------------------
// DB
// -----------------------------------------------------------------------------

$existingTables = $db->getDatabaseInfo()->getTableNames();

foreach($existingTables as $table) {

	// Get script file paths
	$dumpPaths = array(
		'structure' => $tm->getDdlScript($table),
		'data' => $tm->getDataScript($table)
	);

	// Create parent directories
	foreach($dumpPaths as $path) {
		if($path)
			FileSystem::createFilePath($path);
	}

	$relPath = FileSystem::getRelativePath($workPath, $dumpPaths['structure']);

	echo "\n\033[1;36m$table\033[0m in \033[0;36m$relPath\033[0m: ";

	switch(dumpStructure($db, $table, $dumpPaths['structure'])) {
		case 0:
			echo "ok"; break;

		case 1:
			echo "created"; break;

		case 2:
			echo "updated"; break;
	}

	if($args->get('data')) {
		if($dumpPaths['data'] === FALSE) {
			echo "\n   -> data dump skipped by config\n";
		}

		else {
			$relPath = FileSystem::getRelativePath($workPath, $dumpPaths['data']);
			echo "\n   -> data dumped in: \033[0;36m$relPath\033[0m\n";

			dumpData($db, $table, $dumpPaths['data']);
		}
	}
}

echo "\n\nDone :-)\n\n";
