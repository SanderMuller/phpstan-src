<?php declare(strict_types = 1);
// usage: php sig.php <phar> Re-signs copies of the phar with every algorithm phar supports and times
// Phar::loadPhar() (a fresh process each time, median of 7), plus hash() over the same bytes in-process.
$src = $argv[1];
$dir = sys_get_temp_dir() . '/sigbench';
@mkdir($dir);
$algs = ['md5' => Phar::MD5, 'sha1' => Phar::SHA1, 'sha256' => Phar::SHA256, 'sha512' => Phar::SHA512];
$php = escapeshellarg(PHP_BINARY);
file_put_contents("$dir/load.php", '<?php $t = hrtime(true); Phar::loadPhar($argv[1], "x.phar"); echo (hrtime(true) - $t) / 1e6;');
$bytes = file_get_contents($src);
printf("%s, %s, %.1f MB\n", PHP_VERSION, php_uname('m'), strlen($bytes) / 1e6);
foreach ($algs as $name => $alg) {
	$dst = "$dir/p-$name.phar";
	copy($src, $dst);
	$p = new Phar($dst);
	$p->setSignatureAlgorithm($alg);
	unset($p);
	$t = [];
	for ($i = 0; $i < 7; $i++) {
		$t[] = (float) shell_exec("$php -d phar.readonly=0 " . escapeshellarg("$dir/load.php") . ' ' . escapeshellarg($dst));
	}
	sort($t);
	$h0 = hrtime(true);
	hash($name, $bytes);
	printf("%-7s loadPhar median %6.1f ms   hash() %6.1f ms\n", $name, $t[3], (hrtime(true) - $h0) / 1e6);
}
