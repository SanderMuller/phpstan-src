<?php declare(strict_types = 1);
// usage: php setup.php <work dir> <phpstan package dir> <arm>... Copies the phpstan package once per arm,
// patches each copy, and writes phpstan-<arm>.neon into every project under <work dir>/projects.
[$_, $w, $pkg] = $argv;
$arms = array_slice($argv, 3);
$neonOnly = $arms === ['--neon-only'];
if ($neonOnly) {
	$arms = array_map('basename', glob("$w/arms/*", GLOB_ONLYDIR));
}
$copy = static function (string $from, string $to) use (&$copy): void {
	mkdir($to, 0777, true);
	foreach (new DirectoryIterator($from) as $f) {
		if ($f->isDot()) continue;
		$f->isDir() ? $copy($f->getPathname(), $to . '/' . $f->getFilename()) : copy($f->getPathname(), $to . '/' . $f->getFilename());
	}
};
foreach ($neonOnly ? [] : $arms as $arm) {
	$dst = "$w/arms/$arm";
	$copy($pkg, $dst);
	$fc = "$w/fcdir-$arm";
	@mkdir($fc, 0777, true);
	$args = match (true) {
		$arm === 'stock', $arm === 'daemon', $arm === 'pr', $arm === 'pre', $arm === 'prci' => ['sha512'],
		$arm === 'fc0' => ['sha512', $fc],
		default => ['sha512', $fc, 'vts'],
	};
	passthru(escapeshellarg(PHP_BINARY) . ' -d phar.readonly=0 ' . escapeshellarg(__DIR__ . '/make-arm.php') . ' ' . implode(' ', array_map('escapeshellarg', ["$dst/phpstan.phar", ...$args])), $ec);
	if ($ec !== 0) exit($ec);
	if ($arm === 'pr' || $arm === 'pre' || $arm === 'prci') {
		passthru(escapeshellarg(PHP_BINARY) . ' -d phar.readonly=0 ' . escapeshellarg(__DIR__ . '/put-restarter.php') . ' ' . escapeshellarg("$dst/phpstan.phar") . ' ' . escapeshellarg(__DIR__ . '/pr/TurboProcessRestarter.php'), $ec);
		if ($ec !== 0) exit($ec);
	}
	if ($arm === 'daemon') {
		passthru(escapeshellarg(PHP_BINARY) . ' -d phar.readonly=0 ' . escapeshellarg(__DIR__ . '/make-daemon.php') . ' ' . escapeshellarg("$dst/phpstan.phar"), $ec);
		if ($ec !== 0) exit($ec);
	}
}
foreach (glob("$w/projects/*", GLOB_ONLYDIR) as $proj) {
	$base = file_get_contents("$proj/base.neon");
	foreach ([...$arms, 'conc1', 'conc2', 'conc3', 'conc4'] as $arm) {
		file_put_contents("$proj/phpstan-$arm.neon", $base . "\n    tmpDir: tmp-$arm\n");
	}
}
