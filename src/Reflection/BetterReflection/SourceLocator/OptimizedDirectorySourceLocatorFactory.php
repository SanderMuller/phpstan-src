<?php declare(strict_types = 1);

namespace PHPStan\Reflection\BetterReflection\SourceLocator;

use PHPStan\Cache\Cache;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\File\FileFinder;
use PHPStan\Php\PhpVersion;
use function array_key_exists;
use function sprintf;

#[AutowiredService]
final class OptimizedDirectorySourceLocatorFactory
{

	public function __construct(
		private FileNodesFetcher $fileNodesFetcher,
		#[AutowiredParameter(ref: '@fileFinderScan')]
		private FileFinder $fileFinder,
		private PhpVersion $phpVersion,
		private SymbolFinderInFiles $symbolFinderInFiles,
		private Cache $cache,
	)
	{
	}

	public function createByDirectory(string $directory): OptimizedDirectorySourceLocator
	{
		$files = $this->fileFinder->findFiles([$directory])->getFiles();

		$cacheKey = sprintf('odsl-%s', $directory);
		return $this->createCachedDirectorySourceLocator($files, $cacheKey);
	}

	/**
	 * @param string[] $files
	 * @param non-empty-string $cacheKey
	 */
	private function createCachedDirectorySourceLocator(array $files, string $cacheKey): OptimizedDirectorySourceLocator
	{
		$variableCacheKey = sprintf('v1-%s', $this->phpVersion->supportsEnums() ? 'enums' : 'no-enums');

		/** @var array<string, array{string, string[], string[], string[]}>|null $cached */
		$cached = $this->cache->load($cacheKey, $variableCacheKey);
		$cached ??= [];

		$filesWithCachedHashes = [];
		foreach ($files as $file) {
			$filesWithCachedHashes[$file] = $cached[$file][0] ?? null;
		}

		// hashing and symbol extraction both happen in the finder (in parallel for large file sets)
		$newCached = [];
		foreach ($this->symbolFinderInFiles->hashAndFindSymbols($filesWithCachedHashes, $this->phpVersion->supportsEnums()) as $file => [$newHash, $symbols]) {
			if ($symbols === null) {
				$newCached[$file] = $cached[$file];
				continue;
			}

			[$newClasses, $newFunctions, $newConstants] = $symbols;
			$newCached[$file] = [$newHash, $newClasses, $newFunctions, $newConstants];
		}

		$this->cache->save($cacheKey, $variableCacheKey, $newCached);

		[$classToFile, $functionToFiles, $constantToFile] = $this->changeStructure($newCached);

		return new OptimizedDirectorySourceLocator(
			$this->fileNodesFetcher,
			$this->cache,
			$this->phpVersion,
			$classToFile,
			$functionToFiles,
			$constantToFile,
		);
	}

	/**
	 * @param string[] $files
	 * @param non-empty-string&literal-string $uniqueCacheIdentifier
	 */
	public function createByFiles(array $files, string $uniqueCacheIdentifier): OptimizedDirectorySourceLocator
	{
		return $this->createCachedDirectorySourceLocator($files, $uniqueCacheIdentifier);
	}

	/**
	 * @param array<string, array{string, string[], string[], string[]}> $symbols
	 * @return array{array<string, string>, array<string, array<int, string>>, array<string, string>}
	 */
	private function changeStructure(array $symbols): array
	{
		$classToFile = [];
		$constantToFile = [];
		$functionToFiles = [];
		foreach ($symbols as $file => [, $classes, $functions, $constants]) {
			foreach ($classes as $classInFile) {
				$classToFile[$classInFile] = $file;
			}
			foreach ($functions as $functionInFile) {
				if (!array_key_exists($functionInFile, $functionToFiles)) {
					$functionToFiles[$functionInFile] = [];
				}
				$functionToFiles[$functionInFile][] = $file;
			}
			foreach ($constants as $constantInFile) {
				$constantToFile[$constantInFile] = $file;
			}
		}

		return [
			$classToFile,
			$functionToFiles,
			$constantToFile,
		];
	}

}
