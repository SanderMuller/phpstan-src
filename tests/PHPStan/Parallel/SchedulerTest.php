<?php declare(strict_types = 1);

namespace PHPStan\Parallel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function array_fill;
use function array_map;
use function count;

class SchedulerTest extends TestCase
{

	public static function dataSchedule(): array
	{
		return [
			[
				1,
				16,
				1,
				50,
				115,
				1,
				[39, 38, 38],
			],
			[
				16,
				16,
				1,
				30,
				124,
				5,
				[25, 25, 25, 25, 24],
			],
			[
				16,
				3,
				1,
				30,
				124,
				3,
				[25, 25, 25, 25, 24],
			],
			[
				16,
				16,
				1,
				10,
				298,
				16,
				[10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 9, 9],
			],
			[
				16,
				16,
				2,
				30,
				124,
				2,
				[25, 25, 25, 25, 24],
			],
			[
				16,
				16,
				2,
				20,
				1,
				1,
				[1],
			],
		];
	}

	/**
	 * @param positive-int $jobSize
	 * @param positive-int $maximumNumberOfProcesses
	 * @param positive-int $minimumNumberOfJobsPerProcess
	 * @param 0|positive-int $numberOfFiles
	 * @param array<int> $expectedJobSizes
	 */
	#[DataProvider('dataSchedule')]
	public function testSchedule(
		int $cpuCores,
		int $maximumNumberOfProcesses,
		int $minimumNumberOfJobsPerProcess,
		int $jobSize,
		int $numberOfFiles,
		int $expectedNumberOfProcesses,
		array $expectedJobSizes,
	): void
	{
		$files = array_fill(0, $numberOfFiles, 'file.php');
		$scheduler = new Scheduler($jobSize, $maximumNumberOfProcesses, $minimumNumberOfJobsPerProcess);
		$schedule = $scheduler->scheduleWork($cpuCores, $files);

		$this->assertSame($expectedNumberOfProcesses, $schedule->getNumberOfProcesses());
		$jobSizes = array_map(static fn (array $job): int => count($job), $schedule->getJobs());
		$this->assertSame($expectedJobSizes, $jobSizes);
	}

}
