<?php
$files = []; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1], FilesystemIterator::SKIP_DOTS)) as $f) { if ($f->isFile()) $files[] = $f->getPathname(); }
$files = array_slice($files, 0, (int) ($argv[2] ?? 2000));
$c0 = getrusage(); $t = hrtime(true);
foreach ($files as $f) { $x = file_get_contents($f); }
$w = (hrtime(true) - $t) / 1e6; $c1 = getrusage();
$cpu = ($c1['ru_utime.tv_sec'] - $c0['ru_utime.tv_sec']) * 1e3 + ($c1['ru_utime.tv_usec'] - $c0['ru_utime.tv_usec']) / 1e3 + ($c1['ru_stime.tv_sec'] - $c0['ru_stime.tv_sec']) * 1e3 + ($c1['ru_stime.tv_usec'] - $c0['ru_stime.tv_usec']) / 1e3;
printf("%d files: wall %.1f ms, cpu %.1f ms, involuntary %d\n", count($files), $w, $cpu, $c1['ru_nivcsw'] - $c0['ru_nivcsw']);
