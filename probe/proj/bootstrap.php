<?php
$f = (new ReflectionClass(\PHPStan\Analyser\MutatingScope::class))->getFileName();
$st = opcache_get_status(true);
$phar = 0; $plain = 0;
foreach ($st['scripts'] ?? [] as $path => $_) { str_starts_with($path, 'phar://') ? $phar++ : $plain++; }
fwrite(STDERR, 'PROBE ' . json_encode([
	'os' => PHP_OS, 'file' => $f,
	'stat_mtime' => @stat($f)['mtime'] ?? null,
	'filemtime' => @filemtime($f),
	'is_cached' => opcache_is_script_cached($f),
	'shm_scripts_phar' => $phar, 'shm_scripts_plain' => $plain,
	'vts' => ini_get('opcache.validate_timestamps'), 'fc' => ini_get('opcache.file_cache'),
	'turbo' => extension_loaded('phpstan_turbo'),
	'wrappers' => stream_get_wrappers(),
], JSON_UNESCAPED_SLASHES) . "\n");
