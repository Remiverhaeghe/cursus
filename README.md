# Cursus

Suivi des compétences des apprentis, partagé entre l'école, l'entreprise d'accueil et l'apprenti.

Projet personnel écrit de zéro à partir d'un besoin fonctionnel : aucun code d'un autre dépôt n'est repris. Il sert de démonstration publique, avec des **données fictives uniquement**.

## Ce que la première version permet

| Profil | Ce qu'il fait |
|---|---|
| École | Crée les formations et leur référentiel, gère les promotions, évalue les compétences vues en cours, suit la progression |
| Tuteur en entreprise | Voit ses apprentis, évalue les compétences mises en pratique, commente |
| Apprenti | Consulte sa progression par compétence et par bloc, et l'historique de ses évaluations |

Chaque évaluation est une ligne datée (niveau de 0 à 4) : rien n'est écrasé, l'historique donne la courbe de progression.

## Stack

Symfony 8.1, PHP 8.5, PostgreSQL 16, FrankenPHP, Docker Compose. Qualité : PHPUnit, PHPStan, PHP-CS-Fixer, Infection, Gitleaks, CI GitHub Actions.

## Lancer le projet

```
docker compose up -d --wait
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

L'application répond sur https://localhost:8443 (HTTP sur le port 8080). Les ports sont définis dans `.env` ; modifie-les s'ils sont déjà pris sur ta machine. Les courriers de développement arrivent dans Mailpit.

## Contrôles qualité

```
docker compose exec php bin/phpunit
docker compose exec php vendor/bin/phpstan analyse
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec php vendor/bin/infection --threads=1
```

La CI rejoue ces contrôles à chaque push et chaque pull request.

## Avancement

Voir le [CHANGELOG](CHANGELOG.md). Lot en cours : 0, squelette et qualité.

## Licence

[MIT](LICENSE)
