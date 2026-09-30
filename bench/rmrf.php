<?php declare(strict_types = 1);
// usage: php rmrf.php [--recreate] <dir>... Deletes each directory tree (portable rm -rf); --recreate makes it again, empty.
require_once __DIR__ . '/rmrf.php.inc';
$args = array_slice($argv, 1);
$recreate = ($args[0] ?? '') === '--recreate';
if ($recreate) {
	array_shift($args);
}
foreach ($args as $dir) {
	rmrf($dir);
	if ($recreate) {
		mkdir($dir, 0777, true);
	}
}
