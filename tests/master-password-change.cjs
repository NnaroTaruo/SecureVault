// Run: node tests/master-password-change.cjs
// Uses isolated sample data; defaults to XAMPP on Windows or MySQL and Chrome on Ubuntu.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn, spawnSync } = require('node:child_process');
const { webcrypto } = require('node:crypto');
const root = path.resolve(__dirname, '..');
const windows = process.platform === 'win32';
const php = process.env.SV_TEST_PHP || (windows ? 'C:/xampp/php/php.exe' : '/usr/bin/php');
const mysqlBin = process.env.SV_TEST_MYSQL_BIN || (windows ? 'C:/xampp/mysql/bin' : '/usr/bin');
const mysqlServer = process.env.SV_TEST_MYSQLD || (windows ? path.join(mysqlBin, 'mysqld.exe') : '/usr/sbin/mysqld');
const chrome = process.env.SV_TEST_CHROME || (windows ? 'C:/Program Files/Google/Chrome/Application/chrome.exe' : '/usr/bin/google-chrome');
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'securevault-rotation-test-'));
const app = path.join(temporary, 'app');
const children = [];
const sockets = [];
const oldPassword = 'Sample-old-vault-key-42!';
const newPassword = 'Sample-new-vault-key-43!';
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
let mysqlPort;
let origins;

