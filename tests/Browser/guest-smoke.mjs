import { createRequire } from 'node:module';
import { spawn, execFileSync } from 'node:child_process';
import { randomBytes, randomUUID } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

const { chromium } = createRequire(import.meta.url)(process.env.PLAYWRIGHT_MODULE || 'playwright');
const cwd = process.cwd();
const output = resolve(cwd, 'storage/app/guest-browser-checks');
await mkdir(output, { recursive: true });
const prefix = 'browser_' + randomBytes(6).toString('hex');
const password = randomBytes(18).toString('base64url');
const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'summit_2026_test',
    CACHE_STORE: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'database',
    SUMMIT_BROWSER_PHASE3: '1', SUMMIT_BROWSER_PREFIX: prefix, SUMMIT_BROWSER_PASSWORD: password,
    SUMMIT_BROWSER_SUBMISSION: randomUUID() };
const fixture = action => execFileSync('php', ['tests/Browser/fixtures.php', action], { cwd, env, encoding: 'utf8' });
const router = resolve(cwd, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
const server = spawn('php', ['-S', '127.0.0.1:8002', router], { cwd: resolve(cwd, 'public'), env, windowsHide: true, stdio: 'pipe' });
const worker = spawn('php', ['artisan', 'queue:work', '--queue=imports', '--sleep=1', '--tries=3', '--timeout=120'], { cwd, env, windowsHide: true, stdio: 'pipe' });
let serverLog = '';
server.stdout.on('data', value => { serverLog += value; });
server.stderr.on('data', value => { serverLog += value; });
worker.stdout.on('data', () => {});
worker.stderr.on('data', () => {});
let browser;
let setup = false;
const results = [];
try {
    fixture('setup');
    setup = true;
    const race = () => new Promise((resolveRace, reject) => {
        const child = spawn('php', ['tests/Browser/fixtures.php', 'race-guest'], { cwd, env, windowsHide: true });
        let text = ''; let error = '';
        child.stdout.on('data', value => { text += value; });
        child.stderr.on('data', value => { error += value; });
        child.on('close', code => {
            try {
                if (code !== 0) { throw new Error(error || text); }
                resolveRace(JSON.parse(text));
            } catch (failure) { reject(new Error(failure.message + '\n' + text)); }
        });
    });
    const raced = await Promise.all([race(), race()]);
    assert.equal(raced[0].guest_id, raced[1].guest_id);
    results.push('Simultaneous independent PHP/database submissions create one guest');
    for (let i = 0; i < 40; i++) {
        try { if ((await fetch('http://127.0.0.1:8002/login')).ok) { break; } } catch {}
        if (i === 39) { throw new Error('Server did not start: ' + serverLog); }
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const goto = async path => {
        const response = await page.goto('http://127.0.0.1:8002' + path);
        assert.equal(response.status(), 200, path);
    };
    const noOverflow = async () => assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, page.url() + ' horizontal overflow');
    const screenshot = async name => {
        if (page.viewportSize().width < 768) {
            await page.waitForFunction(() => {
                const sidebar = document.querySelector('.sidebar');
                return !sidebar || (sidebar.classList.contains('is-open') ? Math.abs(sidebar.getBoundingClientRect().left) < 1 : sidebar.getBoundingClientRect().right <= 1);
            });
        }
        await noOverflow();
        await page.screenshot({ path: resolve(output, name + '.png'), fullPage: true });
    };
    const login = async role => {
        await goto('/login');
        await page.getByLabel('Email address', { exact: true }).fill(prefix + '_' + role + '@example.test');
        await page.getByLabel('Password', { exact: true }).fill(password);
        await Promise.all([page.waitForURL(url => !url.pathname.includes('login')), page.getByRole('button', { name: 'Sign in to workspace' }).click()]);
    };
    const logout = async () => {
        await Promise.all([page.waitForURL('**/login'), page.getByRole('button', { name: 'Sign out' }).click()]);
    };
    await login('data_entry');
    await goto('/follow-up/guests/create');
    await screenshot('entry-desktop');
    await page.setViewportSize({ width: 360, height: 800 });
    await screenshot('entry-mobile');
    const session = await page.locator('#conference_session_id option').nth(1).getAttribute('value');
    await page.locator('#conference_session_id').selectOption(session);
    await page.getByLabel('Full name', { exact: false }).fill('Mobile Guest ' + prefix);
    await page.getByLabel('Phone', { exact: false }).fill('08023456789');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save guest', exact: true }).click()]);
    assert.equal(new URL(page.url()).pathname, '/follow-up/guests/create');
    assert.equal(await page.locator('#conference_session_id').inputValue(), session);
    assert.equal(await page.locator('#full_name').inputValue(), '');
    results.push('Mobile entry saves guest and retains session for batch capture');
    await page.getByLabel('Full name', { exact: false }).fill('Second Encounter ' + prefix);
    await page.getByLabel('Phone', { exact: false }).fill('+2348023456789');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save guest', exact: true }).click()]);
    await page.getByText('Review possible matching encounters', { exact: true }).waitFor();
    await screenshot('duplicate-review-mobile');
    await page.getByLabel('I reviewed the matching records.', { exact: false }).check();
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save guest', exact: true }).click()]);
    await page.getByRole('link', { name: 'Second Encounter ' + prefix, exact: true }).click();
    await page.getByRole('heading', { name: 'Second Encounter ' + prefix, exact: true }).waitFor();
    await screenshot('guest-detail-mobile');
    await page.getByRole('link', { name: 'Edit details', exact: true }).click();
    await page.getByLabel('Full name', { exact: false }).fill('Corrected Guest ' + prefix);
    await page.getByLabel('I reviewed the matching records.', { exact: false }).check();
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save changes', exact: true }).click()]);
    await page.getByRole('heading', { name: 'Corrected Guest ' + prefix, exact: true }).waitFor();
    results.push('Duplicate confirmation creates a distinct encounter and editing preserves it');
    await goto('/follow-up/guests');
    await screenshot('guest-list-mobile');
    assert.equal(await page.getByText('Private ' + prefix, { exact: true }).count(), 0);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await screenshot('guest-list-desktop');
    await logout();
    await login('officer');
    await goto('/follow-up/guests');
    await page.getByRole('heading', { name: 'Assigned ' + prefix, exact: true }).waitFor();
    assert.equal(await page.getByRole('heading', { name: 'Private ' + prefix, exact: true }).count(), 0);
    assert.equal(await page.getByRole('link', { name: 'Enter guest', exact: true }).count(), 0);
    await page.getByRole('link', { name: 'View guest', exact: true }).click();
    await screenshot('officer-guest-detail-desktop');
    results.push('Officer sees assigned encounters with session and cannot access entry tools');
    await logout();
    await login('super_admin');
    await goto('/follow-up/imports');
    const file = resolve(output, 'synthetic-guests.csv');
    const rows = ['Event Date,Event Session,Full Name,Phone,Gender,Home Address,Heard About,Comment'];
    for (let i = 0; i < 101; i++) {
        rows.push('2026-10-31,Morning,Queued Guest ' + prefix + ' ' + i + ',080' + String(1000 + i).padStart(8, '0') + ',,,,');
    }
    await writeFile(file, rows.join('\n') + '\n');
    await page.locator('#file').setInputFiles(file);
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Inspect upload', exact: true }).click()]);
    await screenshot('import-mapping-desktop');
    await page.getByLabel('Timezone for source timestamps', { exact: false }).selectOption('Africa/Lagos');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Generate dry-run preview', exact: true }).click()]);
    await screenshot('import-preview-desktop');
    await page.setViewportSize({ width: 360, height: 800 });
    await screenshot('import-preview-mobile');
    await page.getByLabel('I reviewed the rows and confirm', { exact: false }).check();
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Confirm import', exact: true }).click()]);
    for (let i = 0; i < 60; i++) {
        if (await page.getByText('Status: completed', { exact: true }).count()) { break; }
        if (i === 59) { throw new Error('Real queue import did not finish'); }
        await page.waitForTimeout(500);
        await page.reload();
    }
    await page.getByText('Created', { exact: true }).locator('..').getByText('101', { exact: true }).waitFor();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Download row results', exact: true }).click();
    assert.match((await download).suggestedFilename(), /guest-import-\d+-results.csv/);
    await screenshot('import-completed-mobile');
    results.push('101 CSV rows complete through real database-queue chunks and row-result download');
    assert.deepEqual(errors, []);
    await writeFile(resolve(output, 'results.json'), JSON.stringify({ results, javascriptErrors: errors }, null, 2));
    console.log(JSON.stringify({ passed: results.length, results }));
} catch (error) {
    if (browser) {
        const pages = browser.contexts().flatMap(context => context.pages());
        if (pages[0]) { await pages[0].screenshot({ path: resolve(output, 'failure.png'), fullPage: true }).catch(() => {}); }
    }
    console.error(error);
    process.exitCode = 1;
} finally {
    if (browser) { await browser.close(); }
    worker.kill();
    server.kill();
    if (setup) { console.log(fixture('cleanup')); }
}
