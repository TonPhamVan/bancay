const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const {chromium} = require(process.env.BANCAY_PLAYWRIGHT_MODULE || 'playwright-core');
const root = path.resolve(__dirname, '../..');
const privateDir = path.join(root, 'legacy-private/ui-mapping');
const outputDir = path.join(root, 'docs/ui-mapping/evidence');
fs.mkdirSync(outputDir, {recursive: true});
const readEnv = name => Object.fromEntries(fs.readFileSync(path.join(root, name), 'utf8').split(/\r?\n/).filter(x => x.includes('=')).map(x => [x.slice(0, x.indexOf('=')), x.slice(x.indexOf('=') + 1)]));
const hash = value => crypto.createHash('sha256').update(value.replace(/\r\n?/g, '\n')).digest('hex');
const norm = value => String(value).replace(/\s+/g, ' ').trim();
const result = {checked_at_utc: new Date().toISOString(), modes: [], issues: []};
const check = (ok, code, context = {}) => {if (!ok) result.issues.push({code, ...context});};

async function parse(page, html, type) {
  return page.evaluate(({html, type}) => {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    if (type === 'list') return {rows: [...doc.querySelectorAll('.store-scroll > table > tbody > tr')].map(tr => ({id: tr.querySelector('td')?.textContent.trim(),
      cells: [...tr.querySelectorAll('td')].map(td => td.textContent), name: tr.querySelector('td:nth-child(2) a')?.textContent})), next: doc.querySelector('a[rel="next"]')?.href};
    if (type === 'fields') return Object.fromEntries([...doc.querySelectorAll('[id^="field_"], [id^="setting_"]')].map(el => [el.name, el.type === 'checkbox' ? {checkbox: el.checked} : {value: el.value}]));
    if (type === 'product') return {images: [...doc.querySelectorAll('#images img')].map(img => img.getAttribute('src')),
      options: doc.querySelectorAll('#options details').length, variants: [...doc.querySelectorAll('#variants form')].filter(f => f.querySelector('[name="variant_id"]')).map(f =>
        Object.fromEntries(['variant_id', 'sku', 'pricing_type', 'price', 'sale_price', 'status', 'image_id'].map(key => [key, f.querySelector(`[name="${key}"]`)?.value])))};
    if (type === 'order') return {readonly: !doc.querySelector('#order_status'), rows: [...doc.querySelectorAll('.store-scroll tbody tr')].map(tr => [...tr.querySelectorAll('td')].map(td => td.textContent))};
  }, {html, type});
}

