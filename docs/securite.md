# Sécurité

## Revue de menaces

Ce qui a de la valeur : les évaluations et la progression des apprentis, les comptes de l'école et des tuteurs. Les données sont fictives, mais l'application est conçue comme si elles étaient réelles.

| Qui | Ce qu'il pourrait tenter | Protection |
|---|---|---|
| Visiteur anonyme | Deviner un mot de passe, forcer le formulaire, abuser du mot de passe oublié | Limite de 5 essais par 15 minutes, TOTP pour l'école et les tuteurs, limite de 3 demandes de lien, même réponse pour une adresse inconnue |
| Apprenti connecté | Lire le suivi d'un autre apprenti, s'évaluer lui-même | Voter `APPRENTICESHIP_VIEW` et `APPRENTICESHIP_EVALUATE`, refus en `POST` |
| Tuteur connecté | Voir ou évaluer les apprentis d'un autre tuteur, noter en contexte école | Voter sur le contrat, contexte imposé par le profil dans `Evaluation` |
| Responsable d'une école | Modifier le référentiel ou le calendrier d'une autre formation, écrire à la place du tuteur | Voter `FORMATION_MANAGE` (formation, promotion, période, évaluation prévue), `EXCHANGE_POST` refusé à l'école |
| Tuteur ou apprenti connecté | Lire ou écrire dans les échanges d'un autre contrat, viser une compétence d'une autre formation | Voters `EXCHANGE_VIEW` et `EXCHANGE_POST`, 404 pour une compétence hors formation, auteur vérifié par `CompetenceMessage` |
| Tout le monde | Envoyer une valeur forgée (niveau 5, compétence d'une autre formation) | Énumération, choix limités du formulaire, règles du constructeur |
| Lien piégé d'un autre site | Faire agir un utilisateur connecté à son insu | CSRF sur chaque formulaire et sur la déconnexion |

## Liste

- [x] Mots de passe en Argon2id
- [x] Limite de tentatives de connexion
- [x] Message identique pour un compte inconnu et un mauvais mot de passe
- [x] TOTP obligatoire pour l'école et les tuteurs, confirmé par un code avant activation
- [x] Secret TOTP chiffré en base (libsodium, clé `APP_ENCRYPTION_KEY` hors du dépôt)
- [x] Codes de secours TOTP : 8 codes à usage unique, gardés en empreinte SHA-256, affichés une seule fois
- [x] Mot de passe oublié : jeton à usage unique de 30 minutes, stocké en empreinte, limite de 3 demandes
- [x] Mot de passe de 12 caractères au moins, refusé s'il figure dans une fuite connue
- [x] Autres sessions fermées après un changement de mot de passe
- [x] Session fermée après 30 minutes d'inactivité
- [x] CSRF sur tous les formulaires et la déconnexion
- [x] Voters testés pour chaque couple profil et donnée
- [x] Suppressions du calendrier en `POST` avec jeton CSRF
- [x] Messages échappés à l'affichage (retours à la ligne seulement), 2000 caractères au plus, jamais modifiés
- [x] CSP avec nonce, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
- [ ] HTTPS forcé et HSTS : à la mise en ligne sur un vrai domaine
- [x] Journal des connexions, des évaluations, des changements de mot de passe et des suppressions de compte, sans donnée sensible
- [x] Journal des actions consultable par l'école (`/ecole/journal`), sans mot de passe, jeton ni adresse IP
- [x] Export et suppression des données de son compte
- [x] Secrets hors du dépôt, Gitleaks en CI
- [x] `SECURITY.md` et `/.well-known/security.txt`
- [ ] Licences des dépendances vérifiées en CI
