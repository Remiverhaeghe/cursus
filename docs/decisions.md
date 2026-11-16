# Décisions d'architecture

Une entrée par choix qui ne se devine pas en lisant le code. Une décision qui change ajoute une entrée, elle ne réécrit pas l'ancienne.

## 2026-11-13 · Attributs préfixés et nommage des colonnes

Contexte : la convention du projet préfixe les attributs par `a` (`$aFirstName`). Décision : une stratégie de nommage Doctrine (`App\Doctrine\PrefixedNamingStrategy`) retire le préfixe côté base (`first_name`). Écarté : un `name:` sur chaque colonne, plus long et facile à oublier.

## 2026-11-13 · Formulaires sur objets de saisie

Contexte : les entités vérifient leurs règles dans le constructeur. Décision : chaque formulaire remplit un petit objet typé (`src/Form/Data`), et l'entité n'est créée qu'à partir de valeurs validées. Écarté : des formulaires branchés sur les entités, qui obligeraient à les rendre modifiables et parfois invalides.

## 2026-11-13 · Double authentification obligatoire pour l'école et les tuteurs

Contexte : ces deux profils lisent et écrivent les évaluations de plusieurs apprentis. Décision : TOTP obligatoire à la première connexion, facultatif pour l'apprenti, désactivable par `TOTP_REQUIRED=0` pour une démo à comptes partagés. Écarté : TOTP facultatif pour tous.

## 2026-11-13 · Évaluations jamais modifiées

Contexte : le cahier des charges demande l'historique de progression. Décision : une évaluation est une ligne datée, sans modification ni suppression ; le niveau courant est la dernière en date. Une erreur se corrige par une nouvelle évaluation.

## 2026-11-13 · Progression : non évaluée comptée à 0, validée à partir de 3

Contexte : règle du cahier des charges pour les moyennes de bloc. Décision : une compétence jamais évaluée compte pour 0 et est signalée ; le seuil de validation est 3 (« Acquise »), dans `CompetenceLevel::VALIDATION_THRESHOLD`.

## 2026-11-13 · CSP stricte, sans style en ligne

Décision : scripts autorisés par un nonce par requête (repris par les sous-requêtes des pages d'erreur), aucun `style=""` ni script en ligne. Les barres de progression utilisent `<progress>` et les courbes du SVG avec attributs.

## 2026-11-15 · Suppression de compte par anonymisation

Contexte : les évaluations gardent leur évaluateur et les contrats leur apprenti. Décision : supprimer un compte efface son identité (nom, adresse, mot de passe, TOTP) et garde la ligne. La suppression est refusée tant que le compte gère une formation ou a un contrat qui finit aujourd'hui ou plus tard. Écarté : la suppression physique, qui casserait l'historique des autres apprentis.

## 2026-11-15 · Démo non indexée

Contexte : démonstration à données fictives, accessible depuis le portfolio. Décision : `robots.txt` interdit tout et chaque page porte `noindex` ; pas de sitemap ni d'image d'aperçu. À revoir si une page de présentation publique est ajoutée.

## 2026-11-15 · Tests hors debug et transactions annulées

Contexte : la suite fonctionnelle prenait 3 min 33 et Infection sautait la plupart des mutants, faute de temps. Mesure : le mode debug vérifie des centaines de fichiers à chaque démarrage du noyau, très lent à travers le dossier partagé de Docker pour Windows ; hors debug, la même suite prend 53 s. Décision : `APP_DEBUG=0` dans `phpunit.dist.xml`, cache de test vidé par `tests/bootstrap.php` quand un fichier du projet change, fixtures chargées une fois et chaque test annulé par `dama/doctrine-test-bundle`. Écarté : sortir `vendor/` du dossier partagé, qui priverait l'éditeur des sources des dépendances.
