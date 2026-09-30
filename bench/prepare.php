<?php declare(strict_types = 1);
// usage: php prepare.php <work dir> <php-parser lib dir> [<tempest checkout>]
// Writes the projects: tiny (one file), parser (php-parser's own lib/), and tempest (in place, when given).
[$_, $w, $parserLib] = $argv;
$tempest = $argv[3] ?? null;
$copy = static function (string $from, string $to) use (&$copy): void {
	@mkdir($to, 0777, true);
	foreach (new DirectoryIterator($from) as $f) {
		if ($f->isDot()) continue;
		$f->isDir() ? $copy($f->getPathname(), $to . '/' . $f->getFilename()) : copy($f->getPathname(), $to . '/' . $f->getFilename());
	}
};
$copy(__DIR__ . '/project/src', "$w/projects/tiny/src");
file_put_contents("$w/projects/tiny/base.neon", "includes:\n    - $w/arms/stock/conf/bleedingEdge.neon\nparameters:\n    level: max\n    paths: [src]\n");
$copy($parserLib, "$w/projects/parser/lib");
file_put_contents("$w/projects/parser/base.neon", "parameters:\n    level: 8\n    paths: [lib]\n");
if ($tempest !== null) {
	$dirs = glob("$tempest/packages/*/src", GLOB_ONLYDIR);
	$paths = implode('', array_map(static fn (string $d): string => "        - $d\n", $dirs));
	@mkdir("$w/projects", 0777, true);
	symlink($tempest, "$w/projects/tempest");
	file_put_contents("$tempest/base.neon", "parameters:\n    level: 8\n    phpVersion: 80500\n    paths:\n$paths");
	echo count($dirs), " tempest src dirs\n";
}
echo "projects written\n";
