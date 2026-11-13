<?php

namespace App\Tests;

use App\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(Kernel::class)]
final class KernelTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        $vKernel = self::bootKernel();

        self::assertSame('test', $vKernel->getEnvironment());
    }
}
