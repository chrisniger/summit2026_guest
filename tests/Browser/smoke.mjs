import { chromium } from 'playwright';
import { spawn, execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

const cwd = process.cwd();
const output = resolve(cwd, 'storage/app/browser-checks');
await mkdir(output, { recursive: true });
const prefix = 'browser_' + randomBytes(6).toString('hex');
const password = randomBytes(18).toString('base64url') + 'Aa1';
const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'summit_2026_test', CACHE_STORE: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'database', SUMMIT_BROWSER_PHASE2: '1', SUMMIT_BROWSER_PREFIX: prefix, SUMMIT_BROWSER_PASSWORD: password };
const fixtures = action => execFileSync('php', ['tests/Browser/fixtures.php', action], { cwd, env, encoding: 'utf8' });
const router = resolve(cwd, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
const server = spawn('php', ['-S', '127.0.0.1:8001', router], { cwd: resolve(cwd, 'public'), env, windowsHide: true, stdio: 'pipe' });
let serverLog = '';
server.stdout.on('data', data => { serverLog += data.toString(); });
server.stderr.on('data', data => { serverLog += data.toString(); });
let browser;
let worker;
const results = [];
const errors = [];
try {
    fixtures('setup');
    worker = spawn('php', ['artisan', 'queue:work', '--queue=imports', '--sleep=1', '--tries=3', '--timeout=120'], { cwd, env, windowsHide: true, stdio: 'pipe' });
    worker.stdout.on('data', () => {});
    worker.stderr.on('data', () => {});
    const race = () => new Promise((resolveRace, rejectRace) => {
        const process = spawn('php', ['tests/Browser/fixtures.php', 'race'], { cwd, env, windowsHide: true });
        let output = ''; let errorOutput = '';
        process.stdout.on('data', data => { output += data; });
        process.stderr.on('data', data => { errorOutput += data; });
        process.on('close', code => { if (code !== 0) { rejectRace(new Error(errorOutput)); } else { resolveRace(JSON.parse(output)); } });
    });
    const raced = await Promise.all([race(), race()]);
    assert.deepEqual(raced.map(result => result.already_present).sort(), [false, true]);
    assert.equal(raced[0].record_id, raced[1].record_id);
    results.push('Two simultaneous PHP/database connections produce one check-in');
    for (let i = 0; i < 40; i++) {
        try { if ((await fetch('http://127.0.0.1:8001/login')).ok) { break; } } catch {}
        if (i === 39) { throw new Error('Test web server did not start: ' + serverLog); }
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') { errors.push(message.text()); } });
    const goto = async path => {
        const response = await page.goto('http://127.0.0.1:8001' + path);
        assert.equal(response.status(), 200, path);
    };
    const noOverflow = async () => {
        const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(element => element.getBoundingClientRect().right > innerWidth + 1).map(element => ({ tag: element.tagName, class: element.className, right: element.getBoundingClientRect().right })).slice(0, 12));
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) {
            console.log(JSON.stringify(overflow));
            console.log(await page.evaluate(() => ({ doc: document.documentElement.scrollWidth, elements: [...document.querySelectorAll('body,.app-frame,.main-frame,.page-content,.panel,.table-wrap,.form-grid,.account,.topbar,.staff-access,input,select')].map(element => ({ tag: element.tagName, class: element.className, width: element.getBoundingClientRect().width, right: element.getBoundingClientRect().right, overflow: getComputedStyle(element).overflowX, position: getComputedStyle(element).position })) })));
            await page.screenshot({ path: resolve(output, 'overflow-debug.png'), fullPage: true });
        }
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Page must not overflow horizontally');
    };
    const screenshot = async name => {
        await page.evaluate(() => window.scrollTo(0, 0));
        if (await page.locator('.sidebar').count() && page.viewportSize().width < 768) {
            await page.waitForFunction(() => {
                const sidebar = document.querySelector('.sidebar');
                const bounds = sidebar.getBoundingClientRect();
                return sidebar.classList.contains('is-open') ? Math.abs(bounds.left) < 1 : bounds.right <= 1;
            });
        }
        await page.screenshot({ path: resolve(output, name + '.png'), fullPage: true });
    };
    const login = async role => {
        await goto('/login');
        await page.getByLabel('Email address', { exact: true }).fill(prefix + '_' + role + '@example.test');
        await page.getByLabel('Password', { exact: true }).fill(password);
        await Promise.all([page.waitForURL(url => !url.pathname.includes('login')), page.getByRole('button', { name: 'Sign in to workspace' }).click()]);
    };
    await goto('/login');
    await screenshot('login-desktop');
    await page.getByRole('button', { name: 'Show', exact: true }).click();
    assert.equal(await page.locator('#password').getAttribute('type'), 'text', 'Alpine password toggle');
    await page.setViewportSize({ width: 360, height: 800 });
    await noOverflow();
    await screenshot('login-mobile');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login('super_admin');
    assert.equal(new URL(page.url()).pathname, '/admin');
    await noOverflow();
    await screenshot('admin-desktop');
    results.push('Admin login, session navigation and desktop overview');
    await goto('/admin/sessions');
    await page.getByRole('button', { name: 'Configure Day 1', exact: true }).click();
    await page.getByRole('heading', { name: 'Configure Day 1', exact: true }).waitFor();
    await page.getByLabel('Start time (WAT)').fill('09:00');
    await page.getByLabel('End time (WAT)').fill('08:00');
    await page.getByRole('button', { name: 'Save schedule', exact: true }).click();
    await page.getByText('End time must be after start time on the selected conference date.').waitFor();
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    await screenshot('sessions-desktop');
    results.push('Livewire edit, server validation and cancel in real browser');
    await goto('/admin/staff');
    await screenshot('staff-desktop');
    await page.setViewportSize({ width: 360, height: 800 });
    await noOverflow();
    await screenshot('staff-mobile');
    await goto('/admin');
    await noOverflow();
    await page.getByRole('button', { name: 'Toggle navigation' }).click();
    assert.equal(await page.getByRole('button', { name: 'Toggle navigation' }).getAttribute('aria-expanded'), 'true');
    await screenshot('admin-mobile-navigation');
    await page.getByRole('link', { name: 'Conference sessions', exact: true }).click();
    await noOverflow();
    await screenshot('sessions-mobile');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await goto('/info-com/imports');
    const csvHeaders = ['Created','First Name','Surname','Phone','Email','Member','Summit Centre','Commits to Invite','Invite Count','Location','Abuja Area','First Time','Bus Shuttle','Bus Route','Attendance','Heard About','Feedback','Consent'];
    const csvValues = [new Date(Date.now() - 300000).toISOString(), 'Imported', prefix, '08055555555', '', 'No', '', '', '', '', '', '', '', '', 'In Person', 'WhatsApp', '', 'Yes'];
    const csvRows = [csvValues, ...Array.from({ length: 100 }, (_, index) => {
        const row = [...csvValues]; row[1] = 'BatchPerson' + index; row[3] = '080555' + String(index).padStart(5, '0'); return row;
    })];
    const csvContent = [csvHeaders, ...csvRows].map(row => row.map(cell => '"' + cell.replaceAll('"', '""') + '"').join(',')).join('\n');
    await page.getByLabel('Registration CSV', { exact: true }).setInputFiles({ name: 'browser-registration.csv', mimeType: 'text/csv', buffer: Buffer.from(csvContent) });
    await page.getByRole('button', { name: 'Preview CSV', exact: true }).click();
    await page.getByRole('heading', { name: 'Review your import' }).waitFor();
    await screenshot('import-preview-desktop');
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Confirm import', exact: true }).click();
    for (let attempt = 0; attempt < 30; attempt++) {
        await page.reload();
        if ((await page.locator('.page-heading p').innerText()).includes('Completed')) { break; }
        if (attempt === 29) { throw new Error('Database queue worker did not complete CSV import'); }
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    const importDownload = page.waitForEvent('download');
    assert.equal((await page.locator('.metric-row > div').nth(2).locator('strong').innerText()).trim(), '101');
    await page.getByRole('link', { name: 'Download row results' }).click();
    assert.match((await importDownload).suggestedFilename(), /results\.csv$/);
    await page.setViewportSize({ width: 360, height: 800 });
    await noOverflow();
    await screenshot('import-result-mobile');
    results.push('CSV upload, dry-run preview, actual database queue commit and result download');
    await goto('/info-com/reports');
    await noOverflow();
    await screenshot('reports-mobile');
    const matrixDownload = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Export attendee matrix' }).click();
    assert.match((await matrixDownload).suggestedFilename(), /matrix\.csv$/);
    results.push('Filtered attendance reports and matrix download on mobile');
    await page.getByRole('button', { name: 'Sign out', exact: true }).click();
    results.push('360px mobile: navigation, overview, staff table and session table');
    for (const [role, route] of [['info_com', '/info-com'], ['follow_up_admin', '/follow-up'], ['data_entry', '/guest-service'], ['officer', '/my-guests']]) {
        await login(role);
        assert.equal(new URL(page.url()).pathname, route);
        await noOverflow();
        await screenshot(role + '-mobile');
        if (role === 'info_com') {
            for (const firstName of ['Mike', 'Jane']) {
                await goto('/info-com/attendees/create');
                await page.getByLabel('First name', { exact: false }).fill(firstName);
                await page.getByLabel('Surname', { exact: false }).fill(prefix);
                await page.getByLabel('Phone number', { exact: false }).fill('08012345678');
                await noOverflow();
                if (firstName === 'Mike') { await screenshot('registration-mobile'); }
                await page.getByRole('button', { name: 'Save registration', exact: false }).click();
                await page.getByRole('heading', { name: firstName + ' ' + prefix, exact: true }).waitFor();
                await page.getByText('Registration saved. Automatically checked in to Browser test session.').waitFor();
            }
            await goto('/info-com/check-in');
            await page.getByLabel('Search by name or phone').fill('+234801234');
            await page.locator('article').filter({ hasText: 'Mike ' + prefix }).waitFor();
            await page.locator('article').filter({ hasText: 'Jane ' + prefix }).waitFor();
            await page.getByLabel('Search by name or phone').fill('08087654321');
            const card = page.locator('article').filter({ hasText: 'Pending ' + prefix });
            await card.getByRole('button', { name: /Mark present/ }).click();
            await page.getByText('Present — check-in saved.', { exact: true }).waitFor();
            assert.equal(await page.getByLabel('Search by name or phone').inputValue(), '');
            await noOverflow();
            await screenshot('check-in-mobile');
            await page.setViewportSize({ width: 1440, height: 1000 });
            await screenshot('check-in-desktop');
            await page.setViewportSize({ width: 360, height: 800 });
            results.push('Onsite forms, auto attendance, shared-phone search and one-click manual check-in');
        }
        assert.equal(await page.getByRole('link', { name: 'Staff & roles' }).count(), 0, 'Admin navigation hidden');
        const denied = await page.goto('http://127.0.0.1:8001/admin/staff');
        assert.equal(denied.status(), 403);
        await page.getByRole('heading', { name: 'This workspace is restricted' }).waitFor();
        await page.getByRole('button', { name: 'Sign out', exact: true }).click();
        results.push(role + ': mobile workspace, role redirect and forbidden admin route');
    }
    assert.deepEqual(errors.filter(message => !message.includes('403 (Forbidden)')), [], 'No unexpected browser errors');
    await writeFile(resolve(output, 'results.json'), JSON.stringify({ passed: results, browserErrors: errors, viewport: [1440, 360] }, null, 2));
    console.log(JSON.stringify({ passed: results, screenshotDirectory: output }, null, 2));
} finally {
    if (browser) { await browser.close(); }
    if (worker) { worker.kill(); }
    try { fixtures('cleanup'); } finally { server.kill(); }
}

