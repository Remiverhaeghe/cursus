# Changelog

## Non publié

### Lot 7 : comptes et mise en ligne
- Mot de passe oublié : lien à usage unique valable 30 minutes, seule l'empreinte du jeton en base, même réponse pour une adresse inconnue, 3 demandes par 15 minutes
- Nouveau mot de passe de 12 caractères au moins, refusé s'il figure dans une fuite connue ; les autres sessions du compte sont fermées
- Session fermée après 30 minutes d'inactivité, avec un message à la reconnexion
- « Mon compte » : export JSON de ses données, suppression du compte par anonymisation (refusée tant qu'une formation ou un contrat en cours en dépend)
- Pages légales (mentions, confidentialité, conditions), remplies depuis la configuration sans rien inventer
- Pages d'erreur 403, 404 et 500 personnalisées ; le nonce CSP suit les sous-requêtes des pages d'erreur
- `robots.txt` et `noindex` pour la démo, `security.txt`, `SECURITY.md`, description des pages
- Accessibilité : focus visible, contrastes de l'orange et du vert corrigés, tableaux qui défilent sur mobile, styles d'impression
- Documentation : `docs/decisions.md`, `docs/securite.md` (revue de menaces), `docs/mise-en-ligne.md`, `ROADMAP.md`

### Lot 6 : démo publique et documentation
- En-têtes de sécurité sur chaque réponse : CSP stricte avec un nonce par requête pour les scripts de l'importmap, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
- Mode démo documenté : `TOTP_REQUIRED=0` et remise à zéro nocturne des fixtures
- README complet : profils, comptes de démonstration, règles de progression, organisation du code, sécurité
- Suppression du journal de bienvenue d'AssetMapper dans `app.js`

### Lot 5 : tableaux de bord et progression
- Calcul de progression sans base de données (`src/Progress`) : niveau courant = dernière évaluation, moyenne par bloc et par apprenti, compétences non évaluées comptées à 0 et signalées, reste à valider (niveau inférieur à 3)
- École : progression de chaque apprenti par promotion et moyenne de la promotion
- Tuteur : ses apprentis seulement, avec progression, compétences validées et non évaluées
- Apprenti : progression par bloc et par compétence, courbe d'évolution (SVG sans JavaScript), reste à valider, lien vers l'historique
- Page de suivi enrichie de la progression détaillée
- Évaluations de plusieurs contrats chargées en une requête

### Lot 4 : évaluations école et entreprise
- Évaluations datées (`Evaluation`) : niveau de 0 à 4 (`CompetenceLevel`), contexte école ou entreprise (`EvaluationContext`), commentaire facultatif ; une évaluation n'est jamais modifiée
- Règles vérifiées à la création : compétence de la formation de l'apprenti, l'école note « école » et le tuteur « entreprise », l'évaluateur suit bien le contrat
- Page de suivi `/suivi/{id}` : historique du plus récent au plus ancien, formulaire pour l'école responsable et le tuteur, consultation seule pour l'apprenti ; accès testé pour chaque profil
- Valeurs forgées refusées (niveau 5 ou négatif, compétence d'une autre formation)
- Journal de chaque évaluation enregistrée (identifiants seulement)
- Fixtures : historique fictif sur plusieurs mois

### Lot 3 : promotions, entreprises et contrats
- Entreprises d'accueil (`Company`), promotions par formation (`Cohort`) et contrats (`Apprenticeship`) reliant apprenti, tuteur, entreprise et promotion
- Un contrat refuse un apprenti ou un tuteur du mauvais profil, et une fin qui ne suit pas le début
- Pages école : entreprises, ouverture d'une promotion depuis sa formation, rattachement d'un apprenti (seuls les comptes du bon profil sont proposés)
- Voter `APPRENTICESHIP_VIEW` / `APPRENTICESHIP_EVALUATE` : école responsable, tuteur du contrat et apprenti concerné (consultation seule), testé pour 7 profils et 2 actions
- `FORMATION_MANAGE` couvre aussi les promotions
- Fixtures : deux entreprises, deux promotions, quatre contrats fictifs

### Lot 2 : formations et référentiel
- Formations (`Formation`) rattachées à un responsable de l'école, blocs (`CompetenceBlock`) numérotés dans l'ordre de création, compétences (`Competence`)
- Pages école : liste et création des formations, référentiel d'une formation avec ajout de blocs et de compétences
- Voter `FORMATION_MANAGE` : seul le responsable de la formation la consulte et la modifie, testé pour chaque profil sur la formation, le bloc et la compétence
- Formulaires branchés sur des objets de saisie typés (`src/Form/Data`), entités toujours créées valides
- Fixtures : deux formations fictives, une par responsable

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
