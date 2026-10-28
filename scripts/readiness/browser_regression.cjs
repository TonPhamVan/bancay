// Runs actual installed Chrome/Edge with disposable profiles against the owned local clone.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.BANCAY_PLAYWRIGHT_MODULE || 'playwright-core');
const root = path.resolve(__dirname, '../..');
const out = path.join(root, 'docs/readiness-closure/evidence');
const shots = path.join(out, 'browser');
const base = 'http://localhost:8001';
const state = JSON.parse(fs.readFileSync(path.join(root, 'legacy-private/readiness-closure/browser-state.json')));
assert.equal(state.base_url, base);
assert.equal(state.cleaned_up, false);
fs.mkdirSync(shots, {recursive: true});
const result = {checked_at_utc: new Date().toISOString(), mode: 'headless installed branded browsers; ephemeral profiles',
  target: 'owned clone of verified current local synthetic DB; not legacy/production', base_url: base, environments: [],
  safari_ios: {status: 'NOT TESTED', reason: 'No Safari/iOS device. Mobile Chrome viewport is emulation, not a physical Android device.'}};
const plan = ['home', 'responsive_navigation', 'search', 'article', 'category', 'product', 'gallery', 'out_of_stock',
  'variant_selection', 'add_cart', 'cart', 'checkout', 'required_validation', 'server_validation', 'order_success', 'order_access', 'console'];
