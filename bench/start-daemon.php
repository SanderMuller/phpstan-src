<?php declare(strict_types = 1);
// usage: php start-daemon.php <work dir> <socket> Starts the fork-server daemon from arms/daemon in the background,
// with the ini entries the turbo restart would give it, and waits for its socket.
[$_, $w, $socket] = $argv;
$pkg = "$w/arms/daemon";
$platform = PHP_OS_FAMILY === 'Darwin' ? 'macos-arm64' : 'linux-gnu-' . php_uname('m');
$ext = sprintf('%s/turbo-ext/%s/phpstan_turbo-%d.%d.so', $pkg, $platform, PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
if (!is_file($ext)) { fwrite(STDERR, "no turbo binary at $ext\n"); exit(1); }
file_put_contents("$w/launch.php", "<?php\nPhar::loadPhar(" . var_export("$pkg/phpstan.phar", true) . ", 'phpstan.phar');\n\$_SERVER['SCRIPT_FILENAME'] = 'phar://phpstan.phar/bin/phpstan';\nrequire 'phar://phpstan.phar/bin/phpstan-daemon';\n");
$ini = ['memory_limit=-1', 'opcache.enable=1', 'opcache.enable_cli=1', 'opcache.jit=disable', 'opcache.jit_buffer_size=0', 'opcache.validate_timestamps=0', 'opcache.file_update_protection=0', 'opcache.max_file_size=0', 'opcache.file_cache=', 'opcache.save_comments=1', 'opcache.optimization_level=0x7FFEBFFF', 'opcache.memory_consumption=256', 'opcache.interned_strings_buffer=64', 'opcache.max_accelerated_files=20000', "extension=$ext", "phpstan.turboExtensionPath=$ext", 'phpstan.restarted=1'];
$cmd = 'PHPSTAN_DAEMON_SOCKET=' . escapeshellarg($socket) . ' nohup ' . escapeshellarg(PHP_BINARY) . implode('', array_map(static fn (string $e): string => ' -d ' . escapeshellarg($e), $ini)) . ' ' . escapeshellarg("$w/launch.php") . ' analyse >> ' . escapeshellarg("$w/daemon.log") . ' 2>&1 &';
exec($cmd);
for ($i = 0; $i < 100 && !file_exists($socket); $i++) { usleep(100000); }
echo file_exists($socket) ? "daemon up at $socket\n" : "daemon did not start:\n" . file_get_contents("$w/daemon.log");
exit(file_exists($socket) ? 0 : 1);
