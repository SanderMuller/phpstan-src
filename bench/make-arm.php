<?php declare(strict_types = 1);
// usage: php -d phar.readonly=0 make-arm.php <phar> <sha1|sha512> [<file cache dir>]
// Re-signs the phar, and with a directory given, makes the turbo restart use it as a persistent
// OPcache file cache instead of blanking opcache.file_cache.
[$_, $pharPath, $alg] = $argv;
$dir = $argv[3] ?? null;
$phar = new Phar($pharPath);
$phar->startBuffering();
if ($dir !== null) {
	$inner = 'src/Turbo/TurboProcessRestarter.php';
	$src = file_get_contents('phar://' . $pharPath . '/' . $inner);
	$n = substr_count($src, "'opcache.file_cache=',");
	if ($n !== 1) {
		fwrite(STDERR, "file cache patch did not apply: $n\n");
		exit(1);
	}
	$phar[$inner] = str_replace("'opcache.file_cache=',", var_export('opcache.file_cache=' . $dir, true) . ',', $src);
}
$phar->setSignatureAlgorithm($alg === 'sha1' ? Phar::SHA1 : Phar::SHA512);
$phar->stopBuffering();
echo $pharPath, ': ', (new Phar($pharPath))->getSignature()['hash_type'], $dir !== null ? ", file cache $dir" : '', "\n";
