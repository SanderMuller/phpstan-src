<?php declare(strict_types = 1);
// usage: php -d opcache.enable_cli=1 -d opcache.validate_timestamps=1 mtime.php <phar>
// What OPcache sees for a phar member with timestamp validation on.
$phar = realpath($argv[1]);
$member = "phar://$phar/src/Analyser/ScopeContext.php";
echo PHP_VERSION, ' ', PHP_OS, "\n";
echo 'stat(member) mtime: ', var_export(@stat($member)['mtime'] ?? null, true), "\n";
echo 'filemtime(member): ', var_export(@filemtime($member), true), "\n";
echo 'filemtime(phar): ', var_export(filemtime($phar), true), ' (', date('c', filemtime($phar)), ")\n";
$p = new Phar($phar);
echo 'PharFileInfo mtime: ', $p['src/Analyser/ScopeContext.php']->getMTime(), "\n";
echo 'time(): ', time(), "\n";
foreach (['opcache.validate_timestamps', 'opcache.file_update_protection', 'opcache.max_file_size', 'opcache.revalidate_freq'] as $k) echo "$k=", ini_get($k), "\n";
$ok = @opcache_compile_file($member);
echo 'opcache_compile_file: ', var_export($ok, true), ' ', error_get_last()['message'] ?? '', "\n";
echo 'cached: ', var_export(opcache_is_script_cached($member), true), "\n";
