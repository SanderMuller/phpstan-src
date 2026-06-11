<?php declare(strict_types = 1);

namespace PHPStan\Reflection\BetterReflection\SourceLocator;

use function array_chunk;
use function array_diff_key;
use function array_filter;
use function array_slice;
use function ceil;
use function count;
use function end;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function hash_file;
use function implode;
use function in_array;
use function is_array;
use function ltrim;
use function max;
use function opcache_get_status;
use function pcntl_fork;
use function pcntl_waitpid;
use function php_strip_whitespace;
use function preg_match_all;
use function preg_replace;
use function serialize;
use function sprintf;
use function str_contains;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function unserialize;

final class SymbolFinderInFiles
{

	public function __construct(private PhpFileCleaner $cleaner)
	{
	}

	private const PARALLEL_THRESHOLD = 400;

	private const PARALLEL_WORKERS = 8;

	/**
	 * Hashes every file and extracts symbols from the ones whose hash no longer
	 * matches the given cached hash (always, when null is given). Unreadable
	 * files are omitted from the result.
	 *
	 * @param array<string, string|null> $filesWithCachedHashes file => cached hash or null
	 * @return array<string, array{string, array{string[], string[], string[]}|null}> file => [hash, symbols or null when the cached hash is still valid]
	 */
	public function hashAndFindSymbols(array $filesWithCachedHashes, bool $supportsEnums): array
	{
		if (
			count($filesWithCachedHashes) >= self::PARALLEL_THRESHOLD
			&& function_exists('pcntl_fork')
			&& function_exists('pcntl_waitpid')
			&& !$this->isOpcacheEnabled()
		) {
			return $this->hashAndFindSymbolsParallel($filesWithCachedHashes, $supportsEnums);
		}

		return $this->hashAndFindSymbolsSerial($filesWithCachedHashes, $supportsEnums);
	}

	/**
	 * Forked children may autoload classes; concurrent population of OPcache
	 * shared memory from forks corrupts it (same constraint as ForkParallelChecker).
	 */
	private function isOpcacheEnabled(): bool
	{
		if (!function_exists('opcache_get_status')) {
			return false;
		}

		$status = opcache_get_status(false);
		if ($status === false) {
			return false;
		}

		return ($status['opcache_enabled'] ?? false) === true;
	}

	/**
	 * @param array<string, string|null> $filesWithCachedHashes
	 * @return array<string, array{string, array{string[], string[], string[]}|null}>
	 */
	private function hashAndFindSymbolsSerial(array $filesWithCachedHashes, bool $supportsEnums): array
	{
		$result = [];
		foreach ($filesWithCachedHashes as $file => $cachedHash) {
			$hash = @hash_file('xxh128', $file);
			if ($hash === false) {
				continue;
			}

			if ($cachedHash !== null && $hash === $cachedHash) {
				$result[$file] = [$hash, null];
				continue;
			}

			$result[$file] = [$hash, $this->findSymbolsInFile($file, $supportsEnums)];
		}

		return $result;
	}

	/**
	 * Forks short-lived children that each hash and scan a slice of the file
	 * list and hand the result back through a temporary file. Chunks that fail
	 * to fork or to round-trip are processed serially in this process.
	 *
	 * @param array<string, string|null> $filesWithCachedHashes
	 * @return array<string, array{string, array{string[], string[], string[]}|null}>
	 */
	private function hashAndFindSymbolsParallel(array $filesWithCachedHashes, bool $supportsEnums): array
	{
		$chunks = array_chunk($filesWithCachedHashes, max(1, (int) ceil(count($filesWithCachedHashes) / self::PARALLEL_WORKERS)), true);

		$children = [];
		foreach ($chunks as $chunkIndex => $chunk) {
			$resultFile = tempnam(sys_get_temp_dir(), 'phpstan-symbols-');
			if ($resultFile === false) {
				break;
			}

			$pid = pcntl_fork();
			if ($pid === -1) {
				@unlink($resultFile);
				break;
			}

			if ($pid === 0) {
				// child
				@file_put_contents($resultFile, serialize($this->hashAndFindSymbolsSerial($chunk, $supportsEnums)));
				exit(0);
			}

			$children[$pid] = [$resultFile, $chunkIndex];
		}

		$result = [];
		$processedChunks = [];
		foreach ($children as $pid => [$resultFile, $chunkIndex]) {
			pcntl_waitpid($pid, $status);
			$contents = @file_get_contents($resultFile);
			@unlink($resultFile);
			if ($contents === false) {
				continue;
			}

			$chunkResult = @unserialize($contents);
			if (!is_array($chunkResult)) {
				continue;
			}

			foreach ($chunkResult as $file => $fileResult) {
				$result[$file] = $fileResult;
			}
			$processedChunks[$chunkIndex] = true;
		}

		// fill in whatever did not make it through a child
		foreach (array_diff_key($chunks, $processedChunks) as $chunk) {
			foreach ($this->hashAndFindSymbolsSerial($chunk, $supportsEnums) as $file => $fileResult) {
				$result[$file] = $fileResult;
			}
		}

		return $result;
	}

