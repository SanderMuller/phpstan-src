<?php declare(strict_types = 1);
// usage: php -d phar.readonly=0 put-restarter.php <phar> <TurboProcessRestarter.php>
// Replaces the restarter in the phar with the pull request's and stamps members like compiler/build/resign.php.
require __DIR__ . '/tools/vendor/autoload.php';
[$_, $pharPath, $file] = $argv;
$phar = new Phar($pharPath);
$phar['src/Turbo/TurboProcessRestarter.php'] = file_get_contents($file);
$phar['src/Turbo/TurboDiagnoseExtension.php'] = file_get_contents(dirname($file) . '/TurboDiagnoseExtension.php');
unset($phar);
$util = new Seld\PharUtils\Timestamps($pharPath);
$util->updateTimestamps(new DateTimeImmutable('2026-09-30 06:00:00'));
$util->save($pharPath, Phar::SHA512);
echo "$pharPath: pull request restarter\n";
