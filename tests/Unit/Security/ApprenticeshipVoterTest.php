<?php

namespace App\Tests\Unit\Security;

use App\Entity\Apprenticeship;
use App\Entity\Cohort;
use App\Entity\Company;
use App\Entity\Formation;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\Voter\ApprenticeshipVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/** Un test par couple profil et action sur le contrat d'un apprenti. */
#[CoversClass(ApprenticeshipVoter::class)]
final class ApprenticeshipVoterTest extends VoterTestCase
{
    private const int GRANTED = VoterInterface::ACCESS_GRANTED;
    private const int DENIED = VoterInterface::ACCESS_DENIED;

    /** @return iterable<string, array{string, string, int}> */
    public static function matrix(): iterable
    {
        $vExpectations = [
            'école responsable' => [self::GRANTED, self::GRANTED],
            'autre école' => [self::DENIED, self::DENIED],
            'tuteur du contrat' => [self::GRANTED, self::GRANTED],
            'autre tuteur' => [self::DENIED, self::DENIED],
            'apprenti concerné' => [self::GRANTED, self::DENIED],
            'autre apprenti' => [self::DENIED, self::DENIED],
            'anonyme' => [self::DENIED, self::DENIED],
        ];
        foreach ($vExpectations as $vProfile => [$vView, $vEvaluate]) {
            yield $vProfile.' consulte' => [$vProfile, ApprenticeshipVoter::VIEW, $vView];
            yield $vProfile.' évalue' => [$vProfile, ApprenticeshipVoter::EVALUATE, $vEvaluate];
        }
    }

    #[DataProvider('matrix')]
    public function testVote(string $pProfile, string $pAttribute, int $pExpected): void
    {
        $vManager = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vApprentice = new User('apprenti@cursus.test', UserRole::Apprentice, 'Lucas', 'Blanc');
        $vTutor = new User('tuteur@cursus.test', UserRole::Tutor, 'Marc', 'Petit');
        $vApprenticeship = new Apprenticeship(
            $vApprentice,
            $vTutor,
            new Company('Atelier Lumen', 'Lille'),
            new Cohort(new Formation('Web', 'Titre', $vManager), 'Promotion 2026', 2026),
            new \DateTimeImmutable('2026-09-01'),
            new \DateTimeImmutable('2027-08-31'),
        );
        $vUsers = [
            'école responsable' => $vManager,
            'autre école' => new User('autre@cursus.test', UserRole::School, 'Paul', 'Roux'),
            'tuteur du contrat' => $vTutor,
            'autre tuteur' => new User('autre.tuteur@cursus.test', UserRole::Tutor, 'Sophie', 'Garnier'),
            'apprenti concerné' => $vApprentice,
            'autre apprenti' => new User('autre.apprenti@cursus.test', UserRole::Apprentice, 'Emma', 'Petit'),
            'anonyme' => null,
        ];

        $vVote = (new ApprenticeshipVoter())->vote(self::token($vUsers[$pProfile]), $vApprenticeship, [$pAttribute]);

        self::assertSame($pExpected, $vVote);
    }

    public function testAbstainsOnOtherAttributesAndSubjects(): void
    {
        $vUser = new User('ecole@cursus.test', UserRole::School, 'Léa', 'Martin');
        $vVoter = new ApprenticeshipVoter();

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vVoter->vote(self::token($vUser), $vUser, [ApprenticeshipVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vVoter->vote(self::token($vUser), $vUser, ['AUTRE']));
    }
}
