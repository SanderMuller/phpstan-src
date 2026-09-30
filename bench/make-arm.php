<?php declare(strict_types = 1);
// usage: php -d phar.readonly=0 make-arm.php <phar> <sha1|sha512> [<file cache dir> [vts]]
// Re-signs the phar. With a directory, the turbo restart (and the workers it spawns) use it as a
// persistent OPcache file cache instead of blanking opcache.file_cache. "vts" also turns
// opcache.validate_timestamps on, so edited files and a replaced phar are recompiled.
$pharPath = $argv[1];
$alg = $argv[2];
$dir = $argv[3] ?? null;
$vts = ($argv[4] ?? '') === 'vts';
$phar = new Phar($pharPath);
$phar->startBuffering();
if ($dir !== null) {
	$inner = 'src/Turbo/TurboProcessRestarter.php';
	$src = file_get_contents('phar://' . $pharPath . '/' . $inner);
	$replace = ["'opcache.file_cache='," => var_export('opcache.file_cache=' . $dir, true) . ','];
	if ($vts) {
		$replace["'opcache.validate_timestamps=0',"] = "'opcache.validate_timestamps=1',";
	}
	foreach ($replace as $old => $new) {
		if (substr_count($src, $old) !== 1) {
			fwrite(STDERR, "patch did not apply: $old\n");
			exit(1);
		}
		$src = str_replace($old, $new, $src);
	}
	$phar[$inner] = $src;
}
$phar->stopBuffering();
unset($phar);
// A Phar API rewrite can leave members at mtime 0 (seen on Linux), which OPcache refuses to cache with
// validate_timestamps on. Stamp them the way compiler/build/resign.php does.
require_once __DIR__ . '/tools/vendor/autoload.php';
$util = new Seld\PharUtils\Timestamps($pharPath);
$util->updateTimestamps(new DateTimeImmutable('2026-09-29 09:57:28'));
$util->save($pharPath, $alg === 'sha1' ? Phar::SHA1 : Phar::SHA512);
$zero = 0;
foreach (new RecursiveIteratorIterator(new Phar($pharPath)) as $m) {
	$zero += $m->getMTime() === 0 ? 1 : 0;
}
if ($zero > 0) {
	fwrite(STDERR, "$zero members at mtime 0\n");
	exit(1);
}
echo $pharPath, ': ', (new Phar($pharPath))->getSignature()['hash_type'], $dir !== null ? ", file cache $dir" . ($vts ? ', timestamps on' : '') : '', "\n";
