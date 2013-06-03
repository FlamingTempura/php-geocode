<?php

/** START CONFIG **/

// geonames archive - list at http://download.geonames.org/export/dump/
$url = 'http://download.geonames.org/export/dump/cities1000.zip';

// postgres connection settings
$host = 'localhost';
$database = 'my_db';
$username = 'my_username';
$password = 'my_password';

// temporary location for geonames archive
$tmpname = '/tmp/geonames.zip';

/** END CONFIG **/


print('Connecting to database... ');
$pdo = new PDO("pgsql:host=$host dbname=$database", $username, $password);
if (!$pdo) { printlnred('FAILED.', true); }
printlngreen('SUCCESS.');

print('Fetching ' . $url . ' ');
file_put_contents($tmpname, fopen($url, 'r'));
printlngreen('DONE.');

print('Extracting... ');
$zip = zip_open($tmpname);
if (!$zip) { printlnred('FAILED.', true); }
$zip_entry = zip_read($zip);
zip_entry_open($zip, $zip_entry, 'r');
$lines = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
printlngreen('DONE.');

// Does the geoname table exist?
$r = $pdo->query("SELECT * FROM pg_tables WHERE tablename = 'geoname'");
if ($r->fetch()) {
	print('This will wipe the current geostore - OK to continue? [Y/n] ');
	$continue = readline();
	if (strtolower($continue) !== 'y') {
		printlnred('ABORTED.', true);
	}
	print('Wiping geostore... ');
	$pdo->exec('TRUNCATE geoname');
} else {
	print('Creating table... ');
	$pdo->exec('
		CREATE TABLE geoname (
			id bigserial primary key,
			name varchar(200) NOT NULL,
			countrycode varchar(2) NOT NULL,
			coordinate geography(Point,4326)
		)');
}
printlngreen('DONE.');

$lines = explode("\n", $lines);
$count = count($lines);

print('Inserting ' . $count . ' geonames...   0%');
$insert = $pdo->prepare('
	INSERT INTO geoname (name, countrycode, coordinate)
		VALUES (:name, :countrycode, ST_MakePoint(:lng, :lat))');
$timestart = microtime(true);
$i = $count - 1;
$percent = 0;
while ($i--) {
	$line = explode("\t", $lines[$i], 10);
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
printlngreen('Done.');

zip_close($zip);
print('Cleaning up... ');
unlink($tmpname);
printlngreen('DONE.');
printlngreen('Geostore import was successful.');

function printlngreen ($str) {
	print("\e[1m\e[32m$str\e[0m\r\n");
}
function printlnred ($str, $die = false) {
	print("\e[1m\e[31m$str\e[0m\r\n");
	if ($die) { die(); }
}
