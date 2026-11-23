// Captures d'écran de Cursus sur les comptes de démonstration (données fictives), pour le README,
// le portfolio et le profil. Lancé par le workflow Captures, une fois l'application démarrée.
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const base = process.env.CURSUS_URL ?? 'https://localhost:8443';
const out = process.env.CAPTURES_DIR ?? 'docs/captures';
const password = 'demo-cursus';
mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
let failures = 0;

async function session(email) {
  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    deviceScaleFactor: 2,
    ignoreHTTPSErrors: true,
    locale: 'fr-FR',
  });
  const page = await context.newPage();
  await page.goto(`${base}/connexion`);
  await page.fill('#email', email);
  await page.fill('#password', password);
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);

  return { context, page };
}

async function shot(page, name, path, { full = false } = {}) {
  try {
    if (path) {
      await page.goto(path.startsWith('http') ? path : `${base}${path}`);
      await page.waitForLoadState('networkidle');
    }
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: full });
    console.log(`capture ${name}`);
  } catch (error) {
    failures += 1;
    console.error(`échec ${name}: ${error.message}`);
  }
}

async function firstHref(page, pattern) {
  return page.$$eval('a', (links, source) => {
    const found = links.find((link) => new RegExp(source).test(link.getAttribute('href') ?? ''));
    return found ? found.href : null;
  }, pattern);
}

// Connexion, sans être connecté
{
  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2, ignoreHTTPSErrors: true, locale: 'fr-FR',
  });
  const page = await context.newPage();
  await shot(page, 'cursus-01-connexion', '/connexion');
  await context.close();
}

// Apprenti : progression, historique, échanges avec le tuteur
{
  const { context, page } = await session('lucas.bernard@cursus.test');
  await shot(page, 'cursus-02-progression', '/apprenti', { full: true });
  const history = await firstHref(page, '^/suivi/\\d+$');
  if (history) {
    await shot(page, 'cursus-03-historique', history, { full: true });
    const exchanges = await firstHref(page, '/echanges$');
    if (exchanges) {
      await shot(page, 'cursus-04-echanges', exchanges, { full: true });
    }
    const calendar = await firstHref(page, '/calendrier$');
    if (calendar) {
      await shot(page, 'cursus-05-calendrier-apprenti', calendar, { full: true });
    }
  }
  await context.close();
}

// Tuteur : ses apprentis
{
  const { context, page } = await session('marc.lefebvre@cursus.test');
  await shot(page, 'cursus-06-tuteur', '/tuteur', { full: true });
  await context.close();
}

// École : tableau de bord, formations, promotion, calendrier
{
  const { context, page } = await session('claire.fontaine@cursus.test');
  await shot(page, 'cursus-07-ecole', '/ecole', { full: true });
  await shot(page, 'cursus-08-formations', '/ecole/formations', { full: true });
  await page.goto(`${base}/ecole/formations`);
  const formation = await firstHref(page, '/ecole/formations/\\d+$');
  if (formation) {
    await shot(page, 'cursus-09-formation', formation, { full: true });
    const cohort = await firstHref(page, '/ecole/promotions/\\d+$');
    if (cohort) {
      await shot(page, 'cursus-10-promotion', cohort, { full: true });
      const calendar = await firstHref(page, '/calendrier$');
      if (calendar) {
        await shot(page, 'cursus-11-calendrier-ecole', calendar, { full: true });
      }
    }
  }
  await context.close();
}

await browser.close();
process.exit(failures > 0 ? 1 : 0);
