<?php

namespace App\Tests\Unit\Security;

use App\Entity\Competence;
use App\Entity\CompetenceBlock;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\Voter\FormationVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/** Un test par couple profil et donnée du référentiel. */
#[CoversClass(FormationVoter::class)]
final class FormationVoterTest extends VoterTestCase
{
    /** @return iterable<string, array{string, string, int}> */
    public static function matrix(): iterable
    {
        $vProfiles = [
            'responsable' => VoterInterface::ACCESS_GRANTED,
            'autre école' => VoterInterface::ACCESS_DENIED,
            'tuteur' => VoterInterface::ACCESS_DENIED,
            'apprenti' => VoterInterface::ACCESS_DENIED,
            'anonyme' => VoterInterface::ACCESS_DENIED,
        ];
        foreach ($vProfiles as $vProfile => $vExpected) {
            foreach (['formation', 'bloc', 'compétence'] as $vSubject) {
                yield $vProfile.' sur '.$vSubject => [$vProfile, $vSubject, $vExpected];
            }
        }
    }

    #[DataProvider('matrix')]
    public function testManageVote(string $pProfile, string $pSubject, int $pExpected): void
    {
        $vManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vFormation = new Formation('Développeur web', 'Titre professionnel', $vManager);
        $vBlock = new CompetenceBlock($vFormation, 'B1', 'Front-end');
        $vSubjects = ['formation' => $vFormation, 'bloc' => $vBlock, 'compétence' => new Competence($vBlock, 'C1.1', 'Maquetter')];
        $vUsers = [
            'responsable' => $vManager,
            'autre école' => new User('autre@cursus.test', UserRole::School, 'Paul', 'Roux'),
            'tuteur' => new User('tuteur@cursus.test', UserRole::Tutor, 'Marc', 'Petit'),
            'apprenti' => new User('apprenti@cursus.test', UserRole::Apprentice, 'Lucas', 'Blanc'),
            'anonyme' => null,
        ];

        $vVote = (new FormationVoter())->vote(self::token($vUsers[$pProfile]), $vSubjects[$pSubject], [FormationVoter::MANAGE]);

        self::assertSame($pExpected, $vVote);
    }

    public function testAbstainsOnOtherAttributesAndSubjects(): void
    {
        $vManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vFormation = new Formation('Développeur web', 'Titre professionnel', $vManager);
        $vVoter = new FormationVoter();

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vVoter->vote(self::token($vManager), $vFormation, ['AUTRE']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vVoter->vote(self::token($vManager), $vManager, [FormationVoter::MANAGE]));
    }
}
