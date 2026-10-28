const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.BANCAY_PLAYWRIGHT_MODULE || 'playwright-core');
const root = path.resolve(__dirname, '../..');
const privateDir = path.join(root, 'legacy-private/phase4/ui');
const out = path.join(root, 'docs/phase-4/evidence');
const fixture = JSON.parse(fs.readFileSync(path.join(privateDir, 'fixture.json')));
const env = Object.fromEntries(fs.readFileSync(path.join(privateDir, 'staging.env'), 'utf8').trim().split(/\r?\n/).map(s => [s.slice(0, s.indexOf('=')), s.slice(s.indexOf('=') + 1)]));
const base = 'http://localhost:8003';
assert.equal(env.DB_DATABASE, 'bancay_phase4_ui');
const results = {checked_at_utc: new Date().toISOString(), mode: 'installed branded browsers, headless, isolated disposable profiles',
  dataset: env.BANCAY_QA_DATASET === 'combined' ? 'combined demo and migrated catalog; checkout only in disposable QA clone' : 'migrated source catalog; publication and synthetic checkout only in disposable QA clone', environments: []};
async function screenshot(page, name) {
  // Actual legacy content/PII is never exported to Git evidence.
  await page.screenshot({path: path.join(privateDir, name + '.jpg'), fullPage: true, type: 'jpeg', quality: 75});
}
async function choose(page, data, variant) {
  for (const option of data.options) {
    const value = option.values.find(v => v.id === variant.values[option.id]);
    await page.getByRole('group', {name: option.name, exact: true}).getByRole('button', {name: value.value, exact: true}).click();
  }
  await page.waitForFunction(id => document.querySelector('input[name="variant_id"]').value === id, variant.id);
}
(async () => {
  for (const spec of [{name: 'chrome-desktop', channel: 'chrome', viewport: {width: 1440, height: 900}},
    {name: 'edge-desktop', channel: 'msedge', viewport: {width: 1440, height: 900}},
    {name: 'chrome-mobile-viewport', channel: 'chrome', viewport: {width: 390, height: 844}, mobile: true}]) {
    const record = {name: spec.name, viewport: spec.viewport, mobile_emulation: !!spec.mobile, cases: []};
    results.environments.push(record);
    const browser = await chromium.launch({channel: spec.channel, headless: true});
    record.browser_version = browser.version();
    const context = await browser.newContext({viewport: spec.viewport, isMobile: !!spec.mobile, hasTouch: !!spec.mobile, locale: 'vi-VN'});
    const blocked = new Set();
    await context.route('**/*', route => {
      const url = new URL(route.request().url());
      if (['http:', 'https:'].includes(url.protocol) && !['localhost', '127.0.0.1'].includes(url.hostname)) {blocked.add(url.hostname); return route.abort();}
      return route.continue();
    });
    const page = await context.newPage();
    page.setDefaultTimeout(12000);
    let currentCase = '';
    async function test(name, fn) {currentCase = name; await fn();record.cases.push({case: name, status: 'PASS'});}
    try {
      if (!spec.mobile) {
        await test('exment_native_login', async () => {
          await page.goto(base + '/admin/auth/login');
          await page.locator('input[name="username"]').fill('local-admin');
          try {await page.locator('input[name="password"]').fill(env.BANCAY_BOOTSTRAP_PASSWORD);} catch {throw new Error('Native authentication form unavailable');}
          await Promise.all([page.waitForURL(url => !url.pathname.includes('/auth/login')),page.locator('button[type="submit"]').click()]);
          assert.ok(page.url().startsWith(base + '/admin'));
        });
        await test('exment_product_images_options_variants', async () => {
          assert.equal((await page.goto(base + '/admin/plugins/bancay_catalog/products/' + fixture.product.id)).status(), 200);
          await page.getByRole('heading', {name: 'Variants / SKU', exact: true}).waitFor();
          assert.ok(await page.locator('#images img').count() > 0);
          assert.ok(await page.locator('#options details').count() >= 2);
          assert.ok(await page.locator('#variants input[name="sku"]').count() >= 4);
        });
        await test('exment_edit_and_long_category_text', async () => {
          assert.equal((await page.goto(base + '/admin/plugins/bancay_catalog/products/' + fixture.product.id + '/edit')).status(), 200);
          assert.ok((await page.locator('input[name="name"]').inputValue()).length > 0);
          assert.equal((await page.goto(base + '/admin/plugins/bancay_catalog/categories/' + fixture.category.id + '/edit')).status(), 200);
          assert.ok((await page.locator('textarea[name="description"]').inputValue()).length > 10000);
        });
        await test('exment_historical_snapshot_readonly', async () => {
          assert.equal((await page.goto(base + '/admin/plugins/bancay_orders/orders/' + fixture.historical_order.id)).status(), 200);
          await page.getByText('Đơn lịch sử legacy được giữ nguyên, không thực hiện workflow tồn kho live.', {exact: true}).waitFor();
          assert.equal(await page.locator('#order_status').count(), 0);
          await page.getByText(new Intl.NumberFormat('en-US').format(fixture.historical_order.unit_price), {exact: true}).first().waitFor();
        });
        await test('exment_domain_grids', async () => {
          for (const url of ['/admin/plugins/bancay_catalog/inventory', '/admin/plugins/bancay_customers/customers', '/admin/plugins/bancay_content/articles', '/admin/plugins/bancay_content/banners']) {
            assert.equal((await page.goto(base + url)).status(), 200);
          }
        });
        // Native login grants never substitute for public order-session ownership.
        await test('reviewed_products_category_forms', async () => {
          assert.equal(fixture.reviewed_products.length, 2);
          for (const item of fixture.reviewed_products) {
            assert.equal((await page.goto(base + '/admin/plugins/bancay_catalog/products/' + item.id + '/edit')).status(), 200);
            assert.equal(await page.locator('select[name="category_id"]').inputValue(), String(item.category_id));
            assert.equal(await page.locator('select[name="status"]').inputValue(), fixture.reviewed_publication || 'DRAFT');
          }
        });
        await test('reviewed_articles_category_forms', async () => {
          assert.equal(fixture.reviewed_articles.length, 7);
          for (const item of fixture.reviewed_articles) {
            assert.equal((await page.goto(base + '/admin/plugins/bancay_content/articles/' + item.id + '/edit')).status(), 200);
            assert.equal(await page.locator('select[name="article_category_id"]').inputValue(), String(item.article_category_id));
            assert.equal(await page.locator('select[name="status"]').inputValue(), fixture.reviewed_publication || 'DRAFT');
          }
        });
      }
      await test('home_and_responsive_navigation', async () => {
        assert.equal((await page.goto(base)).status(), 200);
        await page.getByRole('link', {name: 'Phase4 acceptance clone — trang chủ', exact: true}).waitFor();
        if (spec.mobile) {await page.getByRole('button', {name: 'Mở menu', exact: true}).click();await page.getByRole('navigation', {name: 'Menu mobile'}).waitFor();}
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      });
      await test('migrated_article', async () => {
        assert.equal((await page.goto(base + '/bai-viet/' + fixture.article.slug)).status(), 200);
        await page.locator('main h1').waitFor();
      });
      await test('portable_content_images_on_different_origin', async () => {
        const images = page.locator('.article-body img[src^="/storage/"]');
        assert.ok(await images.count() > 0, 'Migrated article must exercise an inline legacy image');
        await page.waitForFunction(() => [...document.querySelectorAll('.article-body img[src^="/storage/"]')].every(img => img.complete && img.naturalWidth > 0));
        assert.ok(await images.evaluateAll(imgs => imgs.every(img => new URL(img.src).origin === location.origin)));
        assert.equal(await page.locator('main [src*="localhost:8002"]').count(), 0);
      });
      if (fixture.oos_product_slug) await test('migrated_out_of_stock', async () => {
        await page.goto(base + '/san-pham/' + fixture.oos_product_slug);
        const data = JSON.parse(await page.locator('[data-product]').getAttribute('data-product'));
        const variant = data.variants.find(v => v.available === 0 && !v.contact);
        assert.ok(variant);await choose(page, data, variant);
        await page.getByText('Tạm hết hàng', {exact: true}).waitFor();
        assert.ok(await page.getByRole('button', {name: 'Thêm vào giỏ', exact: true}).isDisabled());
      });
      await test('recovered_product_inline_images', async () => {
        assert.equal((await page.goto(base + '/san-pham/' + fixture.recovered_product_slug)).status(), 200);
        const selector = 'img[src^="/storage/articles/legacy/"]';
        assert.ok(await page.locator(selector).count() >= 3);
        await page.waitForFunction(sel => [...document.querySelectorAll(sel)].every(img => img.complete && img.naturalWidth > 0), selector);
      });
      await test('home_category_migrated_product', async () => {
        await page.goto(base);
        const categorySelector = 'a[href$="/danh-muc/' + fixture.category.slug + '"]';
        if (await page.locator('main ' + categorySelector).count()) {
          await page.locator('main ' + categorySelector).first().click();
        } else if (spec.mobile) {
          await page.getByRole('button', {name: 'Mở menu', exact: true}).click();
          await page.getByRole('navigation', {name: 'Menu mobile', exact: true}).locator(categorySelector).click();
        } else {
          const categoryLink = page.getByRole('navigation', {name: 'Điều hướng chính', exact: true}).locator(categorySelector);
          if (!await categoryLink.isVisible()) await page.getByRole('button', {name: 'Danh mục khác', exact: true}).click();
          await categoryLink.click();
        }
        for (let pageNumber = 0; pageNumber < 20 && !await page.locator('main a[href="/san-pham/' + fixture.product.slug + '"]').count(); pageNumber++) {
          const next = page.locator('main a[rel="next"]:visible').first();
          assert.ok(await next.count(), 'Product must be reachable through category pagination');
          await next.click();
        }
        await page.locator('main a[href="/san-pham/' + fixture.product.slug + '"]').first().click();
        await page.locator('[data-product]').waitFor();
      });
      const data = JSON.parse(await page.locator('[data-product]').getAttribute('data-product'));
      const variant = data.variants.find(v => !v.contact && v.available >= 1);
      assert.ok(variant);
      await test('variant_price_stock_and_gallery', async () => {
        assert.equal(data.options.length, 2);
        await choose(page, data, variant);
        await page.getByText(variant.price.formatted, {exact: true}).first().waitFor();
        const thumbs = page.getByRole('group', {name: 'Ảnh sản phẩm', exact: true}).getByRole('button');
        assert.ok(await thumbs.count() > 0);
        const button = thumbs.nth(await thumbs.count() > 1 ? 1 : 0);
        const src = await button.getAttribute('data-image');await button.click();
        await page.waitForFunction(src => {const img=document.querySelector('[data-product] img');return img.src===src && img.complete && img.naturalWidth>0;},src);
        await screenshot(page,spec.name+'-product');
      });
      await test('add_cart_and_checkout', async () => {
        await page.locator('#quantity').fill('1');
        await Promise.all([page.waitForURL('**/gio-hang'),page.getByRole('button',{name:'Thêm vào giỏ',exact:true}).click()]);
        assert.ok((await page.locator('main').innerText()).includes(variant.sku));
        await page.getByRole('link',{name:'Tiến hành đặt hàng',exact:true}).click();
        await page.getByRole('heading',{name:'Hoàn tất đặt hàng.',exact:true}).waitFor();
      });
      await test('checkout_validation', async () => {
        await page.getByRole('button',{name:'Xác nhận đặt hàng',exact:true}).click();
        assert.ok(await page.locator('input:invalid').count() >= 3);
      });
      await test('new_order_from_migrated_variant', async () => {
        await page.locator('[name="customer_name"]').fill('Phase4 Synthetic '+spec.name);
        await page.locator('[name="customer_phone"]').fill('0900000000');
        await page.locator('[name="customer_email"]').fill('phase4@example.test');
        await page.locator('#address').fill('Local QA only, no delivery');
        await Promise.all([page.waitForURL('**/don-hang/**'),page.getByRole('button',{name:'Xác nhận đặt hàng',exact:true}).click()]);
        await page.getByRole('heading',{name:'Chúng mình đã nhận đơn.',exact:true}).waitFor();
        assert.ok((await page.locator('main').innerText()).includes(variant.sku));
        record.synthetic_order_path=new URL(page.url()).pathname;
        record.expected={variant_id:variant.id,sku:variant.sku,quantity:1,unit_price:variant.price.price,available_before:variant.available};
        await screenshot(page,spec.name+'-success');
      });
    } catch(error) {
      record.cases.push({case:currentCase,status:'FAIL',error_code:'BROWSER_ASSERTION_FAILED'});
      fs.writeFileSync(path.join(privateDir,spec.name+'-failure.txt'),String(error.message).replaceAll(env.BANCAY_BOOTSTRAP_PASSWORD,'[REDACTED]'));
      await screenshot(page,spec.name+'-failure');
    } finally {await browser.close();}
    record.blocked_external_hosts=[...blocked];
    fs.writeFileSync(path.join(out,'browser-acceptance.json'),JSON.stringify(results,null,2)+'\n');
    console.log(spec.name+': '+record.cases.filter(c=>c.status==='PASS').length+' PASS, '+record.cases.filter(c=>c.status==='FAIL').length+' FAIL');
  }
  results.status=results.environments.every(e=>e.cases.length>=7 && e.cases.every(c=>c.status==='PASS') && e.synthetic_order_path)?'PASS':'FAIL';
  results.safari_ios='NOT TESTED: no device; mobile is Chrome viewport emulation';
  fs.writeFileSync(path.join(out,'browser-acceptance.json'),JSON.stringify(results,null,2)+'\n');
  if(results.status!=='PASS')process.exitCode=1;
})().catch(()=>{console.error('BROWSER_ACCEPTANCE_SETUP_FAILED: inspect private environment');process.exitCode=1;});
