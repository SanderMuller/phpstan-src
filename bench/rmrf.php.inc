<?php declare(strict_types = 1);
function rmrf(string $dir): void
{
	if (!is_dir($dir)) {
		return;
	}
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $f) {
		$f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
	}
	rmdir($dir);
}
