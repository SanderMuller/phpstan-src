<?php declare(strict_types = 1);

namespace PHPStan\Command;

use PHPStan\ShouldNotHappenException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\StreamOutput;
use function chmod;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function fopen;
use function function_exists;
use function implode;
use function mkdir;
use function posix_geteuid;
use function realpath;
use function rewind;
use function sprintf;
use function stream_get_contents;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;

#[Group('exec')]
#[CoversNothing]
class CommandHelperTest extends TestCase
{

	public static function dataBegin(): array
	{
		return [
			[
				'',
				'',
				__DIR__ . '/data/testIncludesExpand.neon',
				null,
				[
					'level' => 'max',
				],
				false,
			],
			[
				'',
				'Recursive included file',
				__DIR__ . '/data/1.neon',
				null,
				[],
				true,
			],
			[
				'',
				'does not exist',
				__DIR__ . '/data/nonexistent.neon',
				null,
				[],
				true,
			],
			[
				'',
				'is missing or is not readable',
				__DIR__ . '/data/containsNonexistent.neon',
				null,
				[],
				true,
			],
			[
				'',
				'These files are included multiple times',
				__DIR__ . '/../../../conf/config.level7.neon',
				'7',
				[],
				true,
			],
			[
				'',
				'These files are included multiple times',
				__DIR__ . '/../../../conf/config.level7.neon',
				'6',
				[],
				true,
			],
			[
				'',
				'These files are included multiple times',
				__DIR__ . '/../../../conf/config.level6.neon',
				'7',
				[],
				true,
			],
			[
				'',
				'',
				__DIR__ . '/data/includePhp.neon',
				null,
				[
					'level' => '3',
				],
				false,
			],
		];
	}

	/**
	 * @param mixed[] $expectedParameters
	 */
	#[DataProvider('dataBegin')]
	public function testBegin(
		string $input,
		string $expectedOutput,
		?string $projectConfigFile,
		?string $level,
		array $expectedParameters,
		bool $expectException,
	): void
	{
		$resource = fopen('php://memory', 'w', false);
		if ($resource === false) {
			throw new ShouldNotHappenException();
		}
		$output = new StreamOutput($resource);

		try {
			$result = CommandHelper::begin(
				new StringInput($input),
				$output,
				[__DIR__],
				null,
				null,
				[],
				$projectConfigFile,
				null,
				$level,
				false,
				false,
				null,
				null,
				false,
			);
			if ($expectException) {
				$this->fail();
			}
		} catch (InceptionNotSuccessfulException) {
			if (!$expectException) {
				rewind($output->getStream());
				$contents = stream_get_contents($output->getStream());
				$this->fail($contents);
			}
		}

		rewind($output->getStream());

		$contents = stream_get_contents($output->getStream());
		$this->assertStringContainsString($expectedOutput, $contents);

		if (isset($result)) {
			$parameters = $result->getContainer()->getParameters();
			foreach ($expectedParameters as $name => $expectedValue) {
				$this->assertArrayHasKey($name, $parameters);
				$this->assertSame($expectedValue, $parameters[$name]);
			}
		} else {
			$this->assertCount(0, $expectedParameters);
		}
	}

