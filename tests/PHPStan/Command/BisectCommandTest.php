<?php declare(strict_types = 1);

namespace PHPStan\Command;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use function file_put_contents;
use function md5_file;
use function sha1_file;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class BisectCommandTest extends TestCase
{

	public function testAcceptsAPharThatMatchesTheChecksum(): void
	{
		$phar = $this->writeTempFile('phpstan.phar contents');
		$md5 = md5_file($phar);
		$sha1 = sha1_file($phar);
		self::assertNotFalse($md5);
		self::assertNotFalse($sha1);

		$this->assertTrue($this->pharMatchesChecksum($phar, ['md5' => $md5, 'sha1' => $sha1]));

		unlink($phar);
	}

	public function testRejectsAPharThatDoesNotMatchTheChecksum(): void
	{
		$phar = $this->writeTempFile('planted phar, right name, wrong bytes');
		$expected = ['md5' => str_repeat('0', 32), 'sha1' => str_repeat('0', 40)];

		$this->assertFalse($this->pharMatchesChecksum($phar, $expected));

		unlink($phar);
	}

	public function testRejectsAMissingPhar(): void
	{
		$expected = ['md5' => str_repeat('0', 32), 'sha1' => str_repeat('0', 40)];

		$this->assertFalse($this->pharMatchesChecksum('/does/not/exist.phar', $expected));
	}

	public function testRejectsAnEmptyChecksum(): void
	{
		$phar = $this->writeTempFile('phpstan.phar contents');

		$this->assertFalse($this->pharMatchesChecksum($phar, ['md5' => '', 'sha1' => '']));

		unlink($phar);
	}

	private function writeTempFile(string $contents): string
	{
		$path = tempnam(sys_get_temp_dir(), 'phpstan-bisect-test');
		file_put_contents($path, $contents);

		return $path;
	}

	/**
	 * @param array{md5: string, sha1: string} $expected
	 */
	private function pharMatchesChecksum(string $pharPath, array $expected): bool
	{
		// setAccessible() is not called: since PHP 8.1 reflection invokes private methods without it.
		return (new ReflectionMethod(BisectCommand::class, 'pharMatchesChecksum'))
			->invoke(new BisectCommand(), $pharPath, $expected);
	}

}
