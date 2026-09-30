<?php declare(strict_types = 1);
// usage: php probe.php <work dir> Per file cache dir: .bin count and bytes, split into phar members and plain files,
// plus the top-level subdirectories (one per OPcache system id / cache id).
$w = $argv[1];
foreach (glob("$w/fcdir-*", GLOB_ONLYDIR) as $dir) {
	$n = ['phar' => 0, 'plain' => 0]; $b = ['phar' => 0, 'plain' => 0];
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
		if (!str_ends_with($f->getFilename(), '.bin')) continue;
		$k = str_contains($f->getPathname(), 'phar:') || str_contains($f->getPathname(), '.phar') ? 'phar' : 'plain';
		$n[$k]++; $b[$k] += $f->getSize();
	}
	$top = array_map('basename', glob("$dir/*", GLOB_ONLYDIR));
	printf("%s: phar %d files %.1f MB, plain %d files %.1f MB, %d top dirs: %s\n", basename($dir), $n['phar'], $b['phar'] / 1e6, $n['plain'], $b['plain'] / 1e6, count($top), implode(' ', array_slice($top, 0, 6)));
}
