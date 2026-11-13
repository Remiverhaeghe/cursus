<?php

namespace App\Tests\Controller;

use App\Controller\HealthController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(HealthController::class)]
final class HealthControllerTest extends WebTestCase
{
    public function testHealthReturnsOk(): void
    {
        $vClient = static::createClient();
        $vClient->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $vClient->getResponse()->getContent());
    }
}
