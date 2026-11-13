# Changelog

## Non publié

### Lot 1 : comptes et rôles
- Comptes école, tuteur et apprenti (`User`), mots de passe hachés en Argon2id
- Connexion par formulaire avec jeton CSRF, limitation à 5 essais par 15 minutes, message d'erreur identique pour un compte inconnu ou un mauvais mot de passe
- Double authentification TOTP : obligatoire pour l'école et les tuteurs (`TOTP_REQUIRED`), facultative pour les apprentis, activation par code QR
- Espaces cloisonnés `/ecole`, `/tuteur`, `/apprenti`, testés pour chaque couple profil et espace
- Journal des connexions réussies et échouées, sans donnée sensible
- Colonnes nommées sans le préfixe `a` des attributs (`PrefixedNamingStrategy`)
- Fixtures de comptes fictifs (mot de passe commun `demo-cursus`)

### Lot 0 : squelette
- Projet Symfony 8.1 sur FrankenPHP et PostgreSQL, ports locaux 8080 et 8443
- Dépendances : Doctrine, Security, Twig, Form, Validator, Mailer, Rate limiter, AssetMapper, TOTP (scheb/2fa)
- Qualité : PHPUnit, PHPStan niveau 6, PHP-CS-Fixer, Infection (seuil 95 %), Gitleaks
- Route `/health` pour la CI et la supervision
- CI GitHub Actions et Dependabot adaptés de nas-monitor
- README, licence MIT à mon nom
