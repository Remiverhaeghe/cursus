<?php

namespace App\Tests\Unit\Form;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\Data\CompetenceBlockData;
use App\Form\Data\CompetenceData;
use App\Form\Data\FormationData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormationData::class)]
#[CoversClass(CompetenceBlockData::class)]
#[CoversClass(CompetenceData::class)]
final class InputDataTest extends TestCase
{
    public function testReferentialInputsAreTrimmed(): void
    {
        $vManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vFormationData = new FormationData();
        $vFormationData->name = '  Développeur web ';
        $vFormationData->diploma = ' Titre professionnel ';
        $vBlockData = new CompetenceBlockData();
        $vBlockData->code = ' B1 ';
        $vBlockData->title = ' Front-end ';
        $vCompetenceData = new CompetenceData();
        $vCompetenceData->code = ' C1.1 ';
        $vCompetenceData->label = ' Maquetter ';

        $vFormation = $vFormationData->toFormation($vManager);
        $vBlock = $vBlockData->toBlock($vFormation);
        $vCompetence = $vCompetenceData->toCompetence($vBlock);

        self::assertSame('Développeur web', $vFormation->getName());
        self::assertSame('Titre professionnel', $vFormation->getDiploma());
        self::assertSame($vManager, $vFormation->getManager());
        self::assertSame('B1', $vBlock->getCode());
        self::assertSame('Front-end', $vBlock->getTitle());
        self::assertSame($vFormation, $vBlock->getFormation());
        self::assertSame('C1.1', $vCompetence->getCode());
        self::assertSame('Maquetter', $vCompetence->getLabel());
        self::assertSame($vBlock, $vCompetence->getBlock());
    }
}
