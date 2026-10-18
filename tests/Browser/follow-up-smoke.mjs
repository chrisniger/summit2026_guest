import { createRequire } from 'node:module';
import { spawn, execFileSync } from 'node:child_process';
import { randomBytes, randomUUID } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

const { chromium } = createRequire(import.meta.url)(process.env.PLAYWRIGHT_MODULE || 'playwright');
const cwd = process.cwd();
const output = resolve(cwd, 'storage/app/follow-up-browser-checks');
await mkdir(output, { recursive: true });
const prefix = 'browser_' + randomBytes(6).toString('hex');
const password = randomBytes(18).toString('base64url');
const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'mysql', DB_DATABASE: 'summit_2026_test',
    CACHE_STORE: 'array', MAIL_MAILER: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'database',
    SUMMIT_BROWSER_PREFIX: prefix, SUMMIT_BROWSER_PASSWORD: password, SUMMIT_BROWSER_SUBMISSION: randomUUID() };
const fixture = action => JSON.parse(execFileSync('php', ['tests/Browser/follow-up-fixtures.php', action], { cwd, env, encoding: 'utf8' }));
const race = (action, extra) => new Promise((resolveRace, reject) => {
    const child = spawn('php', ['tests/Browser/follow-up-fixtures.php', action], { cwd, env: { ...env, ...extra }, windowsHide: true });
    let text = ''; let error = '';
    child.stdout.on('data', value => { text += value; });
    child.stderr.on('data', value => { error += value; });
    child.on('close', code => {
        try { if (code !== 0) { throw new Error(error || text); } resolveRace(JSON.parse(text)); }
        catch (failure) { reject(new Error(failure.message + '\n' + text)); }
    });
});
let server;
let browser;
let setup = false;
const results = [];
try {
    const data = fixture('setup');
    setup = true;
    const barrier = () => ({ SUMMIT_BROWSER_BARRIER: String(Date.now() / 1000 + 1.5) });
    let sync = barrier();
    const contactEnv = { SUMMIT_BROWSER_GUEST_ID: String(data.contact_id), SUMMIT_BROWSER_CONTACT_INPUT: JSON.stringify(data.contact_input), ...sync };
    const contacts = await Promise.all([race('contact', contactEnv), race('contact', contactEnv)]);
    assert.deepEqual(contacts.map(row => row.status), [200, 200]);
    assert.equal(contacts[0].activity_id, contacts[1].activity_id);
    assert.equal(fixture('facts')['Race Contact ' + prefix].activities, 1);
    results.push('Simultaneous contact retries create one activity and status transition');
    sync = barrier();
    const owners = await Promise.all(data.bulk_tokens.map(token => race('assign', { SUMMIT_BROWSER_ASSIGNMENT_TOKEN: token, ...sync })));
    assert.deepEqual(owners.map(row => row.status).sort(), [200, 409]);
    assert.equal(fixture('facts')['Race Assignment ' + prefix].assignments, 1);
    results.push('Competing bulk confirmations save one owner and reject the stale selection');
    sync = barrier();
    const ownershipRace = await Promise.all([
        race('contact', { SUMMIT_BROWSER_GUEST_ID: String(data.reassignment_id), SUMMIT_BROWSER_CONTACT_INPUT: JSON.stringify(data.reassignment_input), ...sync }),
        race('assign', { SUMMIT_BROWSER_ASSIGNMENT_TOKEN: data.reassignment_token, ...sync })
    ]);
    const facts = fixture('facts')['Race Reassignment ' + prefix];
    if (ownershipRace[0].status === 200) {
        assert.equal(ownershipRace[1].status, 409);
        assert.equal(facts.active_officer, data.officer_id);
        assert.equal(facts.activities, 1);
    } else {
        assert.ok([403, 409].includes(ownershipRace[0].status));
        assert.equal(ownershipRace[1].status, 200);
        assert.equal(facts.active_officer, data.other_officer_id);
        assert.equal(facts.activities, 0);
    }
    results.push('Contact versus reassignment preserves an atomic outcome and rejects stale authorization');
    const router = resolve(cwd, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
    server = spawn('php', ['-S', '127.0.0.1:8003', router], { cwd: resolve(cwd, 'public'), env, windowsHide: true, stdio: 'pipe' });
    server.stdout.on('data', () => {});
    server.stderr.on('data', () => {});
    for (let i = 0; i < 40; i++) {
        try { if ((await fetch('http://127.0.0.1:8003/login')).ok) { break; } } catch {}
        if (i === 39) { throw new Error('Browser server did not start'); }
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const goto = async path => {
        const response = await page.goto('http://127.0.0.1:8003' + path);
        assert.equal(response.status(), 200, path);
    };
    const screenshot = async name => {
        if (page.viewportSize().width < 768) {
            await page.waitForFunction(() => {
                const sidebar = document.querySelector('.sidebar');
                return !sidebar || (sidebar.classList.contains('is-open') ? Math.abs(sidebar.getBoundingClientRect().left) < 1 : sidebar.getBoundingClientRect().right <= 1);
            });
        }
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, page.url() + ' horizontal overflow');
        await page.screenshot({ path: resolve(output, name + '.png'), fullPage: true });
    };
    const login = async role => {
        await goto('/login');
        await page.getByLabel('Email address', { exact: true }).fill(prefix + '_' + role + '@example.test');
        await page.getByLabel('Password', { exact: true }).fill(password);
        await Promise.all([page.waitForURL(url => !url.pathname.includes('login')), page.getByRole('button', { name: 'Sign in to workspace' }).click()]);
    };
    const logout = async () => Promise.all([page.waitForURL('**/login'), page.getByRole('button', { name: 'Sign out' }).click()]);
    const review = async (ids, action, officer) => {
        await goto('/follow-up/guests');
        for (const id of ids) { await page.locator('input[name="guest_ids[]"][value="' + id + '"]').check(); }
        await page.locator('#assignment_action').selectOption(action);
        if (officer) { await page.locator('#officer_user_id').selectOption(String(officer)); }
        await page.locator('#assignment_reason').fill('Reviewed browser allocation');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Review assignment changes', exact: true }).click()]);
        await page.getByRole('heading', { name: 'Review assignment changes', exact: true }).waitFor();
    };
    const confirm = async () => {
        await page.locator('input[name="confirm"]').check();
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Confirm assignment changes', exact: true }).click()]);
    };
    await login('follow_up_admin');
    await screenshot('follow-up-dashboard-desktop');
    await review([data.bulk_one_id, data.bulk_two_id], 'assign', data.officer_id);
    await screenshot('assignment-confirm-desktop');
    await page.setViewportSize({ width: 360, height: 800 });
    await screenshot('assignment-confirm-mobile');
    await confirm();
    await screenshot('assignment-queue-mobile');
    assert.equal(fixture('facts')['Bulk One ' + prefix].active_officer, data.officer_id);
    results.push('Admin reviews and confirms exact bulk selection on desktop and mobile');
    await logout();
    await login('officer');
    await screenshot('officer-dashboard-mobile');
    await goto('/follow-up/guests/' + data.bulk_one_id);
    await screenshot('contact-form-mobile');
    const saveContact = async (outcome, comment) => {
        await page.locator('#contact_outcome').selectOption(outcome);
        await page.locator('#contact_comment').fill(comment);
        if (outcome === 'UNREACHABLE') { await page.locator('input[name="failed_attempt_confirmed"]').check(); }
        if (outcome === 'CONTACTED') { await page.locator('input[name="two_way_confirmed"]').check(); }
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Save follow-up', exact: true }).click()]);
    };
    await saveContact('NOTE_ONLY', 'SMS sent without reply');
    assert.equal(fixture('facts')['Bulk One ' + prefix].status, 'NOT CONTACTED');
    await saveContact('UNREACHABLE', 'Private first-officer no-answer comment');
    assert.equal(fixture('facts')['Bulk One ' + prefix].status, 'UNREACHABLE');
    await saveContact('CONTACTED', 'Actual two-way conversation confirmed');
    assert.equal(fixture('facts')['Bulk One ' + prefix].status, 'CONTACTED');
    await screenshot('contact-history-mobile');
    results.push('Officer records notes, failed attempts and confirmed contact with preserved history');
    await goto('/follow-up/reports');
    await screenshot('personal-reports-mobile');
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Export Activity timeline', exact: true }).click();
    assert.match((await download).suggestedFilename(), /follow-up-activities-/);
    assert.equal(await page.getByText('Private ' + prefix, { exact: true }).count(), 0);
    await logout();
    await login('follow_up_admin');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await goto('/follow-up/reports');
    await screenshot('team-reports-desktop');
    await page.setViewportSize({ width: 360, height: 800 });
    await screenshot('team-reports-mobile');
    await review([data.bulk_one_id], 'reassign', data.other_officer_id);
    await confirm();
    await logout();
    await login('officer');
    const forbidden = await page.goto('http://127.0.0.1:8003/follow-up/guests/' + data.bulk_one_id);
    assert.equal(forbidden.status(), 403);
    await logout();
    await login('other_officer');
    await goto('/follow-up/guests/' + data.bulk_one_id);
    assert.equal(await page.getByText('Private first-officer no-answer comment', { exact: true }).count(), 0);
    assert.equal(await page.getByText('Actual two-way conversation confirmed', { exact: true }).count(), 0);
    await screenshot('new-officer-private-history-mobile');
    results.push('Reports export authorized activity and reassignment hides former access and private notes');
    await logout();
    await login('follow_up_admin');
    await review([data.bulk_one_id], 'unassign');
    await confirm();
    assert.equal(fixture('facts')['Bulk One ' + prefix].active_officer, null);
    assert.equal(fixture('facts')['Bulk One ' + prefix].status, 'CONTACTED');
    results.push('Unassignment preserves guest status and historical activity');
    assert.deepEqual(errors, []);
    await writeFile(resolve(output, 'results.json'), JSON.stringify({ results, javascriptErrors: errors }, null, 2));
    console.log(JSON.stringify({ passed: results.length, results }));
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
    if (setup) { console.log(JSON.stringify(fixture('cleanup'))); }
}