(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  try {
    for (const mode of process.argv.includes('--combined') ? ['combined', 'migrated'] : ['migrated', 'demo']) {
      const data = JSON.parse(fs.readFileSync(path.join(privateDir, mode + '.json')));
      const context = await browser.newContext({viewport: {width: 1440, height: 900}});
      await context.route('**/*', route => {
        const url = new URL(route.request().url());
        return ['http:', 'https:'].includes(url.protocol) && !['localhost', '127.0.0.1'].includes(url.hostname) ? route.abort() : route.continue();
      });
      const page = await context.newPage();
      const record = {mode, base: data.base, lists: {}, edit_forms: 0, checked_fields: 0, checked_variants: 0, checked_images: 0, historical_samples: 0};
      result.modes.push(record);
      const credentials = readEnv(mode === 'migrated' ? 'legacy-private/phase4/staging.env' : 'legacy-private/phase1/admin.env');
      await page.goto(data.base + '/admin/auth/login');
      await page.locator('[name="username"]').fill('local-admin');
      await page.locator('[name="password"]').fill(credentials.BANCAY_BOOTSTRAP_PASSWORD);
      await Promise.all([page.waitForURL(url => !url.pathname.includes('/auth/login')), page.locator('button[type="submit"]').click()]);
      await page.goto(data.base + '/admin/plugins/bancay_catalog/products');
      const get = async (url, type) => {
        const response = await context.request.get(new URL(url, data.base).href);
        if (response.status() !== 200) {check(false, 'HTTP_STATUS', {mode, path: new URL(url, data.base).pathname, status: response.status()}); return null;}
        return parse(page, await response.text(), type);
      };
      const lists = {...data.entities, inventory: {module: 'catalog', rows: data.inventory}, orders: {module: 'orders', rows: data.orders}};
      for (const [entity, definition] of Object.entries(lists)) {
        const expected = new Map(definition.rows.map(row => [String(row.id), row]));
        const seen = new Set();
        let url = `${data.base}/admin/plugins/bancay_${definition.module}/${entity}`, pages = 0;
        while (url && pages++ < 50) {
          const view = await get(url, 'list'); if (!view) break;
          for (const actual of view.rows.filter(row => /^\d+$/.test(row.id || ''))) {
            const row = expected.get(actual.id);
            check(!!row && !seen.has(actual.id), 'LIST_UNEXPECTED_OR_DUPLICATE', {mode, entity, id: actual.id}); seen.add(actual.id);
            if (!row) continue;
            const cells = actual.cells.map(norm);
            if (entity === 'inventory') {
              check(cells[1].includes(row.sku) && cells[2] === String(row.quantity) && cells[3] === String(row.reserved) && cells[4] === String(row.available), 'INVENTORY_MAPPING', {mode, id: row.id});
            } else if (entity === 'orders') {
              check(norm(actual.name) === row.number && cells[3] === row.total && cells[4] === row.status, 'ORDER_LIST_MAPPING', {mode, id: row.id});
            } else {
              check(norm(actual.name) === norm(row.name), 'LIST_NAME', {mode, entity, id: row.id});
              if (entity === 'products') check(cells[2] === norm(row.category) && cells[3].includes(row.variant_count + ' SKU') && cells[3].includes(row.price_range) && cells[3].includes('Khả dụng: ' + row.available) && cells[4] === row.status, 'PRODUCT_GRID_MAPPING', {mode, id: row.id});
              else check(cells[2] === row.status && (entity !== 'articles' || cells[1].includes(norm(row.category))), 'LIST_STATUS_OR_CATEGORY', {mode, entity, id: row.id});
            }
          }
          url = view.next ? new URL(view.next, data.base).href : null;
          if (url) check(new URL(url).origin === data.base, 'PAGINATION_ORIGIN', {mode, entity});
        }
        check(seen.size === expected.size, 'LIST_COUNT', {mode, entity, expected: expected.size, actual: seen.size});
        record.lists[entity] = {records: seen.size, pages};
        if (entity === 'inventory' || entity === 'orders') continue;
        for (const row of definition.rows) {
          const fields = await get(`/admin/plugins/bancay_${definition.module}/${entity}/${row.id}/edit`, 'fields'); if (!fields) continue;
          for (const [field, expectedField] of Object.entries(row.fields)) {
            const actual = fields[field]; record.checked_fields++;
            check(!!actual && ('checkbox' in expectedField ? actual.checkbox === expectedField.checkbox : hash(actual.value) === expectedField.sha256), 'EDIT_FIELD', {mode, entity, id: row.id, field});
          }
          record.edit_forms++;
          if (entity === 'products') {
            const view = await get(`/admin/plugins/bancay_catalog/products/${row.id}`, 'product'); if (!view) continue;
            check(view.options === row.option_count && view.images.length === row.image_paths.length, 'PRODUCT_OPTIONS_OR_GALLERY_COUNT', {mode, id: row.id});
            for (const image of row.image_paths) check(view.images.some(url => new URL(url, data.base).pathname === '/storage/' + image), 'GALLERY_PATH', {mode, id: row.id});
            record.checked_images += view.images.length;
            for (const variant of row.variants) {
              const actual = view.variants.find(v => v.variant_id === String(variant.id));
              check(!!actual && ['sku', 'pricing_type', 'price', 'sale_price', 'status', 'image_id'].every(k => actual[k] === String(variant[k])), 'VARIANT_MAPPING', {mode, product_id: row.id, id: variant.id});
              record.checked_variants++;
            }
          }
        }
        console.log(`${mode}: ${entity} list/form mapping checked (${seen.size} records)`);
      }
      const settings = await get('/admin/plugins/bancay_settings/settings', 'fields');
      for (const [key, value] of Object.entries(data.settings)) check(settings?.[`settings[${key}]`] && hash(settings[`settings[${key}]`].value) === value, 'SETTING_VALUE', {mode, key});
      for (const order of data.orders.filter(o => o.legacy).slice(0, 10)) {
        const view = await get('/admin/plugins/bancay_orders/orders/' + order.id, 'order'); if (!view) continue;
        check(view.readonly && view.rows.length === order.items.length, 'HISTORICAL_READONLY_OR_ITEMS', {mode, id: order.id});
        order.items.forEach((item, index) => check(view.rows[index] && [0, 2, 3, 4].every((column, i) => norm(view.rows[index][column]) === norm([item.name, item.unit_price, item.quantity, item.subtotal][i])), 'HISTORICAL_SNAPSHOT', {mode, id: order.id}));
        record.historical_samples++;
      }
      await page.goto(data.base + '/san-pham');
      const links = new Set(await page.locator('main a[href^="/san-pham/"]').evaluateAll(items => items.map(x => x.getAttribute('href'))));
      check(links.size === Math.min(24, data.public_products), 'FRONTEND_PRODUCT_VISIBILITY', {mode, expected: data.public_products, actual: links.size});
      record.frontend_public_products = data.public_products;
      record.frontend_public_articles = data.public_articles;
      if (mode === 'migrated') {
        check((await page.goto(data.base + '/san-pham/' + data.entities.products.rows[0].slug)).status() === 404, 'DRAFT_PRODUCT_LEAK', {mode});
        check((await page.goto(data.base + '/bai-viet/' + data.entities.articles.rows[0].slug)).status() === 404, 'DRAFT_ARTICLE_LEAK', {mode});
      }
      await page.goto(data.base + '/admin/plugins/bancay_catalog/products');
      await page.screenshot({path: path.join(privateDir, mode + '-admin-products.jpg'), fullPage: true, type: 'jpeg'});
      await context.close();
    }
  } finally {await browser.close();}
  result.status = result.issues.length ? 'FAIL' : 'PASS';
  fs.writeFileSync(path.join(outputDir, 'mapping.json'), JSON.stringify(result, null, 2) + '\n');
  console.log(`UI mapping ${result.status}; ${result.issues.length} mismatches; raw records/credentials/screenshots stay private.`);
  if (result.issues.length) process.exitCode = 1;
})().catch(() => {console.error('UI_MAPPING_SETUP_FAILED; inspect local runtime privately');process.exitCode = 1;});
