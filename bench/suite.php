<?php declare(strict_types = 1);
// usage: php suite.php <work dir> <tiny|parser|spawn|tempest> <rounds>
// Primes every arm once (untimed), then runs bench.php over the suite's arms, interleaved.
[$_, $w, $suite, $rounds] = $argv;
$php = escapeshellarg(PHP_BINARY);
$rm = $php . ' ' . escapeshellarg(__DIR__ . '/rmrf.php');
$project = $w . '/projects/' . ($suite === 'spawn' ? 'parser' : $suite);
$flags = $suite === 'spawn' ? ' -d disable_functions=pcntl_fork' : '';
$arms = $suite === 'tiny' ? ['stock', 'fc0', 'fcv', 'fcve'] : ['stock', 'fcv', 'fcve'];
$kinds = match ($suite) {
	'tiny' => ['ver', 'warm', 'cold'],
	'parser' => ['warm', 'cold'],
	default => ['cold'],
};
$specs = [];
foreach ($arms as $arm) {
	$phar = escapeshellarg("$w/arms/$arm/phpstan.phar");
	$analyse = "$php$flags $phar analyse -c phpstan-$arm.neon --no-progress --memory-limit=-1";
	exec('cd ' . escapeshellarg($project) . " && $analyse > " . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null') . ' 2>&1', $o, $ec);
	echo "primed $suite/$arm, exit $ec\n";
	$emptyFc = $arm === 'fcve' ? " && $rm --recreate " . escapeshellarg("$w/fcdir-fcve") : '';
	foreach ($kinds as $kind) {
		$cmd = match ($kind) {
			'ver' => "$php $phar --version",
			'warm' => $emptyFc === '' ? $analyse : 'prep:' . substr($emptyFc, 4) . ";;$analyse",
			'cold' => "prep:$rm tmp-$arm$emptyFc;;$analyse",
		};
		$specs[] = "$suite-$arm-$kind=$project|$cmd";
	}
}
passthru("$php " . escapeshellarg(__DIR__ . '/bench.php') . ' ' . (int) $rounds . ' ' . implode(' ', array_map('escapeshellarg', $specs)), $ec);
exit($ec);
