<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Competence;
use App\Entity\CompetenceBlock;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Formation::class)]
#[CoversClass(CompetenceBlock::class)]
#[CoversClass(Competence::class)]
final class ReferentialTest extends TestCase
{
    public function testOnlySchoolCanManageAFormation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Formation('BTS', 'BTS', new User('tuteur@cursus.test', UserRole::Tutor, 'Léo', 'Martin'));
    }

    public function testFormationKeepsItsManagerAndBlocksInOrder(): void
    {
        $vManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vFormation = new Formation('Développeur web', 'Titre professionnel', $vManager);

        $vFirst = new CompetenceBlock($vFormation, 'B1', 'Front-end');
        $vSecond = new CompetenceBlock($vFormation, 'B2', 'Back-end');

        self::assertNull($vFormation->getId());
        self::assertSame('Développeur web', $vFormation->getName());
        self::assertSame('Développeur web', (string) $vFormation);
        self::assertSame('Titre professionnel', $vFormation->getDiploma());
        self::assertSame($vManager, $vFormation->getManager());
        self::assertTrue($vFormation->isManagedBy($vManager));
        self::assertFalse($vFormation->isManagedBy(new User('autre@cursus.test', UserRole::School, 'Paul', 'Roux')));
        self::assertSame([$vFirst, $vSecond], $vFormation->getBlocks()->toArray());
        self::assertSame(1, $vFirst->getPosition());
        self::assertSame(2, $vSecond->getPosition());
        self::assertSame(3, $vFormation->nextBlockPosition());
    }

    public function testCompetencesBelongToTheirFormation(): void
    {
        $vManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vFormation = new Formation('Développeur web', 'Titre professionnel', $vManager);
        $vBlock = new CompetenceBlock($vFormation, 'B1', 'Front-end');
        $vOtherBlock = new CompetenceBlock($vFormation, 'B2', 'Back-end');
        $vModel = new Competence($vBlock, 'C1.1', 'Maquetter');
        $vInterface = new Competence($vBlock, 'C1.2', 'Intégrer');
        $vDatabase = new Competence($vOtherBlock, 'C2.1', 'Base de données');
        $vForeign = new Competence(new CompetenceBlock(new Formation('Autre', 'BTS', $vManager), 'B1', 'Autre'), 'C1.1', 'Autre');

        self::assertSame([$vModel, $vInterface, $vDatabase], $vFormation->getCompetences());
        self::assertTrue($vFormation->hasCompetence($vDatabase));
        self::assertFalse($vFormation->hasCompetence($vForeign));
        self::assertSame($vFormation, $vModel->getFormation());
        self::assertSame($vBlock, $vModel->getBlock());
        self::assertSame([$vModel, $vInterface], $vBlock->getCompetences()->toArray());
    }

    public function testLabels(): void
    {
        $vFormation = new Formation('Développeur web', 'Titre professionnel', new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin'));
        $vBlock = new CompetenceBlock($vFormation, 'B1', 'Front-end');
        $vCompetence = new Competence($vBlock, 'C1.1', 'Maquetter');

        self::assertNull($vBlock->getId());
        self::assertSame($vFormation, $vBlock->getFormation());
        self::assertSame('B1', $vBlock->getCode());
        self::assertSame('Front-end', $vBlock->getTitle());
        self::assertSame('B1 · Front-end', (string) $vBlock);
        self::assertSame('B1 · Front-end', $vBlock->label());
        self::assertNull($vCompetence->getId());
        self::assertSame('C1.1', $vCompetence->getCode());
        self::assertSame('Maquetter', $vCompetence->getLabel());
        self::assertSame('C1.1 · Maquetter', (string) $vCompetence);
    }
}