	/**
	 * Inspired by Composer\Autoload\ClassMapGenerator::findClasses()
	 * @link https://github.com/composer/composer/blob/45d3e133a4691eccb12e9cd6f9dfd76eddc1906d/src/Composer/Autoload/ClassMapGenerator.php#L216
	 *
	 * @return array{string[], string[], string[]}
	 */
	private function findSymbolsInFile(string $file, bool $supportsEnums): array
	{
		$contents = @php_strip_whitespace($file);
		if ($contents === '') {
			return [[], [], []];
		}

		$extraTypes = $supportsEnums ? '|enum' : '';
		$matchResults = (bool) preg_match_all(sprintf('{\b(?:(?:class|interface|trait|const|function%s)\s)|(?:define\s*\()}i', $extraTypes), $contents, $matches);
		if (!$matchResults) {
			return [[], [], []];
		}

		$contents = $this->cleaner->clean($contents, count($matches[0]));

		preg_match_all(sprintf('{
			(?:
				\b(?<![\$:>])(?:
					(?: (?P<type>class|interface|trait%s) \s++ (?P<name>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\-]*+) )
					| (?: (?P<function>function) \s++ (?:&\s*)? (?P<fname>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\-]*+) \s*+ [&\(] )
					| (?: (?P<constant>const) \s++ (?P<cname>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\-]*+) \s*+ [^;] )
					| (?: (?:\\\)? (?P<define>define) \s*+ \( \s*+ [\'"] (?P<dname>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:[\\\\]{1,2}[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+) )
					| (?: (?P<ns>namespace) (?P<nsname>\s++[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\s*+\\\\\s*+[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+)? \s*+ [\{;] )
				)
			)
		}ix', $extraTypes), $contents, $matches);

		$classes = [];
		$functions = [];
		$constants = [];
		$namespace = '';

		for ($i = 0, $len = count($matches['type']); $i < $len; $i++) {
			if (isset($matches['ns'][$i]) && $matches['ns'][$i] !== '') {
				$namespace = preg_replace('~\s+~', '', strtolower($matches['nsname'][$i])) . '\\';
				continue;
			}

			if ($matches['function'][$i] !== '') {
				$functions[] = strtolower(ltrim($namespace . $matches['fname'][$i], '\\'));
				continue;
			}

			if ($matches['constant'][$i] !== '') {
				$constants[] = self::normalizeConstantName(ltrim($namespace . $matches['cname'][$i], '\\'));
			}

			if ($matches['define'][$i] !== '') {
				$constants[] = self::normalizeConstantName($matches['dname'][$i]);
				continue;
			}

			$name = $matches['name'][$i];

			// skip anon classes extending/implementing
			if (in_array($name, ['extends', 'implements'], true)) {
				continue;
			}

			$classes[] = strtolower(ltrim($namespace . $name, '\\'));
		}

		return [
			$classes,
			$functions,
			$constants,
		];
	}

	private static function normalizeConstantName(string $name): string
	{
		if (!str_contains($name, '\\')) {
			return $name;
		}

		$nameParts = array_filter(explode('\\', $name), static fn ($part) => $part !== '');
		return strtolower(implode('\\', array_slice($nameParts, 0, -1))) . '\\' . end($nameParts);
	}

}
