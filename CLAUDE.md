# Cursus

Application Symfony 8 / PostgreSQL / FrankenPHP de suivi des compétences d'apprentis (école, tuteur, apprenti). Suivre les conventions de Rémi (skill `conventions-remi`). Toujours répondre en français.

- Écriture neuve : ne jamais reprendre de code ou de gabarit d'un autre dépôt.
- Données fictives uniquement : aucune donnée réelle, ni dans les fixtures, ni dans les tests.
- Tout se lance dans Docker : `docker compose exec php bin/phpunit`, `vendor/bin/phpstan`, `vendor/bin/php-cs-fixer`, `vendor/bin/infection`.
- Cahier des charges : `D:\Projet_dev\_idees\cursus-cahier-des-charges.md` (rôles, modèle, règles, lots 0 à 6).
- Accès aux données par voters, un test par couple profil et donnée. Un correctif de bug s'accompagne d'un test.
- `README.md` et `CHANGELOG.md` à jour après chaque lot ; fichiers de moins de 1000 lignes.
