<?php declare(strict_types = 1);

namespace PHPStan\Command;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use function chmod;
use function function_exists;
use function mkdir;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use const DIRECTORY_SEPARATOR;

class FixerApplicationTest extends TestCase
{

	public function testAcceptsAPrivateDirectory(): void
	{
		$base = $this->prepare();
		mkdir($base . '/private', 0700);

		$this->assertNull($this->tmpDirUnsafeReason($base . '/private'));
	}

	public function testRejectsASymlink(): void
	{
		$base = $this->prepare();
		mkdir($base . '/target', 0700);
		symlink($base . '/target', $base . '/link');

		$this->assertSame('it is a symbolic link', $this->tmpDirUnsafeReason($base . '/link'));
	}

	public function testRejectsAWorldWritableDirectory(): void
	{
		$base = $this->prepare();
		mkdir($base . '/ww', 0700);
		chmod($base . '/ww', 0777); // an explicit chmod is needed because mkdir() is masked by the umask

		$this->assertSame('it is writable by other users', $this->tmpDirUnsafeReason($base . '/ww'));
	}

	private function prepare(): string
	{
		if (DIRECTORY_SEPARATOR !== '/' || !function_exists('posix_geteuid')) {
			self::markTestSkipped('The fixer temporary directory check runs on POSIX only.');
		}

		$base = sys_get_temp_dir() . '/phpstan-fixer-security-' . uniqid();
		mkdir($base, 0700, true);

		return $base;
	}

	private function tmpDirUnsafeReason(string $tmpDir): ?string
	{
		$app = (new ReflectionClass(FixerApplication::class))->newInstanceWithoutConstructor();

		// setAccessible() is not called: since PHP 8.1 reflection invokes private methods without it.
		return (new ReflectionMethod($app, 'tmpDirUnsafeReason'))->invoke($app, $tmpDir);
	}

}
