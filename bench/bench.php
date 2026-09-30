<?php declare(strict_types = 1);
// usage: php bench.php <rounds> <label>=<cwd>|<shell command> ...  Runs every command once per round, interleaved.
// Per command: median/min/max wall and median child CPU (user+sys) in seconds. A "prep:" prefix runs before each run, untimed.
$rounds = (int) $argv[1]; $arms = [];
foreach (array_slice($argv, 2) as $spec) { [$label, $rest] = explode('=', $spec, 2); [$cwd, $cmd] = explode('|', $rest, 2); $prep = null; if (str_starts_with($cmd, 'prep:')) { [$prep, $cmd] = explode(';;', substr($cmd, 5), 2); } $arms[$label] = compact('cwd', 'cmd', 'prep'); }
$res = [];
$null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$cpu = static function (): float { $u = @getrusage(1); if (!is_array($u)) { return 0.0; } return $u['ru_utime.tv_sec'] + $u['ru_utime.tv_usec'] / 1e6 + $u['ru_stime.tv_sec'] + $u['ru_stime.tv_usec'] / 1e6; };
for ($r = 0; $r < $rounds; $r++) foreach ($arms as $label => $a) {
	if ($a['prep'] !== null) { exec('cd ' . escapeshellarg($a['cwd']) . ' && ' . $a['prep'] . ' >' . $null . ' 2>&1'); }
	$c0 = $cpu(); $t0 = hrtime(true);
	exec('cd ' . escapeshellarg($a['cwd']) . ' && ' . $a['cmd'] . ' >' . $null . ' 2>&1', $o, $ec);
	$res[$label]['wall'][] = (hrtime(true) - $t0) / 1e9; $res[$label]['cpu'][] = $cpu() - $c0; $res[$label]['ec'] = $ec;
}
$med = static function (array $v): float { sort($v); return $v[intdiv(count($v), 2)]; };
foreach ($res as $label => $x) printf("%-28s wall median %.3f (%.3f-%.3f)  cpu median %.3f  exit %d  n=%d\n", $label, $med($x['wall']), min($x['wall']), max($x['wall']), $med($x['cpu']), $x['ec'], count($x['wall']));
