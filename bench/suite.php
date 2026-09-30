<?php declare(strict_types = 1);
// usage: php suite.php <work dir> <tiny|parser|spawn|tempest> <rounds>
// Primes every arm once (untimed), then runs bench.php over the suite's arms, interleaved.
[$_, $w, $suite, $rounds] = $argv;
$php = escapeshellarg(PHP_BINARY);
$rm = $php . ' ' . escapeshellarg(__DIR__ . '/rmrf.php');
$project = $w . '/projects/' . ($suite === 'spawn' ? 'parser' : $suite);
$flags = $suite === 'spawn' ? ' -d disable_functions=pcntl_fork' : '';
$arms = explode(',', (string) (getenv('ARMS') ?: ($suite === 'tiny' ? 'stock,fc0,fcv,fcve' : 'stock,fc0,fcv')));
$client = $php . ' ' . escapeshellarg(__DIR__ . '/client.php') . ' ' . escapeshellarg((string) getenv('DAEMON_SOCKET'));
$kinds = match ($suite) {
	'tiny' => ['ver', 'warm', 'cold'],
	'parser' => ['warm', 'cold'],
	default => ['cold'],
};
$specs = [];
foreach ($arms as $arm) {
	$phar = escapeshellarg("$w/arms/$arm/phpstan.phar");
	$bin = $arm === 'daemon' ? $client : ($arm === 'pre' ? 'TMPDIR=' . escapeshellarg("$w/systemp-pre") . ' ' : '') . "$php$flags $phar";
	$analyse = "$bin analyse -c phpstan-$arm.neon --no-progress --memory-limit=-1";
	exec('cd ' . escapeshellarg($project) . " && $analyse > " . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null') . ' 2>&1', $o, $ec);
	echo "primed $suite/$arm, exit $ec\n";
	$emptyFc = match ($arm) {
		'fcve' => " && $rm --recreate " . escapeshellarg("$w/fcdir-fcve"),
		// the pull request's cache lives in the system temp dir, shared by the pr and pre arms (same phar)
		// its own TMPDIR, so emptying its cache leaves the pr arm's alone
		'pre' => " && $rm --recreate " . escapeshellarg("$w/systemp-pre"),
		default => '',
	};
	foreach ($kinds as $kind) {
		$cmd = match ($kind) {
			'ver' => "$bin --version",
			'warm' => $emptyFc === '' ? $analyse : 'prep:' . substr($emptyFc, 4) . ";;$analyse",
			'cold' => "prep:$rm tmp-$arm$emptyFc;;$analyse",
		};
		$specs[] = "$suite-$arm-$kind=$project|$cmd";
	}
}
passthru("$php " . escapeshellarg(__DIR__ . '/bench.php') . ' ' . (int) $rounds . ' ' . implode(' ', array_map('escapeshellarg', $specs)), $ec);
exit($ec);
