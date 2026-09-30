<?php declare(strict_types = 1);
// usage: php -d phar.readonly=0 make-daemon.php <phar>
// Stamps member mtimes afterwards (a Phar API write zeroes them on Linux).
// Adds bin/phpstan-daemon: bin/phpstan with a fork server inserted before it reads the working directory.
$pharPath = $argv[1];
$bin = file_get_contents("phar://$pharPath/bin/phpstan");
$needle = '    $cwd = \getcwd();';
if (substr_count($bin, $needle) !== 1) { fwrite(STDERR, "anchor not found\n"); exit(1); }
$loop = <<<'PHP'
    // fork server: everything above ran once; every request continues below in a forked child
    $__socketPath = (string) \getenv('PHPSTAN_DAEMON_SOCKET');
    @\unlink($__socketPath);
    $__server = \stream_socket_server('unix://' . $__socketPath, $__errno, $__errstr);
    if ($__server === \false) { \fwrite(\STDERR, "daemon: $__errstr\n"); exit(1); }
    while (\true) {
        $__conn = @\stream_socket_accept($__server, -1);
        if ($__conn === \false) { continue; }
        $__request = \json_decode((string) \fgets($__conn), \true);
        $__pid = \pcntl_fork();
        if ($__pid === 0) {
            \fclose($__server);
            \chdir($__request['cwd']);
            // argv[0] must be a real file, or AnalyserRunner analyses in-process instead of in parallel
            $__request['argv'][0] = \Phar::running(\false);
            $_SERVER['argv'] = $GLOBALS['argv'] = $__request['argv'];
            $_SERVER['argc'] = $GLOBALS['argc'] = \count($__request['argv']);
            $analysisStartTime = \microtime(\true);
            break;
        }
        $__ru0 = \getrusage(1);
        \pcntl_waitpid($__pid, $__status);
        $__ru1 = \getrusage(1);
        $__cpu = static fn (array $r): float => $r['ru_utime.tv_sec'] + $r['ru_utime.tv_usec'] / 1e6 + $r['ru_stime.tv_sec'] + $r['ru_stime.tv_usec'] / 1e6;
        \fwrite($__conn, \pcntl_wexitstatus($__status) . ' ' . \round($__cpu($__ru1) - $__cpu($__ru0), 3) . "\n");
        \fclose($__conn);
    }

PHP;
$phar = new Phar($pharPath);
$phar['bin/phpstan-daemon'] = str_replace($needle, $loop . $needle, $bin);
unset($phar);
require_once __DIR__ . '/tools/vendor/autoload.php';
$util = new Seld\PharUtils\Timestamps($pharPath);
$util->updateTimestamps(new DateTimeImmutable('2026-09-29 09:57:28'));
$util->save($pharPath, Phar::SHA512);
echo "added bin/phpstan-daemon\n";