	public static function dataParameters(): array
	{
		return [
			[
				__DIR__ . '/relative-paths/root.neon',
				[
					'bootstrapFiles' => [
						realpath(__DIR__ . '/../../../stubs/runtime/ReflectionUnionType.php'),
						realpath(__DIR__ . '/../../../stubs/runtime/ReflectionAttribute.php'),
						realpath(__DIR__ . '/../../../stubs/runtime/Attribute85.php'),
						realpath(__DIR__ . '/../../../stubs/runtime/ReflectionIntersectionType.php'),
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'here.php',
					],
					'scanFiles' => [
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'here.php',
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'there.php',
						__DIR__ . DIRECTORY_SEPARATOR . 'up.php',
					],
					'scanDirectories' => [
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'src',
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths',
						realpath(__DIR__ . '/../../../') . DIRECTORY_SEPARATOR . 'conf',
					],
					'paths' => [
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'src',
					],
					'excludePaths' => [
						'analyseAndScan' => [
							__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'src',
							__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'data',
							'*/src/*/data',
						],
						'analyse' => [],
					],
				],
			],
			[
				__DIR__ . '/relative-paths/nested/nested.neon',
				[
					'scanFiles' => [
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'here.php',
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'there.php',
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'up.php',
					],
					'reportUnmatchedIgnoredErrors' => false,
					'ignoreErrors' => [
						[
							'message' => '#aaa#',
							'path' => __DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'aaa.php',
						],
						[
							'message' => '#bbb#',
							'paths' => [
								__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'aaa.php',
								__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'bbb.php',
							],
						],
					],
				],
			],
			[
				// every path written through %rootDir% and a '.' or '..' segment: NeonAdapter expands the
				// placeholder itself and normalizes the result like a plain relative entry
				__DIR__ . '/relative-paths/placeholders.neon',
				[
					'bootstrapFiles' => [
						realpath(__DIR__ . '/../../../stubs/runtime/ReflectionUnionType.php'),
						realpath(__DIR__ . '/../../../stubs/runtime/ReflectionAttribute.php'),
						realpath(__DIR__ . '/../../../stubs/runtime/Attribute85.php'),
						realpath(__DIR__ . '/../../../stubs/runtime/ReflectionIntersectionType.php'),
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'here.php',
					],
					'scanFiles' => [
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'there.php',
						__DIR__ . DIRECTORY_SEPARATOR . 'up.php',
					],
					'scanDirectories' => [
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths',
					],
					'paths' => [
						__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'src',
					],
					'excludePaths' => [
						'analyseAndScan' => [
							__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'data',
							__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'nonexistent',
						],
						'analyse' => [],
					],
					'ignoreErrors' => [
						[
							'message' => '#aaa#',
							'path' => __DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'aaa.php',
						],
						[
							'message' => '#bbb#',
							'paths' => [
								__DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'aaa.php',
							],
						],
					],
				],
			],
			[
				__DIR__ . '/exclude-paths/straightforward.neon',
				[
					'excludePaths' => [
						'analyseAndScan' => [
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test',
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test2',
						],
						'analyse' => [],
					],
				],
			],
			[
				__DIR__ . '/exclude-paths/full.neon',
				[
					'excludePaths' => [
						'analyseAndScan' => [
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test2',
						],
						'analyse' => [
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test',
						],
					],
				],
			],
			[
				__DIR__ . '/exclude-paths/including.neon',
				[
					'excludePaths' => [
						'analyseAndScan' => [
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test3',
						],
						'analyse' => [
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test',
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test2',
						],
					],
				],
			],
			[
				__DIR__ . '/exclude-paths/including-mixed.neon',
				[
					'excludePaths' => [
						'analyseAndScan' => [
							'*.blade.php',
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test2',
						],
						'analyse' => [
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test',
						],
					],
				],
			],
			[
				__DIR__ . '/exclude-paths/including-mixed-vice-versa.neon',
				[
					'excludePaths' => [
						'analyseAndScan' => [
							'*.blade.php',
							__DIR__ . DIRECTORY_SEPARATOR . 'exclude-paths' . DIRECTORY_SEPARATOR . 'test',
						],
						'analyse' => [],
					],
				],
			],
		];
	}

	/**
	 * @param array<string, mixed> $expectedParameters
	 * @throws InceptionNotSuccessfulException
	 */
	#[DataProvider('dataParameters')]
	public function testResolveParameters(
		string $configFile,
		array $expectedParameters,
	): void
	{
		$result = CommandHelper::begin(
			new StringInput(''),
			new NullOutput(),
			[__DIR__],
			null,
			null,
			[],
			$configFile,
			null,
			'0',
			false,
			false,
			null,
			null,
			false,
		);
		$parameters = $result->getContainer()->getParameters();
		foreach ($expectedParameters as $name => $expectedValue) {
			$this->assertArrayHasKey($name, $parameters);
			$this->assertSame($expectedValue, $parameters[$name]);
		}
	}

