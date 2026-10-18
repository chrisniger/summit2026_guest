import { createRequire } from 'node:module';
import { spawn, execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

const { chromium } = createRequire(import.meta.url)(process.env.PLAYWRIGHT_MODULE || 'playwright');
const cwd = process.cwd();
const output = resolve(cwd, 'storage/app/release-checks');
await mkdir(output, { recursive: true });
const prefix = 'browser_' + randomBytes(6).toString('hex');
const password = randomBytes(24).toString('base64url');
const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'summit_2026_test',
    CACHE_STORE: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'database',
    SUMMIT_BROWSER_PHASE2: '1', SUMMIT_BROWSER_PREFIX: prefix, SUMMIT_BROWSER_PASSWORD: password,
    SUMMIT_MYSQLDUMP: process.env.SUMMIT_MYSQLDUMP || 'D:/xampp/mysql/bin/mysqldump.exe',
    SUMMIT_MYSQL: process.env.SUMMIT_MYSQL || 'D:/xampp/mysql/bin/mysql.exe' };
const fixture = (action, parse = true) => {
    const result = execFileSync('php', ['tests/Browser/release-fixtures.php', action], { cwd, env, encoding: 'utf8', timeout: 300000 });
    return parse ? JSON.parse(result) : result;
};
const child = (action, extra = {}) => new Promise((resolveChild, reject) => {
    const process = spawn('php', ['tests/Browser/release-fixtures.php', action], { cwd, env: { ...env, ...extra }, windowsHide: true });
    let text = ''; let error = '';
    process.stdout.on('data', value => { text += value; });
    process.stderr.on('data', value => { error += value; });
    process.on('close', code => {
        try { if (code !== 0) { throw Error(error || text); } resolveChild(JSON.parse(text)); }
        catch (failure) { reject(new Error(failure.message + '\n' + text)); }
    });
});
const results = [];
let browser;
let server;
let setup = false;
let facts;
try {
    fixture('setup', false);
    setup = true;
    const batches = fixture('volume');
    assert.equal(batches.recovered_batches, 2);
    const worker = spawn('php', ['artisan', 'queue:work', 'database', '--queue=imports', '--stop-when-empty', '--tries=3', '--timeout=120'], { cwd, env, windowsHide: true, stdio: 'pipe' });
    let workerOutput = '';
    worker.stdout.on('data', value => { workerOutput += value; });
    worker.stderr.on('data', value => { workerOutput += value; });
    const code = await new Promise(resolve => worker.on('close', resolve));
    assert.equal(code, 0, workerOutput);
    facts = fixture('facts');
    assert.equal(Number(facts.info_created), 1749);
    assert.equal(Number(facts.guest_created), 1000);
    assert.equal(facts.completed_info, 1);
    assert.equal(facts.completed_guest, 1);
    assert.equal(facts.guest_summary.total, 1000);
    results.push('1749 Info Com and 1000 independent guest CSV rows complete after worker-stop recovery through real queued chunks');
    const checkins = await Promise.all(Array.from({ length: 16 }, () => child('check-in')));
    assert.equal(new Set(checkins.map(row => row.record_id)).size, 1);
    assert.equal(checkins.filter(row => !row.already_present).length, 1);
    results.push('16 concurrent independent check-in processes produce one attendance pair');
    const assigned = fixture('assign');
    const contactEnv = { SUMMIT_RELEASE_GUEST_ID: String(assigned.guest_id), SUMMIT_RELEASE_INPUT: JSON.stringify(assigned.input) };
    const contacts = await Promise.all(Array.from({ length: 8 }, () => child('contact', contactEnv)));
    assert.equal(new Set(contacts.map(row => row.activity_id)).size, 1);
    facts = fixture('facts');
    assert.equal(facts.guest_summary.assigned, 200);
    assert.equal(facts.guest_summary.attempted_guests, 1);
    assert.equal(facts.guest_summary.successful_guests, 1);
    assert.equal(facts.guest_summary.success_rate, 100);
    assert.equal(facts.info_present, 1);
    results.push('200-guest allocation and eight concurrent contact retries reconcile to one attempt and 100% distinct contact success');
    const backup = fixture('backup');
    assert.equal(backup.tables_checked, 21);
    assert.equal(backup.originals_checked, 2);
    assert.equal(backup.wrong_password_rejected, true);
    assert.equal(backup.rehearsal_database_removed, true);
    results.push('Encrypted database/key/two-originals backup restores 21 tables in isolation, rejects the wrong password and removes its rehearsal database');

    const router = resolve(cwd, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
    server = spawn('php', ['-S', '127.0.0.1:8004', router], { cwd: resolve(cwd, 'public'), env, windowsHide: true, stdio: 'pipe' });
    server.stdout.on('data', () => {});
    server.stderr.on('data', () => {});
    for (let i = 0; i < 40; i++) {
        try { if ((await fetch('http://127.0.0.1:8004/login')).ok) { break; } } catch {}
        if (i === 39) { throw Error('Release browser server did not start'); }
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const context = await browser.newContext({ viewport: { width: 360, height: 800 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const goto = async path => {
        const response = await page.goto('http://127.0.0.1:8004' + path);
        assert.equal(response.status(), 200, path);
        assert.match(response.headers()['cache-control'], /no-store/);
    };
    await goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(prefix + '_data_entry@example.test');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await Promise.all([page.waitForURL('**/guest-service'), page.getByRole('button', { name: 'Sign in to workspace' }).click()]);
    await goto('/follow-up/guests/create');
    await page.locator('#conference_session_id').selectOption(await page.locator('#conference_session_id option').nth(1).getAttribute('value'));
    await page.locator('#full_name').fill('Offline recovery ' + prefix);
    await page.locator('#phone_raw').fill('08012340987');
    await context.setOffline(true);
    await page.waitForFunction(() => navigator.onLine === false);
    await page.getByRole('button', { name: 'Save guest', exact: true }).click();
    await page.locator('#connection-status').waitFor({ state: 'visible' });
    assert.equal(await page.locator('#full_name').inputValue(), 'Offline recovery ' + prefix);
    assert.equal(new URL(page.url()).pathname, '/follow-up/guests/create');
    await context.setOffline(false);
    await page.waitForFunction(() => navigator.onLine === true);
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save guest', exact: true }).click()]);
    await page.getByRole('heading', { name: 'Offline recovery ' + prefix, exact: true }).waitFor();
    results.push('Offline save is blocked while entered values remain; reconnection saves one guest');
    for (const width of [320, 360, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await goto('/follow-up/guests/create');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Entry width ' + width);
        const unlabeled = await page.evaluate(() => [...document.querySelectorAll('input:not([type=hidden]),select,textarea')]
            .filter(element => !element.labels?.length && !element.getAttribute('aria-label')).map(element => element.name));
        assert.deepEqual(unlabeled, []);
        await page.keyboard.press('Tab');
        const focusTag = await page.evaluate(() => document.activeElement.tagName);
        assert.notEqual(focusTag, 'BODY');
        await page.screenshot({ path: resolve(output, 'entry-' + width + '.png'), fullPage: true });
    }
    results.push('Guest entry remains contained and labelled at 320/360/768/1440px with keyboard focus and reduced motion');
    await page.getByRole('button', { name: 'Sign out' }).click();
    await goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(prefix + '_super_admin@example.test');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await Promise.all([page.waitForURL('**/admin'), page.getByRole('button', { name: 'Sign in to workspace' }).click()]);
    await goto('/admin/operations');
    await page.screenshot({ path: resolve(output, 'operations-desktop.png'), fullPage: true });
    await goto('/follow-up/reports');
    const csvWait = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Export Guest roster', exact: true }).click();
    const download = await csvWait;
    await download.saveAs(resolve(output, 'synthetic-guest-roster.csv'));
    assert.match(download.suggestedFilename(), /follow-up-guests-/);
    const exported = await readFile(resolve(output, 'synthetic-guest-roster.csv'), 'utf8');
    assert.equal(exported.trim().split(/\r?\n/).length, 1002, '1000 imported guests plus the offline recovery entry and header');
    await page.setViewportSize({ width: 320, height: 900 });
    await goto('/admin/operations');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.screenshot({ path: resolve(output, 'operations-mobile.png'), fullPage: true });
    assert.deepEqual(errors, []);
    results.push('Admin operations and filtered report download work with private no-store responses at realistic data volume');
    await writeFile(resolve(output, 'results.json'), JSON.stringify({ results, volume: facts, backup, javascriptErrors: errors }, null, 2));
    console.log(JSON.stringify({ passed: results.length, results, volume: facts, backup }));
} catch (error) {
    if (browser) {
        const page = browser.contexts().flatMap(context => context.pages())[0];
        if (page) { await page.screenshot({ path: resolve(output, 'failure.png'), fullPage: true }).catch(() => {}); }
    }
    console.error(error);
    process.exitCode = 1;
} finally {
    if (browser) { await browser.close(); }
    if (server) { server.kill(); }
    if (setup) { console.log(fixture('cleanup', false)); }
}
