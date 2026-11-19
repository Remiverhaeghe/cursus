<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Enum\UserRole;
use App\Security\Voter\ExchangeVoter;
use App\Tests\Support\ContractFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/** Un test par couple profil et action sur les échanges d'un contrat. */
#[CoversClass(ExchangeVoter::class)]
final class ExchangeVoterTest extends VoterTestCase
{
    private const int GRANTED = VoterInterface::ACCESS_GRANTED;
    private const int DENIED = VoterInterface::ACCESS_DENIED;

    /** @return iterable<string, array{string, string, int}> */
    public static function matrix(): iterable
    {
        $vExpectations = [
            'école responsable' => [self::GRANTED, self::DENIED],
            'autre école' => [self::DENIED, self::DENIED],
            'tuteur du contrat' => [self::GRANTED, self::GRANTED],
            'autre tuteur' => [self::DENIED, self::DENIED],
            'apprenti concerné' => [self::GRANTED, self::GRANTED],
            'autre apprenti' => [self::DENIED, self::DENIED],
            'anonyme' => [self::DENIED, self::DENIED],
        ];
        foreach ($vExpectations as $vProfile => [$vView, $vPost]) {
            yield $vProfile.' lit' => [$vProfile, ExchangeVoter::VIEW, $vView];
            yield $vProfile.' écrit' => [$vProfile, ExchangeVoter::POST, $vPost];
        }
    }

    #[DataProvider('matrix')]
    public function testVote(string $pProfile, string $pAttribute, int $pExpected): void
    {
        $vData = new ContractFactory();
        $vUsers = [
            'école responsable' => $vData->aManager,
            'autre école' => new User('autre@cursus.test', UserRole::School, 'Paul', 'Roux'),
            'tuteur du contrat' => $vData->aTutor,
            'autre tuteur' => new User('autre.tuteur@cursus.test', UserRole::Tutor, 'Sophie', 'Garnier'),
            'apprenti concerné' => $vData->aApprentice,
            'autre apprenti' => new User('autre.apprenti@cursus.test', UserRole::Apprentice, 'Emma', 'Petit'),
            'anonyme' => null,
        ];

        $vVote = (new ExchangeVoter())->vote(self::token($vUsers[$pProfile]), $vData->aApprenticeship, [$pAttribute]);

        self::assertSame($pExpected, $vVote);
    }

    public function testAbstainsOnOtherAttributesAndSubjects(): void
    {
        $vData = new ContractFactory();
        $vVoter = new ExchangeVoter();

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vVoter->vote(self::token($vData->aTutor), $vData->aTutor, [ExchangeVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vVoter->vote(self::token($vData->aTutor), $vData->aApprenticeship, ['AUTRE']));
    }
}
