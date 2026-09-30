<?php declare(strict_types = 1);
// usage: php conc.php <work dir> <reps> [spawn]
// Starts 4 runs at once on the parser project, all sharing one freshly emptied file cache, and compares
// each run's raw errors with the stock arm's.
$w = $argv[1];
$reps = (int) $argv[2];
$spawn = ($argv[3] ?? '') === 'spawn';
$php = escapeshellarg(PHP_BINARY) . ($spawn ? ' -d disable_functions=pcntl_fork' : '');
$project = "$w/projects/parser";
$run = static function (string $arm, string $neon) use ($php, $w): string {
	return "$php " . escapeshellarg("$w/arms/$arm/phpstan.phar") . " analyse -c $neon --no-progress --error-format=raw --memory-limit=-1";
};
$norm = static function (string $out): array {
	$lines = array_filter(array_map('trim', explode("\n", $out)), static fn (string $l): bool => $l !== '');
	sort($lines);
	return $lines;
};
require_once __DIR__ . '/rmrf.php.inc';
rmrf("$project/tmp-stock");
$ref = $norm((string) shell_exec('cd ' . escapeshellarg($project) . ' && ' . $run('stock', 'phpstan-stock.neon') . ' 2>&1'));
echo 'reference: ', count($ref), " lines\n";
for ($r = 1; $r <= $reps; $r++) {
	rmrf("$w/fcdir-conc");
	mkdir("$w/fcdir-conc");
	$procs = [];
	for ($i = 1; $i <= 4; $i++) {
		rmrf("$project/tmp-conc$i");
		$procs[$i] = proc_open($run('conc', "phpstan-conc$i.neon"), [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes[$i], $project);
	}
	$same = 0;
	foreach ($procs as $i => $p) {
		$out = stream_get_contents($pipes[$i][1]);
		$ec = proc_close($p);
		$lines = $norm($out);
		if ($lines === $ref) {
			$same++;
		} else {
			echo "rep $r run $i exit $ec differs:\n", implode("\n", array_slice(array_merge(array_diff($lines, $ref), ['--- missing:'], array_diff($ref, $lines)), 0, 12)), "\n";
		}
	}
	echo 'rep ', $r, ($spawn ? ' spawn' : ' fork'), ": $same/4 identical to stock\n";
}
