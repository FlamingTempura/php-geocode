<?php

// Imports a geonames dataset into the geostore
// To run: php import.php
// Use -a to add to existing geostore (i.e., don't wipe geostore)
// geonames archive - list at http://download.geonames.org/export/dump/

$tmpname = '/tmp/geonames.zip';  // temporary location for geonames archive

$settings = getSettings();
$ds = read('Dataset', 'http://download.geonames.org/export/dump/cities1000.zip');
$host = read('Database host', 'localhost', $settings['host']);
$db = read('Database name', $settings['db']);
$user = read('Database username', $settings['user']);
$pass = read('Password', $settings['pass']);
$tbl = read('Table name', 'geoname', $settings['tbl']);
saveSettings($host, $db, $user, $pass, $tbl);

print('Connecting to database... ');
$pdo = new PDO("pgsql:host=$host dbname=$db", $user, $pass);
abortOn(!$pdo, 'FAILED.', true);
success('SUCCESS.');

print('Fetching ' . $ds . ' ');
file_put_contents($tmpname, fopen($ds, 'r'));
success('DONE.');

print('Extracting... ');
$zip = zip_open($tmpname);
abortOn(!$zip, 'FAILED.', true);
$zip_entry = zip_read($zip);
zip_entry_open($zip, $zip_entry, 'r');
$lines = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
success('DONE.');

$r = $pdo->query("SELECT * FROM pg_tables WHERE tablename = '$tbl'");
if ($r->fetch()) {		// Does the table exist?
	if (count(getopt('a')) === 0) {
		$continue = read('Current geostore will be wiped - continue? [Y/n]');
		abortOn(strtolower($continue) !== 'y', 'ABORTED.', true);
		print('Wiping geostore... ');
		$pdo->exec('TRUNCATE $tbl');
	}
} else {
	print('Creating table... ');
	$pdo->exec("CREATE TABLE $tbl (id bigserial primary key,
		name varchar(200) NOT NULL, countrycode varchar(2) NOT NULL,
		coordinate geography(Point,4326) )");
}
success('DONE.');

$lines = explode("\n", $lines);
$count = count($lines);

print('Inserting ' . $count . ' geonames...   0%');
$insert = $pdo->prepare("INSERT INTO $tbl (name, countrycode, coordinate)
	VALUES (:name, :countrycode, ST_MakePoint(:lng, :lat))");
$timestart = microtime(true);
$i = $count - 1;
$percent = 0;
while ($i--) {
	$line = explode("\t", $lines[$i], 10);
	var_dump($line);
	die();
	$insert->execute(array(
		':name' => $line[1],
		':countrycode' => $line[8],
		':lat' => floatval($line[4]),
		':lng' => floatval($line[5])
	));
	$nprogress = 100 - round($i / $count * 100);
	if ($nprogress > $percent) {
		$percent = $nprogress;
		print "\033[4D" . str_pad($percent, '3', ' ', STR_PAD_LEFT) . '%';
	}
}
print(' (took ' . round(microtime(true) - $timestart, 4) . 's.) ');
success('DONE.');

zip_close($zip);
print('Cleaning up... ');
unlink($tmpname);
success("DONE.\r\nGeostore import was successful.");

function success ($str) { print("\e[1m\e[32m$str\e[0m\r\n"); }
function abortOn ($condition, $str, $die = false) {
	if (!$condition) { return; }
	print("\e[1m\e[31m$str\e[0m\r\n");
	if ($die) { die(); }
}
function read ($msg, $default = null) {
	print($msg . ($default ? " [$default]" : '') . ': ');
	$rtn = readline();
	if (!$rtn && is_null($default)) { return read($msg, $default); }
	return $rtn ?: $default;
}
function saveSettings ($h, $db, $user, $pass, $tbl) {
	file_put_contents('.settings', serialize(array('host' => $h, 'db' => $db,
		'user' => $user, 'pass' => $pass, 'tbl' => $tbl)));
}
function getSettings () {
	return file_exists('.settings') ?
		unserialize(file_get_contents('.settings')) :
		array('host' => null, 'db' => null, 'user' => null,
			'pass' => null, 'tbl' => null);
}
