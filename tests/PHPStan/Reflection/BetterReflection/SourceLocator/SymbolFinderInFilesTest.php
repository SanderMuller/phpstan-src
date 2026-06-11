<?php declare(strict_types = 1);

namespace PHPStan\Reflection\BetterReflection\SourceLocator;

use PHPStan\Testing\PHPStanTestCase;
use function array_fill_keys;
use function count;
use function file_put_contents;
use function hash_file;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

class SymbolFinderInFilesTest extends PHPStanTestCase
{

	public function testHashAndFindSymbolsSerial(): void
	{
		$finder = self::getContainer()->getByType(SymbolFinderInFiles::class);
		$file = __DIR__ . '/data/directory/a.php';
		$hash = hash_file('xxh128', $file);

		$result = $finder->hashAndFindSymbols([$file => null], true);
		$this->assertArrayHasKey($file, $result);
		[$resultHash, $symbols] = $result[$file];
		$this->assertSame($hash, $resultHash);
		$this->assertNotNull($symbols);
		$this->assertNotEmpty($symbols[0]);

		// matching cached hash skips symbol extraction
		$result = $finder->hashAndFindSymbols([$file => $hash], true);
		[$resultHash, $symbols] = $result[$file];
		$this->assertSame($hash, $resultHash);
		$this->assertNull($symbols);

		// stale cached hash triggers re-extraction
		$result = $finder->hashAndFindSymbols([$file => 'stale-hash'], true);
		[, $symbols] = $result[$file];
		$this->assertNotNull($symbols);

		// unreadable files are omitted
		$result = $finder->hashAndFindSymbols([__DIR__ . '/data/does-not-exist.php' => null], true);
		$this->assertSame([], $result);
	}

	public function testHashAndFindSymbolsAboveParallelThreshold(): void
	{
		$finder = self::getContainer()->getByType(SymbolFinderInFiles::class);

		$directory = sys_get_temp_dir() . '/phpstan-symbol-finder-test-' . uniqid();
		mkdir($directory, 0777, true);

		$files = [];
		for ($i = 0; $i < 401; $i++) {
			$file = sprintf('%s/file%d.php', $directory, $i);
			file_put_contents($file, sprintf("<?php\n\nclass SymbolFinderParallelTestClass%d {}\n", $i));
			$files[] = $file;
		}

		$result = $finder->hashAndFindSymbols(array_fill_keys($files, null), true);

		$this->assertCount(count($files), $result);
		foreach ($files as $i => $file) {
			[$hash, $symbols] = $result[$file];
			$this->assertSame(hash_file('xxh128', $file), $hash);
			$this->assertNotNull($symbols);
			$this->assertSame([sprintf('symbolfinderparalleltestclass%d', $i)], $symbols[0]);
		}
	}

}
