const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.BANCAY_PLAYWRIGHT_MODULE || 'playwright-core');
const root = path.resolve(__dirname, '../..');
const privateDir = path.join(root, 'legacy-private/exment-v7');
const env = Object.fromEntries(fs.readFileSync(path.join(root, 'legacy-private/phase4/ui/staging.env'), 'utf8').trim().split(/\r?\n/).map(s => [s.slice(0, s.indexOf('=')), s.slice(s.indexOf('=') + 1)]));
const fixture = JSON.parse(fs.readFileSync(path.join(root, 'legacy-private/phase4/ui/fixture.json')));
assert.equal(env.DB_DATABASE, 'bancay_phase4_ui');
const base = 'http://localhost:8003';
(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  const context = await browser.newContext({viewport: {width: 1440, height: 900}, locale: 'vi-VN'});
  await context.route('**/*', route => {
    const url = new URL(route.request().url());
    return ['http:', 'https:'].includes(url.protocol) && !['localhost', '127.0.0.1'].includes(url.hostname) ? route.abort() : route.continue();
  });
  const page = await context.newPage();
  const errors = [], failedAssets = [], cases = [];
  page.on('pageerror', error => errors.push(error.name));
  page.on('response', response => {
    const url = new URL(response.url());
    if (response.status() >= 400 && url.origin === base && /\.(css|js|woff2?|svg|png|jpe?g)$/.test(url.pathname)) failedAssets.push({path: url.pathname, status: response.status()});
  });
  try {
    await page.goto(base + '/admin/auth/login');
    await page.locator('input[name="username"]').fill('local-admin');
    await page.locator('input[name="password"]').fill('Synthetic-wrong-password-for-upgrade-test');
    await Promise.all([page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/auth/login')), page.locator('button[type="submit"]').click()]);
    await page.waitForLoadState('networkidle');
    assert.ok(page.url().includes('/auth/login'));
    assert.equal(await page.locator('input[name="username"]').count(), 1);
    cases.push({case: 'native_invalid_password_rejected', status: 'PASS'});
    await page.locator('input[name="username"]').fill('local-admin');
    await page.locator('input[name="password"]').fill(env.BANCAY_BOOTSTRAP_PASSWORD);
    await Promise.all([page.waitForURL(url => !url.pathname.includes('/auth/login')), page.locator('button[type="submit"]').click()]);
    await page.waitForLoadState('networkidle');
    assert.match(await page.locator('#version').innerText(), /7\.0\.0/);
    assert.ok(await page.locator('.main-header').evaluate(el => el.getBoundingClientRect().height > 20));
    assert.ok(await page.locator('link[href*="/vendor/open-admin/"]').count() > 0);
    assert.equal(await page.locator('[src*="/vendor/laravel-admin/"], link[href*="/vendor/laravel-admin/"]').count(), 0);
    await page.screenshot({path: path.join(privateDir, 'native-dashboard.jpg'), fullPage: true, type: 'jpeg'});
    cases.push({case: 'native_login_v7_dashboard_and_assets', status: 'PASS'});
    await page.goto(base + '/admin/plugins/bancay_catalog/products/' + fixture.reviewed_products[0].id + '/edit');
    await page.locator('select[name="category_id"]').waitFor();
    await page.waitForLoadState('networkidle');
    await page.screenshot({path: path.join(privateDir, 'native-product-edit.jpg'), fullPage: true, type: 'jpeg'});
    assert.deepEqual(errors, []);
    assert.deepEqual(failedAssets, []);
    cases.push({case: 'native_editor_no_pageerrors_or_failed_assets', status: 'PASS'});
    fs.writeFileSync(path.join(root, 'docs/exment-v7/evidence/native-admin.json'), JSON.stringify({status: 'PASS', browser: browser.version(), cases, page_errors: errors, failed_local_assets: failedAssets}, null, 2) + '\n');
    console.log('PASS: native invalid/valid login, v7 dashboard/editor styles/assets, no page errors or failed local assets.');
  } finally {await browser.close();}
})().catch(error => {
  fs.writeFileSync(path.join(privateDir, 'native-admin-failure.log'), String(error.message).replaceAll(env.BANCAY_BOOTSTRAP_PASSWORD, '[REDACTED]'));
  console.error('NATIVE_V7_ACCEPTANCE_FAILED: inspect private log');
  process.exitCode = 1;
});
