<?php declare(strict_types = 1);

namespace PHPStan\Parallel;

use PHPStan\ShouldNotHappenException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ProcessPoolTest extends TestCase
{

	public function testTryGetProcessReturnsNullForAnUnknownIdentifier(): void
	{
		$pool = $this->createPool();

		$this->assertNull($pool->tryGetProcess('unknown'));
	}

	public function testGetProcessThrowsForAnUnknownIdentifier(): void
	{
		$pool = $this->createPool();

		$this->expectException(ShouldNotHappenException::class);
		$pool->getProcess('unknown');
	}

	public function testTryGetProcessReturnsAnAttachedProcess(): void
	{
		$pool = $this->createPool();
		$process = $this->createMock(Process::class);
		$pool->attachProcess('worker-1', $process);

		$this->assertSame($process, $pool->tryGetProcess('worker-1'));
		$this->assertNull($pool->tryGetProcess('worker-2'));
	}

	private function createPool(): ProcessPool
	{
		return (new ReflectionClass(ProcessPool::class))->newInstanceWithoutConstructor();
	}

}