	/**
	 * --autoload-file lands in the result cache meta (executedFilesHashes) and --configuration in
	 * additionalConfigFiles; both are normalized like every other path given on the command line, so
	 * './' and '../' segments never reach the container.
	 *
	 * @throws InceptionNotSuccessfulException
	 */
	public function testAutoloadFileAndConfigurationAreNormalized(): void
	{
		$result = CommandHelper::begin(
			new StringInput(''),
			new NullOutput(),
			[__DIR__],
			null,
			__DIR__ . '/relative-paths/nested/../here.php',
			[],
			__DIR__ . '/relative-paths/nested/../root.neon',
			null,
			'0',
			false,
			false,
			null,
			null,
			false,
		);
		$parameters = $result->getContainer()->getParameters();
		$relativePaths = __DIR__ . DIRECTORY_SEPARATOR . 'relative-paths' . DIRECTORY_SEPARATOR;
		$this->assertSame($relativePaths . 'here.php', $parameters['cliAutoloadFile']);
		$this->assertContains($relativePaths . 'root.neon', $parameters['additionalConfigFiles']);
	}

	/**
	 * The default temporary directory is refused when it is a symlink another user could have planted.
	 */
	public function testDefaultTmpDirRejectsSymlink(): void
	{
		$base = $this->prepareDefaultTmpDirTest();
		symlink($base . '/target', $base . '/phpstan-' . posix_geteuid());

		[$output, $exitCode] = $this->analyseWithDefaultTmpDir($base);

		$this->assertStringContainsString('is not safe to use because it is a symbolic link', $output);
		$this->assertNotSame(0, $exitCode);
	}

	/**
	 * The default temporary directory is refused when it is writable by other users, because they could
	 * then replace a cached file with code that the next run loads.
	 */
	public function testDefaultTmpDirRejectsWorldWritableDirectory(): void
	{
		$base = $this->prepareDefaultTmpDirTest();
		$dir = $base . '/phpstan-' . posix_geteuid();
		mkdir($dir, 0700);
		chmod($dir, 0777); // an explicit chmod is needed because mkdir() is masked by the umask

		[$output, $exitCode] = $this->analyseWithDefaultTmpDir($base);

		$this->assertStringContainsString('is not safe to use because it is writable by other users', $output);
		$this->assertNotSame(0, $exitCode);
	}

	/**
	 * A fresh base holding a config-free project. The caller plants the attacker condition at
	 * "<base>/phpstan-<euid>".
	 */
	private function prepareDefaultTmpDirTest(): string
	{
		if (DIRECTORY_SEPARATOR !== '/' || !function_exists('posix_geteuid')) {
			self::markTestSkipped('The default temporary directory check runs on POSIX only.');
		}

		$base = sys_get_temp_dir() . '/phpstan-tmpdir-security-' . uniqid();
		mkdir($base . '/proj', 0700, true);
		mkdir($base . '/target', 0700, true);
		file_put_contents($base . '/proj/a.php', "<?php\n\nfunction phpstanSecurityTest(): void\n{\n}\n");

		return $base;
	}

	/**
	 * A subprocess is needed because sys_get_temp_dir() is cached per process, so this process cannot
	 * redirect its own default path. The child runs from the config-free project so it uses the default
	 * temporary directory, not this repository's phpstan.neon.dist.
	 *
	 * @return array{string, int} the combined output and the exit code
	 */
	private function analyseWithDefaultTmpDir(string $base): array
	{
		$command = sprintf(
			'cd %s && TMPDIR=%s %s %s analyse -l 0 --no-progress %s 2>&1',
			escapeshellarg($base . '/proj'),
			escapeshellarg($base),
			escapeshellarg(PHP_BINARY),
			escapeshellarg(__DIR__ . '/../../../bin/phpstan'),
			escapeshellarg($base . '/proj/a.php'),
		);
		exec($command, $outputLines, $exitCode);
		exec(sprintf('rm -rf %s', escapeshellarg($base)));

		return [implode("\n", $outputLines), $exitCode];
	}

}