function run(command, args, options = {}) {
    const result = spawnSync(command, args, { encoding: 'utf8', windowsHide: true, timeout: 60000, ...options });
    if (result.error || result.status !== 0) throw new Error('Test process failed: ' + path.basename(command) + '\n' + (result.error?.message || result.stderr || result.stdout));
    return result.stdout;
}
function background(command, args, cwd) {
    const child = spawn(command, args, { cwd, windowsHide: true, stdio: 'ignore' });
    child.on('error', error => { child.startError = error; });
    children.push(child);
    return child;
}
async function port() {
    const server = net.createServer();
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const value = server.address().port;
    await new Promise(resolve => server.close(resolve));
    return value;
}
async function until(check, message, attempts = 100) {
    for (let attempt = 0; attempt < attempts; attempt++) {
        try { if (await check()) return; } catch {}
        await sleep(100);
    }
    throw new Error(message);
}
function sql(statement, database = true) {
    return run(path.join(mysqlBin, windows ? 'mysql.exe' : 'mysql'), ['--no-defaults', '--host=127.0.0.1', '--port=' + mysqlPort, '--user=root', '--batch', '--skip-column-names', ...(database ? ['--database=securevault_rotation_test'] : [])], { input: statement });
}
async function request(endpoint, body, context = {}, server = 0, csrf = context.csrf) {
    const response = await fetch(origins[server] + '/' + endpoint, {
        method: body === undefined ? 'GET' : 'POST', redirect: 'manual',
        headers: { ...(context.cookie ? { Cookie: context.cookie } : {}), ...(csrf ? { 'X-CSRF-Token': csrf } : {}) },
        ...(body === undefined ? {} : { body: new URLSearchParams(body) }), signal: AbortSignal.timeout(15000)
    });
    const cookie = response.headers.get('set-cookie')?.split(';')[0] || context.cookie;
    return { status: response.status, data: await response.json(), cookie };
}
async function signIn(username, password, server = 0) {
    const result = await request('api/auth.php?action=login', { username, password }, {}, server);
    assert.equal(result.status, 200, 'Sample sign-in failed');
    const html = await (await fetch(origins[server] + '/dashboard.php', { headers: { Cookie: result.cookie } })).text();
    const csrf = html.match(/const CSRF_TOKEN = "([a-f0-9]+)"/);
    assert(csrf, 'The dashboard did not provide a CSRF token');
    return { cookie: result.cookie, csrf: csrf[1], username };
}
async function vault(context) {
    const result = await request('api/accounts.php?action=sync_vault', undefined, context);
    assert.equal(result.status, 200);
    assert.equal(result.data.success, true);
    return result.data.data;
}
async function key(password, salt) {
    const material = await webcrypto.subtle.importKey('raw', Buffer.from(password), 'PBKDF2', false, ['deriveKey']);
    return webcrypto.subtle.deriveKey({ name: 'PBKDF2', salt: Buffer.from(salt), iterations: 100000, hash: 'SHA-256' }, material, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
}
async function encrypt(value, cryptoKey) {
    const iv = webcrypto.getRandomValues(new Uint8Array(12));
    const ciphertext = await webcrypto.subtle.encrypt({ name: 'AES-GCM', iv }, cryptoKey, Buffer.from(value));
    return Buffer.concat([iv, Buffer.from(ciphertext)]).toString('base64');
}
async function decrypt(value, cryptoKey) {
    const bytes = Buffer.from(value, 'base64');
    return Buffer.from(await webcrypto.subtle.decrypt({ name: 'AES-GCM', iv: bytes.subarray(0, 12) }, cryptoKey, bytes.subarray(12))).toString('utf8');
}
async function replacements(snapshot, password = newPassword) {
    const cryptoKey = await key(password, snapshot.salt);
    const previousKey = await key(oldPassword, snapshot.salt);
    return Promise.all(snapshot.accounts.map(async account => ({ id: Number(account.id), previous_password_encrypted: account.password_encrypted, password_encrypted: await encrypt(await decrypt(account.password_encrypted, previousKey), cryptoKey) })));
}
async function createUser(username, count = 2) {
    const result = await request('api/auth.php?action=register', { username, email: username + '@example.test', password: oldPassword, confirm_password: oldPassword });
    assert.equal(result.data.success, true);
    const context = await signIn(username, oldPassword);
    const snapshot = await vault(context);
    const cryptoKey = await key(oldPassword, snapshot.salt);
    for (let index = 0; index < count; index++) {
        const added = await request('api/accounts.php?action=add_account', { service_name: 'Sample ' + index, username: 'sample@example.test', category_id: snapshot.categories[0].id, encrypted_password: await encrypt('Saved example ' + index + ' 🔐', cryptoKey), notes: 'Sample data only' }, context);
        assert.equal(added.data.success, true);
    }
    return context;
}
const rotate = (context, accounts, previous = oldPassword, next = newPassword) => request('api/auth.php?action=update_master_password', { old_password: previous, new_password: next, accounts: JSON.stringify(accounts) }, context);

async function setup() {
    for (const executable of [php, chrome, mysqlServer, path.join(mysqlBin, windows ? 'mysql.exe' : 'mysql'), ...(windows ? [path.join(mysqlBin, 'mysql_install_db.exe')] : [])]) assert(fs.existsSync(executable), 'Missing test executable: ' + executable);
    fs.mkdirSync(app);
    const files = run('git', ['ls-files', '-z'], { cwd: root }).split('\0').filter(Boolean);
    assert(!files.includes('config.php') && !files.includes('database.sql'), 'Private files must not be tracked or copied');
    for (const file of files) {
        fs.mkdirSync(path.dirname(path.join(app, file)), { recursive: true });
        fs.copyFileSync(path.join(root, file), path.join(app, file));
    }
    mysqlPort = await port();
    const data = path.join(temporary, 'mysql-data');
    if (windows) run(path.join(mysqlBin, 'mysql_install_db.exe'), ['--datadir=' + data, '--port=' + mysqlPort, '--silent']);
    else run(mysqlServer, ['--no-defaults', '--initialize-insecure', '--datadir=' + data]);
    background(mysqlServer, ['--no-defaults', '--basedir=' + path.dirname(mysqlBin), '--datadir=' + data, '--bind-address=127.0.0.1', '--port=' + mysqlPort, '--skip-log-bin', ...(windows ? ['--console'] : ['--mysqlx=0', '--socket=' + path.join(temporary, 'mysql.sock'), '--pid-file=' + path.join(temporary, 'mysql.pid')])]);
    await until(() => { sql('SELECT 1', false); return true; }, 'Isolated MySQL/MariaDB did not start');
    sql('CREATE DATABASE securevault_rotation_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nUSE securevault_rotation_test;\n' + fs.readFileSync(path.join(root, 'database.example.sql'), 'utf8'), false);
    const ports = [await port(), await port(), await port()];
    origins = ports.map(value => 'http://127.0.0.1:' + value);
    const config = fs.readFileSync(path.join(root, 'config.example.php'), 'utf8').replace("'localhost'", "'127.0.0.1'").replace("'your_db_user'", "'root'").replace("'your_db_password'", "''").replace("'your_db_name'", "'securevault_rotation_test'").replace("'DB_PORT', 3306", "'DB_PORT', " + mysqlPort).replace('http://localhost/SecureVault/', origins[0] + '/').replace("'ENVIRONMENT', 'development'", "'ENVIRONMENT', 'production'");
    fs.writeFileSync(path.join(app, 'config.php'), config);
    const sessions = path.join(temporary, 'sessions');
    fs.mkdirSync(sessions);
    for (const value of ports) background(php, ['-d', 'session.save_path=' + sessions, '-d', 'log_errors=1', '-d', 'error_log=' + path.join(temporary, 'php-errors.log'), '-S', '127.0.0.1:' + value, '-t', app], app);
    for (const origin of origins) await until(async () => (await fetch(origin + '/login.php')).status === 200, 'Isolated PHP server did not start');
}
async function serverChecks() {
    const context = await createUser('server-check');
    const snapshot = await vault(context);
    const original = snapshot.accounts.map(account => account.password_encrypted);
    const updates = await replacements(snapshot);
    const staleCiphertext = await encrypt('Saved example 9 🔐', await key(oldPassword, snapshot.salt));
    const badCases = [
        ['missing entries', updates.slice(1), 409],
        ['empty list for a nonempty vault', [], 409],
        ['duplicate IDs', [updates[0], updates[0]], 400],
        ['foreign ID', [{ ...updates[0], id: 2147483647 }, updates[1]], 409],
        ['stale ciphertext', [{ ...updates[0], previous_password_encrypted: staleCiphertext }, updates[1]], 409],
        ['malformed ciphertext', [{ ...updates[0], password_encrypted: 'invalid' }, updates[1]], 400]
    ];
    for (const [label, accounts, status] of badCases) assert.equal((await rotate(context, accounts)).status, status, label);
    assert.equal((await rotate(context, updates, 'Incorrect-current-key!')).status, 400);
    assert.equal((await rotate(context, updates, oldPassword, oldPassword)).status, 400);
    assert.equal((await rotate(context, updates, oldPassword, 'A1!a'.repeat(20))).status, 400);
    assert.equal((await request('api/auth.php?action=update_master_password', { old_password: oldPassword, new_password: newPassword, accounts: '[]' }, context, 0, '')).status, 403);
    for (const accounts of ['', '{}', 'null', '[invalid', '[] trailing']) assert.equal((await request('api/auth.php?action=update_master_password', { old_password: oldPassword, new_password: newPassword, accounts }, context)).status, 400);
    assert.deepEqual((await vault(context)).accounts.map(account => account.password_encrypted), original);
    // A legacy nontransactional sessions table must disable rotation before any data changes.
    // Tokens are ASCII; keep this test-only index within MySQL's MyISAM byte limit.
    sql('RENAME TABLE sessions TO sessions_before_engine_test; CREATE TABLE sessions LIKE sessions_before_engine_test; ALTER TABLE sessions MODIFY session_token VARCHAR(255) CHARACTER SET ascii NOT NULL, ENGINE=MyISAM; INSERT INTO sessions SELECT * FROM sessions_before_engine_test');
    assert.equal((await rotate(context, updates)).status, 400);
    sql('DROP TABLE sessions; RENAME TABLE sessions_before_engine_test TO sessions');
    assert.deepEqual((await vault(context)).accounts.map(account => account.password_encrypted), original);
    sql('CREATE TRIGGER fail_rotation BEFORE UPDATE ON accounts FOR EACH ROW SET NEW.password_encrypted = IF(OLD.id = ' + snapshot.accounts[1].id + ', NULL, NEW.password_encrypted)');
    assert.equal((await rotate(context, updates)).status, 500);
    sql('DROP TRIGGER fail_rotation');
    assert.deepEqual((await vault(context)).accounts.map(account => account.password_encrypted), original, 'A mid-update error must roll back every entry');
    assert.equal((await request('api/auth.php?action=login', { username: context.username, password: newPassword })).status, 401);
    await signIn(context.username, oldPassword);
    const empty = await createUser('empty-check', 0);
    const emptySalt = (await vault(empty)).salt;
    assert.equal((await rotate(empty, [])).status, 200);
    assert.equal((await vault(await signIn(empty.username, newPassword))).salt, emptySalt, 'The existing salt must be preserved');
    console.log('PASS: malformed, incomplete, duplicate, foreign, stale, wrong-key, and CSRF requests fail safely; a mid-update database failure rolls back; empty vault rotation works.');

    const concurrent = await createUser('concurrency-check');
    const oldSession = await signIn(concurrent.username, oldPassword, 1);
    const before = await vault(concurrent);
    const changed = await replacements(before);
    sql('CREATE TRIGGER pause_rotation BEFORE UPDATE ON accounts FOR EACH ROW DO SLEEP(1.5)');
    const rotation = rotate(concurrent, changed);
    await until(() => Number(sql("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB = 'securevault_rotation_test' AND STATE = 'User sleep'")) > 0, 'Rotation did not reach the concurrency test barrier');
    const staleWrite = request('api/accounts.php?action=add_account', { service_name: 'Stale writer', username: 'sample', category_id: before.categories[0].id, encrypted_password: before.accounts[0].password_encrypted }, oldSession, 1);
    const staleLogin = request('api/auth.php?action=login', { username: concurrent.username, password: oldPassword }, {}, 2);
    const [changedResult, writeResult, loginResult] = await Promise.all([rotation, staleWrite, staleLogin]);
    sql('DROP TRIGGER pause_rotation');
    assert.equal(changedResult.status, 200);
    assert.equal(writeResult.status, 401, 'A write authenticated before rotation must be rejected after waiting for the user lock');
    assert.equal(loginResult.status, 401, 'An old-password sign-in must not create a new session after rotation');
    const after = await vault(await signIn(concurrent.username, newPassword));
    assert.equal(after.accounts.length, before.accounts.length);
    assert.equal(after.salt, before.salt);
    for (const account of after.accounts) await decrypt(account.password_encrypted, await key(newPassword, after.salt));
    console.log('PASS: concurrent old-session writes and old-password sign-ins cannot cross a successful rotation.');
}
async function browserPage(debugPort) {
    const target = await (await fetch('http://127.0.0.1:' + debugPort + '/json/new?about:blank', { method: 'PUT' })).json();
    const socket = new WebSocket(target.webSocketDebuggerUrl);
    sockets.push(socket);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    const pending = new Map();
    let id = 0;
    socket.onmessage = event => {
        const message = JSON.parse(event.data);
        if (message.id && pending.has(message.id)) {
            const { resolve, reject, timer } = pending.get(message.id);
            clearTimeout(timer); pending.delete(message.id);
            if (message.error) reject(new Error(message.error.message)); else resolve(message.result);
        }
    };
    const command = (method, params = {}) => new Promise((resolve, reject) => {
        const current = ++id;
        const timer = setTimeout(() => { pending.delete(current); reject(new Error('Browser command timed out: ' + method)); }, 20000);
        pending.set(current, { resolve, reject, timer });
        socket.send(JSON.stringify({ id: current, method, params }));
    });
    const evaluate = async expression => {
        const result = await command('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) throw new Error('Browser check failed: ' + (result.exceptionDetails.exception?.description || result.exceptionDetails.text));
        return result.result.value;
    };
    const navigate = async endpoint => {
        const marker = Date.now() + '-' + Math.random();
        await evaluate('window.testNavigationMarker = ' + JSON.stringify(marker));
        await command('Page.navigate', { url: origins[0] + endpoint });
        await until(() => evaluate('window.testNavigationMarker !== ' + JSON.stringify(marker) + ' && location.pathname === ' + JSON.stringify(endpoint.split('?')[0]) + ' && document.readyState === "complete"'), 'Browser navigation did not finish: ' + endpoint);
    };
    await command('Page.enable'); await command('Runtime.enable'); await command('Network.enable');
    await command('Network.setBlockedURLs', { urls: ['*://cdn.tailwindcss.com/*', '*://fonts.googleapis.com/*', '*://fonts.gstatic.com/*'] });
    await command('Page.addScriptToEvaluateOnNewDocument', { source: 'window.tailwind = {}; try { localStorage.setItem("seen_update_v1_3_enhanced", "true"); } catch {}' });
    return { evaluate, navigate, command };
}
async function vaultActionChecks(page, signInBrowser, context) {
    const empty = await createUser('empty-browser-check', 0);
    const previousCheck = await page.evaluate('localStorage.getItem(VAULT_KEY_CHECK_STORAGE)');
    await signInBrowser(oldPassword, empty.username);
    const emptyCheck = await page.evaluate('localStorage.getItem(VAULT_KEY_CHECK_STORAGE)');
    assert(!emptyCheck.includes(oldPassword), 'Browser key checks must not store the master password');
    const observeWrites = () => page.evaluate(`window.actionWrites=[];window.actionFetch=window.fetch;window.fetch=(url,options)=>{if(/action=(add_account|update|delete_account)(?:&|$)/.test(String(url)))window.actionWrites.push({url:String(url),body:String(options.body)});return window.actionFetch(url,options)};true`);
    const save = password => page.evaluate(`(async()=>{openAddAccountModal();document.getElementById('serviceName').value='Verified sample';document.getElementById('category').value=String((await VaultDB.getMeta('categories'))[0].id);document.getElementById('accountUsername').value='sample';document.getElementById('accountPassword').value='Saved example verified 🔐';document.getElementById('masterPassword').value=${JSON.stringify(password)};await handleAccountSubmit({preventDefault(){}})})()`);
    const writes = () => page.evaluate('window.actionWrites.length');
    await observeWrites();
    for (const encoded of [null, '{invalid', previousCheck, JSON.stringify({...JSON.parse(emptyCheck), salt:'wrong-salt'}), JSON.stringify({...JSON.parse(emptyCheck), encrypted:'invalid'})]) {
        await page.evaluate(encoded === null ? 'localStorage.removeItem(VAULT_KEY_CHECK_STORAGE)' : `localStorage.setItem(VAULT_KEY_CHECK_STORAGE,${JSON.stringify(encoded)})`);
        await save(oldPassword);
        assert.equal(await writes(), 0, 'Missing, malformed, wrong-session, wrong-salt, and damaged checks must block saves');
    }
    await page.evaluate(`localStorage.setItem(VAULT_KEY_CHECK_STORAGE,${JSON.stringify(emptyCheck)})`);
    await save('Mistyped-master-key-99!');
    assert.equal(await writes(), 0, 'A wrong key must not send a save request, even in an empty vault');
    assert.equal((await vault(empty)).accounts.length, 0);
    assert.match(await page.evaluate('document.getElementById("alertArea").textContent'), /Incorrect master key/);
    assert.equal(await page.evaluate('document.querySelector("#accountForm button[type=submit]").disabled'), false);

    await page.evaluate(`window.realStorageGet=Storage.prototype.getItem;Storage.prototype.getItem=function(name){if(name===VAULT_KEY_CHECK_STORAGE)throw new DOMException('blocked','SecurityError');return window.realStorageGet.call(this,name)}`);
    await save(oldPassword);
    assert.equal(await writes(), 0, 'Unavailable storage must fail closed');
    await page.evaluate('Storage.prototype.getItem=window.realStorageGet');
    await page.evaluate(`document.getElementById('masterPassword').value=${JSON.stringify(oldPassword)};Promise.all([handleAccountSubmit({preventDefault(){}}),handleAccountSubmit({preventDefault(){}})])`);
    assert.equal(await writes(), 1, 'Repeated submit must create only one entry');
    const posted = await page.evaluate('window.actionWrites[0].body');
    for (const value of new URLSearchParams(posted).values()) assert(!value.includes(oldPassword) && !value.includes('Saved example verified'), 'Save requests must contain ciphertext, not plaintext keys or passwords');
    assert.equal(await page.evaluate('document.getElementById("masterPassword").value'), '');
    assert.equal(await page.evaluate('document.getElementById("accountPassword").value'), '');
    const first = (await vault(empty)).accounts[0];
    assert.equal(await decrypt(first.password_encrypted, await key(oldPassword, (await vault(empty)).salt)), 'Saved example verified 🔐');
    await page.navigate('/dashboard.php');
    await page.evaluate('syncVaultFromNetwork()');
    await observeWrites();
    await page.evaluate(`deleteAccount(${first.id});document.getElementById('promptMasterKey').value='Wrong-delete-key!';handleMasterKeySubmit({preventDefault(){}})`);
    assert.equal(await writes(), 0, 'A wrong delete key must not send a delete request');
    assert.equal((await vault(empty)).accounts.length, 1);
    assert.equal(await page.evaluate('document.getElementById("masterKeyModal").classList.contains("hidden")'), false);
    assert.equal(await page.evaluate('document.getElementById("promptMasterKey").value'), '');
    assert.equal(await page.evaluate('document.getElementById("masterKeySubmitBtn").disabled'), false);
    await page.evaluate(`localStorage.setItem(VAULT_KEY_CHECK_STORAGE,'invalid')`);
    assert.equal(await page.evaluate(`executeDeleteAccount(${first.id},${JSON.stringify(oldPassword)})`), false);
    assert.equal(await writes(), 0, 'A damaged check must also block deletion');
    const deletionWrites = await page.evaluate(`(async()=>{localStorage.setItem(VAULT_KEY_CHECK_STORAGE,${JSON.stringify(emptyCheck)});document.getElementById('promptMasterKey').value=${JSON.stringify(oldPassword)};await handleMasterKeySubmit({preventDefault(){}});return window.actionWrites})()`);
    assert.equal(deletionWrites.length, 1);
    assert.equal((await vault(empty)).accounts.length, 0, 'A correct key must still delete the last entry');
    const deletion = new URLSearchParams(deletionWrites[0].body);
    assert.deepEqual([...deletion.keys()].sort(), ['action','id'], 'Deletion must not send the key to PHP');
    await page.navigate('/dashboard.php');
    await observeWrites();
    await page.evaluate('__triggerInactivityLock()');
    assert.equal(await page.evaluate('localStorage.getItem(VAULT_KEY_CHECK_STORAGE)'), null);
    await save(oldPassword);
    assert.equal(await writes(), 0, 'A locked vault must not save');

    // A successful server sign-in must not redirect if its browser check could not be stored.
    await page.navigate('/login.php');
    await page.evaluate(`window.realStorageSet=Storage.prototype.setItem;Storage.prototype.setItem=function(name,value){if(name===VAULT_KEY_CHECK_STORAGE)throw new DOMException('blocked','QuotaExceededError');return window.realStorageSet.call(this,name,value)};document.getElementById('login-username').value=${JSON.stringify(empty.username)};document.getElementById('login-password').value=${JSON.stringify(oldPassword)};handleLoginSubmit({preventDefault(){},target:document.getElementById('loginFormElement')})`);
    assert.equal(await page.evaluate('location.pathname'), '/login.php');
    assert.match(await page.evaluate('document.getElementById("alertArea").textContent'), /Allow browser storage/);
    assert.equal(await page.evaluate('document.getElementById("login-password").value'), '');
    await page.evaluate('Storage.prototype.setItem=window.realStorageSet');
    await signInBrowser(oldPassword);
    await observeWrites();
    const before = await vault(context);
    await page.evaluate(`executeEditFetch(${before.accounts[0].id},${JSON.stringify(oldPassword)})`);
    await page.evaluate(`document.getElementById('masterPassword').value='Wrong-edit-key!';document.getElementById('accountPassword').value='Saved example updated 🔐';handleAccountSubmit({preventDefault(){}})`);
    assert.equal(await writes(), 0);
    assert.equal((await vault(context)).accounts[0].password_encrypted, before.accounts[0].password_encrypted, 'A wrong key must not overwrite an existing entry');
    await page.evaluate(`document.getElementById('masterPassword').value=${JSON.stringify(oldPassword)};handleAccountSubmit({preventDefault(){}})`);
    assert.equal(await writes(), 1);
    const after = await vault(context);
    assert.equal(await decrypt(after.accounts[0].password_encrypted, await key(oldPassword, after.salt)), 'Saved example updated 🔐');
    await page.navigate('/dashboard.php');
    await page.evaluate('syncVaultFromNetwork()');
    console.log('PASS: empty-vault saves, edits, and deletions verify the signed-in key; wrong keys and invalid checks send no mutations; plaintext keys stay out of write requests; duplicate submits and locked saves are blocked; storage failures require another sign-in.');
}

async function browserChecks() {
    const context = await createUser('browser-check');
    const debugPort = await port();
    background(chrome, ['--headless=new', '--disable-gpu', ...(process.env.GITHUB_ACTIONS === 'true' ? ['--no-sandbox'] : []), '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=' + debugPort, '--user-data-dir=' + path.join(temporary, 'chrome'), 'about:blank']);
    await until(async () => (await fetch('http://127.0.0.1:' + debugPort + '/json/version')).ok, 'Isolated Chrome did not start');
    const page = await browserPage(debugPort);
    const signInBrowser = async (password, username = 'browser-check') => {
        await page.command('Network.clearBrowserCookies');
        await page.navigate('/login.php');
        await page.evaluate(`document.getElementById('login-username').value=${JSON.stringify(username)};document.getElementById('login-password').value=${JSON.stringify(password)};void handleLoginSubmit({preventDefault(){},target:document.getElementById('loginFormElement')});'started'`);
        await until(() => page.evaluate('location.pathname.endsWith("dashboard.php") && document.readyState === "complete"'), 'Browser sign-in did not prepare the key check and open the vault');
        assert.equal(await page.evaluate('syncVaultFromNetwork()'), true);
    };
    const beginChange = async (previous, next, wait = true) => {
        const script = `document.getElementById('oldPassword').value=${JSON.stringify(previous)};document.getElementById('newPassword').value=${JSON.stringify(next)};document.getElementById('confirmNewPassword').value=${JSON.stringify(next)};window.testChange=handleSettingsSubmit({preventDefault(){}});`;
        await page.evaluate(script + (wait ? 'window.testChange' : '"started"'));
    };
    await signInBrowser(oldPassword);
    await vaultActionChecks(page, signInBrowser, context);
    await page.evaluate('VaultDB.saveAll([], {rotation_pending:false})');
    await beginChange('Incorrect-current-key!', newPassword);
    assert.match(await page.evaluate('document.getElementById("keyChangeStatus").textContent'), /Nothing was changed/);
    assert.equal((await vault(context)).accounts.length, 2);
    await page.evaluate('window.realSaveAll=VaultDB.saveAll;VaultDB.saveAll=async()=>{throw new Error("simulated quota failure")};');
    await beginChange(oldPassword, newPassword);
    assert.match(await page.evaluate('document.getElementById("keyChangeStatus").textContent'), /Nothing was changed/);
    await page.evaluate('VaultDB.saveAll=window.realSaveAll');

    const beforeFailure = await vault(context);
    sql('CREATE TRIGGER browser_rollback BEFORE UPDATE ON accounts FOR EACH ROW SET NEW.password_encrypted = IF(OLD.id = ' + beforeFailure.accounts[1].id + ', NULL, NEW.password_encrypted)');
    await beginChange(oldPassword, newPassword);
    sql('DROP TRIGGER browser_rollback');
    assert.equal(await page.evaluate('VaultDB.getMeta("rotation_pending")'), true);
    assert.equal(await page.evaluate('getEffectiveSalt()'), null, 'Unconfirmed changes must block stale offline decryption');
    assert.equal(await page.evaluate('syncVaultFromNetwork()'), true);
    assert.equal(await page.evaluate('VaultDB.getMeta("rotation_pending")'), false);
    assert.deepEqual((await vault(context)).accounts.map(account => account.password_encrypted), beforeFailure.accounts.map(account => account.password_encrypted));

    // Start with an empty local cache and queue another tab's sync behind rotation.
    const other = await browserPage(debugPort);
    await other.navigate('/dashboard.php');
    await other.evaluate('syncVaultFromNetwork()');
    await page.evaluate('VaultDB.saveAll([], {rotation_pending:false})');
    await page.evaluate('void navigator.locks.request(VAULT_CACHE_LOCK, async()=>{window.testLockHeld=true;await new Promise(resolve=>{window.releaseTestLock=resolve})});"queued"');
    await until(() => page.evaluate('window.testLockHeld === true'), 'Cache test lock did not start');
    await beginChange(oldPassword, newPassword, false);
    assert.equal(await page.evaluate('document.getElementById("settingsSubmitBtn").disabled'), true);
    await other.evaluate('void syncVaultFromNetwork();"queued"');
    await page.evaluate('window.releaseTestLock();"released"');
    await until(() => page.evaluate('location.pathname.endsWith("login.php") && document.readyState === "complete"'), 'Successful key change did not request sign-in');
    const cached = await other.evaluate('VaultDB.getAll()');
    assert.equal(cached.length, 2, 'Rotation must include entries missing from local storage');
    const salt = await other.evaluate('VaultDB.getMeta("salt")');
    const retiredKey = await key(oldPassword, salt);
    for (const account of cached) {
        assert.match(await decrypt(account.password_encrypted, await key(newPassword, salt)), /Saved example/);
        await assert.rejects(() => decrypt(account.password_encrypted, retiredKey));
    }
    assert.equal((await request('api/accounts.php?action=sync_vault', undefined, context)).status, 401);
    assert.equal((await request('api/auth.php?action=login', {username:context.username,password:oldPassword})).status, 401);
    await signInBrowser(newPassword);
    const currentSession = await signIn(context.username, newPassword);
    assert.equal((await request('api/accounts.php?action=delete_account', {id:cached[0].id}, {cookie:currentSession.cookie,csrf:context.csrf})).status, 403, 'An old tab must not write using its stale page token after another tab signs in again');
    // Exercise the normal add/delete UI after the new CSRF checks.
    await page.evaluate(`openAddAccountModal();document.getElementById('serviceName').value='Disposable sample';document.getElementById('category').value=${JSON.stringify(String(beforeFailure.categories[0].id))};document.getElementById('accountUsername').value='sample';document.getElementById('accountPassword').value='Disposable saved password!';document.getElementById('masterPassword').value=${JSON.stringify(newPassword)};handleAccountSubmit({preventDefault(){}})`);
    await sleep(1100);
    await until(() => page.evaluate('(async()=> (await VaultDB.getAll()).length===3)()'), 'The account form must still save successfully');
    await page.evaluate(`(async()=>{const account=(await VaultDB.getAll()).find(item=>item.service_name==='Disposable sample');await executeDeleteAccount(account.id,${JSON.stringify(newPassword)})})()`);
    assert.equal((await page.evaluate('VaultDB.getAll()')).length, 2, 'The account delete flow must still work');
    await until(() => page.evaluate('Boolean(navigator.serviceWorker.controller)'), 'Service worker did not control the sample dashboard');
    await until(() => page.evaluate('(async()=>Boolean(await caches.match(location.href)))()'), 'The offline dashboard was not cached');
    await page.command('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: 0, uploadThroughput: 0 });
    await page.navigate('/dashboard.php');
    assert.equal(await page.evaluate('navigator.onLine'), false);
    const revealed = await page.evaluate(`(async()=>{const accounts=await VaultDB.getAll();const id=accounts[0].id;await executeRevealPassword(id,null,${JSON.stringify(newPassword)});return document.querySelector('.password-display-'+id).textContent})()`);
    assert.match(revealed, /Saved example/, 'The new key must reveal an entry after an offline reload');
    await page.command('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    await page.navigate('/dashboard.php');
    await page.evaluate('syncVaultFromNetwork()');
    console.log('PASS: browser storage failures preserve data; a missing local vault is refreshed; stale tabs cannot write; add/delete still work; queued sync cannot restore old ciphertext; the new key works after an offline reload.');

    // The server commits, but the browser loses the response. No blind retry or stale offline use.
    const thirdPassword = 'Sample-third-vault-key-44!';
    await page.evaluate('window.realFetch=window.fetch;window.fetch=async (...args)=>{const response=await window.realFetch(...args);if(String(args[0]).includes("update_master_password"))throw new TypeError("simulated lost response");return response}');
    await beginChange(newPassword, thirdPassword);
    assert.equal(await page.evaluate('VaultDB.getMeta("rotation_pending")'), true);
    assert.equal(await page.evaluate('getEffectiveSalt()'), null);
    assert.match(await page.evaluate('document.getElementById("keyChangeStatus").textContent'), /could not be confirmed/);
    await signInBrowser(thirdPassword);
    assert.equal(await page.evaluate('VaultDB.getMeta("rotation_pending")'), false);
    for (const account of await page.evaluate('VaultDB.getAll()')) await decrypt(account.password_encrypted, await key(thirdPassword, salt));

    const fourthPassword = 'Sample-fourth-vault-key-45!';
    await page.evaluate('window.realSaveAll=VaultDB.saveAll;window.saveCount=0;VaultDB.saveAll=async function(...args){if(++window.saveCount===2)throw new Error("simulated quota failure after commit");return window.realSaveAll.apply(this,args)}');
    await beginChange(thirdPassword, fourthPassword, false);
    await until(() => page.evaluate('location.pathname.endsWith("login.php") && location.search.includes("offline_refresh=1") && document.readyState==="complete"'), 'A post-commit storage failure must explain recovery using the new key');
    await signInBrowser(fourthPassword);
    assert.equal(await page.evaluate('VaultDB.getMeta("rotation_pending")'), false);
    for (const account of await page.evaluate('VaultDB.getAll()')) await decrypt(account.password_encrypted, await key(fourthPassword, salt));
    assert(!fs.existsSync(path.join(app, 'api/debug.txt')), 'Authentication requests must not create a debug log');
    console.log('PASS: lost-response and post-commit storage failures recover by signing in with the new key, without losing entries.');
}

(async () => {
    try {
        await setup();
        await serverChecks();
        await browserChecks();
        console.log('PASS: safe master-password change integration checks completed.');
    } finally {
        for (const socket of sockets) socket.close();
        for (const child of children.reverse()) {
            if (child.exitCode === null) {
                const ended = new Promise(resolve => child.once('exit', resolve));
                child.kill();
                await Promise.race([ended, sleep(3000)]);
            }
        }
        const resolved = path.resolve(temporary);
        assert.equal(path.dirname(resolved), path.resolve(os.tmpdir()));
        assert(path.basename(resolved).startsWith('securevault-rotation-test-'));
        fs.rmSync(resolved, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
