<?php

namespace App\Tests\Functional\DataFixtures;

use App\DataFixtures\CohortFixtures;
use App\DataFixtures\EvaluationFixtures;
use App\DataFixtures\ReferentialFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\Competence;
use App\Entity\Evaluation;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Les fixtures sont chargées une fois avant la suite, hors de PHPUnit : ce test les recharge
 * dans la transaction du test, annulée à la fin, pour vérifier la démo et compter leur code.
 */
#[CoversClass(UserFixtures::class)]
#[CoversClass(ReferentialFixtures::class)]
#[CoversClass(CohortFixtures::class)]
#[CoversClass(EvaluationFixtures::class)]
final class DemoFixturesTest extends KernelTestCase
{
    private const ENTITIES = [
        User::class,
        Formation::class,
        Competence::class,
        Company::class,
        Cohort::class,
        Apprenticeship::class,
        Evaluation::class,
    ];

    /** @return array<class-string, int> */
    private function counts(EntityManagerInterface $pEntityManager): array
    {
        $rCounts = [];
        foreach (self::ENTITIES as $vClass) {
            $rCounts[$vClass] = $pEntityManager->getRepository($vClass)->count([]);
        }

        return $rCounts;
    }

    public function testReloadingTheFixturesGivesTheSameDemo(): void
    {
        $vKernel = self::bootKernel();
        $vEntityManager = static::getContainer()->get(EntityManagerInterface::class);
        $vBefore = $this->counts($vEntityManager);

        $vTester = new CommandTester((new Application($vKernel))->find('doctrine:fixtures:load'));
        $vTester->execute([], ['interactive' => false]);
        $vEntityManager->clear();

        $vTester->assertCommandIsSuccessful();
        self::assertSame($vBefore, $this->counts($vEntityManager));
        foreach ($vBefore as $vClass => $vCount) {
            self::assertGreaterThan(0, $vCount, $vClass);
        }
        $vSchool = $vEntityManager->getRepository(User::class)->findOneBy(['aEmail' => 'claire.fontaine@cursus.test']);
        self::assertInstanceOf(User::class, $vSchool);
        self::assertSame(UserRole::School, $vSchool->getRole());
    }
}
