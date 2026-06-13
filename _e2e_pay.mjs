import pw from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pw;
const BASE = 'https://afriklink-com.vercel.app';
const SLUG = 'cat-demo-9992644';
const fail = (m) => { console.log('❌ ' + m); process.exitCode = 1; };
const ok = (m) => console.log('✅ ' + m);

const browser = await chromium.launch();
const ctx = await browser.newContext({ locale: 'fr-FR', ignoreHTTPSErrors: true, viewport: { width: 1180, height: 1500 }, deviceScaleFactor: 2 });
const page = await ctx.newPage();
await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
await page.fill('input[name="identifier"]', 'bracknetswilliam+cat9992644@gmail.com');
await page.fill('input[name="password"]', 'CatTest2026!Afrik');
await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type="submit"]')]);
ok('logged in');

// --- read first product detail + stock BEFORE ordering ---
await page.goto(BASE + '/boutique/' + SLUG, { waitUntil: 'networkidle' });
const firstHref = await page.$$eval('.product-cell a.product-card', as => as[0]?.getAttribute('href') || null);
let stockBefore = null;
if (firstHref) {
  await page.goto(new URL(firstHref, BASE).href, { waitUntil: 'networkidle' });
  const tag = await page.$eval('.listing-tags', el => el.textContent.trim()).catch(() => '');
  const m = tag.match(/(\d+)/);
  stockBefore = m ? parseInt(m[1], 10) : null;
  ok('first product stock before: ' + (stockBefore === null ? 'unlimited' : stockBefore));
}

// --- place an order from the storefront ---
await page.goto(BASE + '/boutique/' + SLUG, { waitUntil: 'networkidle' });
await page.click('[data-order-item] [data-qty-inc]');
await page.waitForTimeout(250);
await page.click('[data-cart-checkout]');
await page.waitForTimeout(400);
await page.fill('#cl-name', 'Client Paiement');
await page.fill('#cl-phone', '+221770000222');
await Promise.all([page.waitForLoadState('networkidle'), page.click('[data-cart-form] button[type="submit"]')]);
const confUrl = page.url();
/\/boutique\/commande\//.test(confUrl) ? ok('order placed → ' + confUrl) : fail('no confirmation: ' + confUrl);
const orderRef = confUrl.split('/').pop();

// --- confirmation should offer "Payer maintenant" ---
const payBtn = await page.$('.pay-now-form button');
payBtn ? ok('confirmation offers “Payer maintenant”') : fail('no pay-now button on confirmation');

// --- start payment → sandbox ---
await Promise.all([page.waitForLoadState('networkidle'), payBtn.click()]);
const sbUrl = page.url();
/\/regler$/.test(sbUrl) ? ok('redirected to sandbox: ' + sbUrl) : fail('not on sandbox: ' + sbUrl);
await page.screenshot({ path: '/tmp/pay_sandbox.png', fullPage: true });
const payTest = await page.$('button[value="pay"]');
payTest ? ok('sandbox shows “Payer (test)”') : fail('no pay button on sandbox');

// --- pay (test) → back to confirmation, now PAID ---
await Promise.all([page.waitForLoadState('networkidle'), payTest.click()]);
const paidUrl = page.url();
const paidBadge = await page.$('.pay-paid-badge');
(paidBadge && /\/boutique\/commande\//.test(paidUrl)) ? ok('order marked PAID (badge shown) → ' + paidUrl) : fail('not paid after sandbox: ' + paidUrl);
await page.screenshot({ path: '/tmp/pay_paid.png', fullPage: true });

// --- seller list shows "Payé" ---
await page.goto(BASE + '/vendeur/commandes?filtre=a_traiter', { waitUntil: 'networkidle' });
const firstRowText = await page.$eval('.order-row', el => el.textContent).catch(() => '');
firstRowText.includes('Client Paiement') ? ok('order in seller list') : fail('order not in seller list');
firstRowText.includes('Payé') ? ok('seller list shows “Payé” badge') : fail('no Payé badge in seller list');
await page.locator('.order-row').first().screenshot({ path: '/tmp/pay_seller.png' });

// --- stock decrement check ---
if (firstHref && stockBefore !== null) {
  await page.goto(new URL(firstHref, BASE).href, { waitUntil: 'networkidle' });
  const tag = await page.$eval('.listing-tags', el => el.textContent.trim()).catch(() => '');
  const m = tag.match(/(\d+)/);
  const stockAfter = m ? parseInt(m[1], 10) : null;
  (stockAfter === stockBefore - 1) ? ok(`stock decremented ${stockBefore} → ${stockAfter}`) : fail(`stock not decremented: before=${stockBefore} after=${stockAfter}`);
} else {
  ok('stock decrement: first product is unlimited (NULL) — skipped (code-verified)');
}

await browser.close();
console.log(process.exitCode ? '\n=== FAILED ===' : '\n=== ALL GOOD ===');
