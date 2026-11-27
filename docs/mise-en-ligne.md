# Mise en ligne

Liste du skill `mise-en-ligne-web-remi`. `[x]` fait, `[ ]` reste à faire, `[-]` sans objet.

## Pages et mentions

- [x] Mentions légales, confidentialité, conditions d'utilisation, liées dans le pied de chaque page
- [ ] Éditeur, hébergeur et contact remplis dans `.env.local` (`LEGAL_PUBLISHER`, `LEGAL_HOST`, `LEGAL_CONTACT`) : les pages affichent « à compléter » tant qu'ils sont vides

## Cookies et mesure d'audience

- [x] Seulement le cookie de session et le jeton anti-falsification : pas de bandeau
- [-] Mesure d'audience : aucune pour l'instant

## Référencement et partage

- [x] Titre propre à chaque page, description générale
- [x] Favicon
- [-] Sitemap et image d'aperçu : démo non indexée (voir `docs/decisions.md`)
- [x] `robots.txt` et `noindex`

## Accessibilité et affichage

- [x] Attribut `lang`, champs reliés à leur `label`, focus visible
- [x] Contrastes vérifiés (4,5:1 au moins pour le texte ; orange et vert foncés le 2026-11-15)
- [x] Tableaux qui défilent sur petit écran
- [ ] Test réel à 360 px de large et au clavier seul
- [x] Pages 403, 404 et 500 personnalisées
- [x] Feuille de style d'impression, animations coupées si demandé

## Performance

- [-] Images : aucune image lourde (QR code et courbes en SVG)
- [ ] Rapport Lighthouse sur la version en ligne

## Formulaires

- [x] Validation côté serveur, erreurs rattachées aux champs
- [x] Mot de passe oublié limité à 3 demandes par 15 minutes
- [-] Formulaire public de contact : aucun

## Données des comptes

- [x] Export des données du compte (JSON)
- [x] Suppression du compte par anonymisation
- [x] Conservation : remise à zéro régulière de la démo

- [ ] `APP_ENCRYPTION_KEY` générée (`openssl rand -hex 32`) dans le fichier d'environnement de production, sauvegardée à part de la base : sans elle, les secrets TOTP ne se relisent plus
- [ ] Premier compte école créé avec `docker compose exec php bin/console app:user:create`

## Domaine et courriels

- [ ] SPF, DKIM et DMARC sur le domaine d'envoi, `MAILER_FROM` sur ce domaine
- [x] `/.well-known/security.txt`

## Liens et contenu

- [ ] Liens cassés vérifiés en CI
- [-] Appel à l'action : application derrière connexion
