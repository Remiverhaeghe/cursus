<?php

namespace App\Command;

use App\Audit\AuditLog;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Crée un compte sans passer par la démo : le mot de passe provisoire est affiché une seule fois. */
#[AsCommand(name: 'app:user:create', description: 'Crée un compte (école, tuteur ou apprenti) avec un mot de passe provisoire')]
final class CreateUserCommand
{
    public const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly UserRepository $aUsers,
        private readonly EntityManagerInterface $aEntityManager,
        private readonly UserPasswordHasherInterface $aPasswordHasher,
        private readonly AuditLog $aAudit,
    ) {
    }

    /** Accepte « school », « tutor », « apprentice » ou la valeur complète du profil. */
    public static function parseRole(string $pName): ?UserRole
    {
        $rRole = UserRole::tryFrom(strtoupper(trim($pName)));
        if (null === $rRole) {
            $rRole = UserRole::tryFrom('ROLE_'.strtoupper(trim($pName)));
        }

        return $rRole;
    }

    public function __invoke(
        SymfonyStyle $pIo,
        #[Argument(description: 'Adresse e-mail du compte')]
        string $email,
        #[Argument(description: 'Profil : school, tutor ou apprentice')]
        string $role,
        #[Argument(description: 'Prénom')]
        string $firstname,
        #[Argument(description: 'Nom')]
        string $lastname,
        #[Option(description: 'Mot de passe provisoire (sinon tiré au hasard et affiché)')]
        ?string $password = null,
    ): int {
        $vRole = self::parseRole($role);
        if (null === $vRole) {
            $pIo->error(\sprintf('Profil inconnu « %s » : school, tutor ou apprentice.', $role));

            return Command::INVALID;
        }
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $pIo->error('Adresse e-mail invalide.');

            return Command::INVALID;
        }
        if (null !== $this->aUsers->findOneByEmail($email)) {
            $pIo->error('Un compte existe déjà avec cette adresse.');

            return Command::FAILURE;
        }
        if (null !== $password && mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $pIo->error(\sprintf('Le mot de passe doit faire au moins %d caractères.', self::MIN_PASSWORD_LENGTH));

            return Command::INVALID;
        }

        $vGenerated = null === $password;
        $vPassword = $password ?? bin2hex(random_bytes(9));
        $vUser = new User($email, $vRole, $firstname, $lastname);
        $vUser->setPassword($this->aPasswordHasher->hashPassword($vUser, $vPassword));
        $this->aEntityManager->persist($vUser);
        $this->aAudit->record(AuditLog::ACCOUNT_CREATED, null, \sprintf('Compte %s créé en ligne de commande', $vRole->label()));
        $this->aEntityManager->flush();

        $pIo->success(\sprintf('Compte %s créé pour %s.', $vRole->label(), $vUser->getEmail()));
        if ($vGenerated) {
            $pIo->writeln('Mot de passe provisoire (affiché une seule fois) : '.$vPassword);
        }
        if ($vRole->requiresTotp()) {
            $pIo->note('La double authentification sera demandée à la première connexion.');
        }

        return Command::SUCCESS;
    }
}
