/**
 * Tests d'interface — UEP / MENFP.
 *
 * Pilote un vrai navigateur pour vérifier ce que les tests HTTP ne peuvent pas
 * voir : calculs en direct, navigation par sections, messages, menu mobile,
 * rendu effectif des graphiques, absence d'erreur JavaScript.
 *
 * PRÉREQUIS
 *   npm install playwright && npx playwright install chromium
 *   L'application tourne : cd htdocs && php -S 127.0.0.1:8090 router-dev.php
 *   Les tests fonctionnels ont été passés au moins une fois (ils créent les
 *   dossiers et réquisitions utilisés ici).
 *
 * USAGE
 *   node tests/tests-interface.js
 */
const { chromium } = require('playwright');
const BASE = process.env.UEP_BASE || 'http://127.0.0.1:8090';
const MDP = process.env.UEP_ADMIN_PASS || 'AdminUep2026Test';
let ok = 0, ko = 0;
function check(label, cond, extra) {
  if (cond) { ok++; console.log('  OK   ' + label); }
  else { ko++; console.log('  FAIL ' + label + (extra ? ' — ' + extra : '')); }
}

(async () => {
  const nav = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
  const ctx = await nav.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' });
  const page = await ctx.newPage();
  const erreursJs = [];
  page.on('pageerror', e => erreursJs.push(e.message));

  // --- Connexion
  await page.goto(BASE + '/login');
  console.log('\n== Bascule d\'affichage du mot de passe ==');
  await page.fill('#mot_de_passe', 'secret');
  check('champ masque au depart', await page.getAttribute('#mot_de_passe', 'type') === 'password');
  await page.click('[data-bascule-mdp="mot_de_passe"]');
  check('champ visible apres clic', await page.getAttribute('#mot_de_passe', 'type') === 'text');
  await page.click('[data-bascule-mdp="mot_de_passe"]');
  check('champ remasque', await page.getAttribute('#mot_de_passe', 'type') === 'password');

  await page.fill('#email', 'admin@menfp.gouv.ht');
  await page.fill('#mot_de_passe', MDP);
  await page.click('button[type=submit]');
  await page.waitForLoadState('networkidle');
  check('connexion reussie', page.url().includes('/dashboard'), page.url());

  console.log('\n== Message flash ==');
  check('flash de bienvenue affiche', await page.locator('.flash-succes').count() === 1);
  await page.click('[data-fermer-flash]');
  check('flash fermable', await page.locator('.flash-succes').count() === 0);

  console.log('\n== Graphiques du tableau de bord ==');
  const canvas = await page.locator('canvas').count();
  check('3 graphiques rendus (' + canvas + ')', canvas === 3);
  const pixels = await page.evaluate(() => {
    const c = document.getElementById('graphiqueRequisitions');
    const ctx2 = c.getContext('2d');
    const d = ctx2.getImageData(0, 0, c.width, c.height).data;
    let n = 0; for (let i = 3; i < d.length; i += 4) if (d[i] > 0) n++;
    return n;
  });
  check('le donut a bien ete dessine (' + pixels + ' px)', pixels > 1000);

  console.log('\n== Formulaire de requisition : calcul en direct ==');
  await page.goto(BASE + '/requisitions/nouveau');
  await page.fill('input[name="designation[]"]', 'Ordinateur');
  await page.fill('input[name="quantite[]"]', '3');
  await page.fill('input[name="prix_unitaire[]"]', '1250.50');
  await page.waitForTimeout(200);
  const ligne = await page.locator('[data-montant-ligne]').first().textContent();
  const total = await page.locator('[data-montant-total]').textContent();
  check('montant de ligne = 3 751,50', ligne.replace(/ | /g, ' ').trim() === '3 751,50', ligne);
  check('total = 3 751,50', total.replace(/ | /g, ' ').trim() === '3 751,50', total);

  await page.click('[data-ajouter-article]');
  check('nouvelle ligne ajoutee', await page.locator('.ligne-article').count() === 2);
  const champs = page.locator('input[name="designation[]"]');
  await champs.nth(1).fill('Souris');
  await page.locator('input[name="quantite[]"]').nth(1).fill('10');
  await page.locator('input[name="prix_unitaire[]"]').nth(1).fill('500');
  await page.waitForTimeout(200);
  const total2 = await page.locator('[data-montant-total]').textContent();
  check('total cumule = 8 751,50', total2.replace(/ | /g, ' ').trim() === '8 751,50', total2);

  await page.locator('[data-retirer-article]').nth(1).click();
  await page.waitForTimeout(150);
  check('ligne retiree', await page.locator('.ligne-article').count() === 1);
  const total3 = await page.locator('[data-montant-total]').textContent();
  check('total recalcule = 3 751,50', total3.replace(/ | /g, ' ').trim() === '3 751,50', total3);
  await page.locator('[data-retirer-article]').first().click();
  check('derniere ligne conservee (videe)', await page.locator('.ligne-article').count() === 1);

  console.log('\n== Questionnaire : navigation par sections ==');
  await page.goto(BASE + '/upd/1');
  check('section 1 affichee', await page.locator('.etape.is-active').getAttribute('data-etape') === '1');
  const visibles = await page.locator('.etape:visible').count();
  check('une seule section visible a la fois (' + visibles + ')', visibles === 1);
  await page.click('[data-etape-suivante]');
  await page.waitForTimeout(200);
  check('passage a la section 2', await page.locator('.etape.is-active').getAttribute('data-etape') === '2');
  await page.click('[data-aller-etape="5"]');
  await page.waitForTimeout(200);
  check('saut direct a la section 5', await page.locator('.etape.is-active').getAttribute('data-etape') === '5');
  check('compteur mis a jour', (await page.locator('[data-etape-courante]').textContent()) === '5');
  await page.locator('.etape.is-active [data-etape-precedente]').click();
  await page.waitForTimeout(200);
  check('retour a la section 4', await page.locator('.etape.is-active').getAttribute('data-etape') === '4');

  console.log('\n== Confirmation avant suppression ==');
  await page.goto(BASE + '/utilisateurs');
  let demande = null;
  page.on('dialog', async d => { demande = d.message(); await d.dismiss(); });
  const suppr = page.locator('form[data-confirmer] button[type=submit]');
  if (await suppr.count() > 0) {
    await suppr.first().click();
    await page.waitForTimeout(300);
    check('confirmation demandee', demande !== null && demande.includes('Supprimer'), String(demande));
    check('page inchangee apres annulation', page.url().includes('/utilisateurs'));
  } else {
    check('confirmation demandee (aucun compte supprimable, test ignore)', true);
  }

  console.log('\n== Menu mobile ==');
  const mob = await nav.newContext({ viewport: { width: 390, height: 844 }, locale: 'fr-FR' });
  const pm = await mob.newPage();
  await pm.goto(BASE + '/login');
  await pm.fill('#email', 'admin@menfp.gouv.ht');
  await pm.fill('#mot_de_passe', MDP);
  await pm.click('button[type=submit]');
  await pm.waitForLoadState('networkidle');
  check('menu masque au depart', !(await pm.locator('#sidebar').evaluate(el => el.classList.contains('is-open'))));
  await pm.click('[data-basculer-menu]');
  await pm.waitForTimeout(300);
  check('menu ouvert au clic', await pm.locator('#sidebar').evaluate(el => el.classList.contains('is-open')));
  await pm.locator('.app-overlay').click({ position: { x: 360, y: 500 } }); // zone visible a droite du menu
  await pm.waitForTimeout(300);
  check('menu referme via le voile', !(await pm.locator('#sidebar').evaluate(el => el.classList.contains('is-open'))));
  check('pas de defilement horizontal', await pm.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1),
        await pm.evaluate(() => document.documentElement.scrollWidth + ' > ' + window.innerWidth));

  await nav.close();
  console.log('\n== Erreurs JavaScript ==');
  check('aucune erreur JavaScript', erreursJs.length === 0, erreursJs.join(' | '));
  console.log('\n=====================================');
  console.log('REUSSIS : ' + ok + '    ECHECS : ' + ko);
  process.exit(ko === 0 ? 0 : 1);
})();