async function noOverflow(page) {
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'Horizontal overflow');
}
async function capture(page, name) {
  await page.screenshot({path: path.join(shots, name + '.jpg'), type: 'jpeg', quality: 75, fullPage: true});
}
async function choose(page, data, variant) {
  for (const option of data.options) {
    const value = option.values.find(v => v.id === variant.values[option.id]);
    assert.ok(value);
    await page.getByRole('group', {name: option.name, exact: true}).getByRole('button', {name: value.value, exact: true}).click();
  }
  await page.waitForFunction(id => document.querySelector('input[name="variant_id"]').value === id, variant.id);
}
(async () => {
  if (process.env.BANCAY_BROWSER_ONLY) result.environments = JSON.parse(fs.readFileSync(path.join(out, 'browser-results.json'))).environments.filter(e => e.name !== process.env.BANCAY_BROWSER_ONLY);
  for (const env of [
    {name: 'desktop-chrome', channel: 'chrome', viewport: {width: 1440, height: 900}},
    {name: 'desktop-edge', channel: 'msedge', viewport: {width: 1440, height: 900}},
    {name: 'mobile-chrome', channel: 'chrome', viewport: {width: 390, height: 844}, isMobile: true, hasTouch: true},
  ].filter(e => !process.env.BANCAY_BROWSER_ONLY || e.name === process.env.BANCAY_BROWSER_ONLY)) {
    const record = {name: env.name, channel: env.channel, viewport: env.viewport, emulated_mobile: !!env.isMobile, cases: []};
    result.environments.push(record);
    let browser, page;
    async function test(name, fn) {
      try { await fn(); record.cases.push({case: name, status: 'PASS'}); }
      catch (error) { record.cases.push({case: name, status: 'FAIL', error: error.message.slice(0, 700)}); throw error; }
    }
    try {
      browser = await chromium.launch({channel: env.channel, headless: true});
      record.version = browser.version();
      const context = await browser.newContext({viewport: env.viewport, isMobile: !!env.isMobile, hasTouch: !!env.hasTouch, locale: 'vi-VN', timezoneId: 'Asia/Ho_Chi_Minh'});
      page = await context.newPage();
      page.setDefaultTimeout(10000);
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      page.on('console', e => {if (e.type() === 'error') errors.push(e.text());});
      let data, variant, orderUrl;
      await test('home', async () => {
        assert.equal((await page.goto(base)).status(), 200);
        await page.getByRole('link', {name: 'Readiness QA local clone — trang chủ', exact: true}).waitFor();
        await noOverflow(page);
        await capture(page, env.name + '-home');
      });
      await test('responsive_navigation', async () => {
        if (env.isMobile) {
          const button = page.getByRole('button', {name: 'Mở menu', exact: true});
          await button.click();
          assert.equal(await button.getAttribute('aria-expanded'), 'true');
          await page.getByRole('navigation', {name: 'Menu mobile', exact: true}).waitFor();
          await capture(page, env.name + '-navigation');
        } else {
          await page.getByRole('navigation', {name: 'Điều hướng chính', exact: true}).waitFor();
          const button = page.getByRole('button', {name: 'Danh mục con của Cây trong nhà', exact: true});
          await button.click();
          assert.equal(await button.getAttribute('aria-expanded'), 'true');
          await page.keyboard.press('Escape');
          assert.equal(await button.getAttribute('aria-expanded'), 'false');
        }
      });
      await test('search', async () => {
        await page.locator(env.isMobile ? '#mobile-search' : '#header-search').fill('Monstera');
        await Promise.all([page.waitForURL('**/tim-kiem?q=Monstera'), page.locator(env.isMobile ? '#mobile-search' : '#header-search').press('Enter')]);
        await page.locator('main a[href$="/san-pham/monstera-chau-dat-nung-demo"]').first().waitFor();
        await noOverflow(page);
      });
      await test('article', async () => {
        await page.goto(base + '/bai-viet');
        await page.locator('main a[href*="/bai-viet/"]').first().click();
        await page.locator('main h1').waitFor();
        assert.ok((await page.locator('main h1').innerText()).length > 10);
        await noOverflow(page);
      });
      await test('category', async () => {
        await page.goto(base);
        await page.locator('main a[href="/danh-muc/cay-trong-nha-demo"]').first().click();
        assert.ok(page.url().endsWith('/danh-muc/cay-trong-nha-demo'));
        await page.locator('main a[href$="/san-pham/monstera-chau-dat-nung-demo"]').first().waitFor();
        await noOverflow(page);
      });
      await test('product', async () => {
        await page.locator('main a[href$="/san-pham/monstera-chau-dat-nung-demo"]').first().click();
        data = JSON.parse(await page.locator('[data-product]').getAttribute('data-product'));
        assert.ok(data.image.startsWith(base + '/storage/'), 'Browser server lost its isolated environment');
        variant = data.variants.find(v => !v.contact && v.available >= 2);
        assert.ok(variant && data.options.length === 2);
        await noOverflow(page);
      });
      await test('gallery', async () => {
        const thumbs = page.getByRole('group', {name: 'Ảnh sản phẩm', exact: true}).getByRole('button');
        assert.ok(await thumbs.count() >= 2);
        const image = await thumbs.nth(1).getAttribute('data-image');
        await thumbs.nth(1).click();
        await page.waitForFunction(src => document.querySelector('[data-product] img').src === src, image);
        await page.waitForFunction(() => {const img = document.querySelector('[data-product] img'); return img.complete && img.naturalWidth > 0;});
      });
      await test('out_of_stock', async () => {
        const unavailable = data.variants.find(v => !v.contact && v.available === 0);
        assert.ok(unavailable, 'No OOS fixture');
        await choose(page, data, unavailable);
        await page.getByText('Tạm hết hàng', {exact: true}).waitFor();
        assert.ok(await page.getByRole('button', {name: 'Thêm vào giỏ', exact: true}).isDisabled());
        assert.ok(await page.getByRole('button', {name: 'Mua ngay', exact: true}).isDisabled());
      });
      await test('variant_selection', async () => {
        await choose(page, data, variant);
        await page.getByText(variant.price.formatted, {exact: true}).first().waitFor();
        assert.ok((await page.locator('[data-product]').innerText()).includes(variant.sku));
        await page.locator('#quantity').fill('2');
        assert.ok(await page.getByRole('button', {name: 'Thêm vào giỏ', exact: true}).isEnabled());
        await capture(page, env.name + '-product');
      });
      await test('add_cart', async () => {
        await Promise.all([page.waitForURL('**/gio-hang'), page.getByRole('button', {name: 'Thêm vào giỏ', exact: true}).click()]);
        assert.ok((await page.locator('main').innerText()).includes(variant.sku));
      });
      await test('cart', async () => {
        const input = page.locator('main input[name="quantity"]');
        assert.equal(await input.inputValue(), '2');
        await input.fill('1');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', {name: 'Cập nhật', exact: true}).click()]);
        assert.equal(await input.inputValue(), '1');
        await input.fill('2');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', {name: 'Cập nhật', exact: true}).click()]);
        assert.equal(await input.inputValue(), '2');
        await noOverflow(page);
        await capture(page, env.name + '-cart');
      });
      await test('checkout', async () => {
        await page.getByRole('link', {name: 'Tiến hành đặt hàng', exact: true}).click();
        await page.getByRole('heading', {name: 'Hoàn tất đặt hàng.', exact: true}).waitFor();
        await noOverflow(page);
      });
      await test('required_validation', async () => {
        await page.getByRole('button', {name: 'Xác nhận đặt hàng', exact: true}).click();
        assert.ok(page.url().endsWith('/thanh-toan'));
        assert.ok(await page.locator('input:invalid').count() >= 3);
      });
      await test('server_validation', async () => {
        await page.locator('[name="customer_name"]').fill('Readiness Synthetic ' + env.name);
        await page.locator('[name="customer_phone"]').fill('invalid');
        await page.locator('[name="customer_email"]').fill('readiness@example.test');
        await page.locator('#address').fill('Synthetic QA address, not a delivery');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', {name: 'Xác nhận đặt hàng', exact: true}).click()]);
        await page.getByRole('alert').first().waitFor();
        assert.ok(page.url().endsWith('/thanh-toan'));
        await page.locator('[name="customer_phone"]').fill('0900000000');
        await capture(page, env.name + '-checkout');
      });
      await test('order_success', async () => {
        await Promise.all([page.waitForURL('**/don-hang/**'), page.getByRole('button', {name: 'Xác nhận đặt hàng', exact: true}).click()]);
        await page.getByRole('heading', {name: 'Chúng mình đã nhận đơn.', exact: true}).waitFor();
        assert.ok((await page.locator('main').innerText()).includes(variant.sku));
        assert.ok((await page.locator('main').innerText()).includes('2 × ' + variant.price.formatted));
        orderUrl = page.url();
        record.synthetic_order_path = new URL(orderUrl).pathname;
        record.expected = {sku: variant.sku, quantity: 2, unit_price: variant.price.price, total: variant.price.price * 2};
        await noOverflow(page);
        await capture(page, env.name + '-success');
      });
      await test('order_access', async () => {
        const outsider = await browser.newContext();
        try {const p = await outsider.newPage();assert.equal((await p.goto(orderUrl)).status(), 404);} finally {await outsider.close();}
      });
      await test('console', async () => {assert.deepEqual(errors, []);});
    } catch (error) {
      if (page) { await capture(page, env.name + '-failure'); fs.writeFileSync(path.join(root, 'legacy-private/readiness-closure', env.name + '-failure.html'), await page.content()); }
      record.failure = error.message.slice(0, 700);
      for (const name of plan) if (!record.cases.some(c => c.case === name)) record.cases.push({case: name, status: 'NOT TESTED', reason: 'Earlier case failed in this environment'});
    } finally {if (browser) await browser.close();}
    console.log(env.name + ': ' + record.cases.filter(c => c.status === 'PASS').length + '/' + plan.length + ' PASS');
    fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify(result, null, 2) + '\n');
  }
  result.status = result.environments.every(e => e.cases.every(c => c.status === 'PASS')) ? 'PASS' : 'FAIL';
  fs.writeFileSync(path.join(out, 'browser-results.json'), JSON.stringify(result, null, 2) + '\n');
  if (result.status !== 'PASS') process.exitCode = 1;
})().catch(error => {console.error(error.message);process.exitCode = 1;});
