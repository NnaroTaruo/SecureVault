// Run: node tests/master-password-change.cjs
// Uses isolated sample data; defaults to XAMPP on Windows or MySQL and Chrome on Ubuntu.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const http = require('node:http');
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
const phpServers = [];
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
async function stopAppServer() {
    const server = phpServers[0];
    const ended = new Promise(resolve => server.once('exit', resolve));
    server.kill();
    await ended;
}
async function restartAppServer() {
    phpServers[0] = background(php, phpServers[0].spawnargs.slice(1), app);
    await until(async () => (await fetch(origins[0] + '/login.php')).status === 200, 'PHP server did not restart');
}
function sql(statement, database = true) {
    return run(path.join(mysqlBin, windows ? 'mysql.exe' : 'mysql'), ['--no-defaults', '--host=127.0.0.1', '--port=' + mysqlPort, '--user=root', '--batch', '--skip-column-names', ...(database ? ['--database=securevault_rotation_test'] : [])], { input: statement });
}
async function request(endpoint, body, context = {}, server = 0, csrf = context.csrf) {
    const response = await fetch(origins[server] + '/' + endpoint, {
        method: body === undefined ? 'GET' : 'POST', redirect: 'manual',
        headers: { ...context.headers, ...(context.cookie ? { Cookie: context.cookie } : {}), ...(csrf ? { 'X-CSRF-Token': csrf } : {}), ...(context.vaultContext ? {'X-Vault-Context':context.vaultContext} : {}) },
        ...(body === undefined ? {} : { body: new URLSearchParams(body) }), signal: AbortSignal.timeout(15000)
    });
    const cookieJar = new Map((context.cookie || '').split('; ').filter(Boolean).map(value => [value.split('=')[0], value.slice(value.indexOf('=')+1)]));
    for (const header of response.headers.getSetCookie()) {
        const pair = header.split(';')[0]; const name = pair.split('=')[0]; const value = pair.slice(pair.indexOf('=')+1);
        if (/max-age=0/i.test(header) || value === 'deleted') cookieJar.delete(name); else cookieJar.set(name,value);
    }
    const cookie = [...cookieJar].map(([name,value]) => name+'='+value).join('; ');
    return { status: response.status, data: await response.json(), cookie, retryAfter: response.headers.get('Retry-After') };
}
// Use real loopback source addresses to exercise the account limit across different IPs.
function loginFromAddress(username, password, localAddress, server=0) {
    return new Promise((resolve, reject) => {
        const body = new URLSearchParams({username,password}).toString();
        const req = http.request(origins[server] + '/api/auth.php?action=login', {method:'POST',localAddress,headers:{'Content-Type':'application/x-www-form-urlencoded','Content-Length':Buffer.byteLength(body)}}, response => {
            let text = '';
            response.setEncoding('utf8');
            response.on('data', chunk => { text += chunk; });
            response.on('end', () => { try { resolve({status:response.statusCode,data:JSON.parse(text),retryAfter:response.headers['retry-after']}); } catch (error) { reject(error); } });
            response.on('error', reject);
        });
        req.on('error', reject);
        req.setTimeout(15000, () => req.destroy(new Error('Source-address login timed out')));
        req.end(body);
    });
}
async function signIn(username, password, server = 0) {
    const result = await request('api/auth.php?action=login', { username, password }, {}, server);
    assert.equal(result.status, 200, 'Sample sign-in failed');
    const html = await (await fetch(origins[server] + '/dashboard.php', { headers: { Cookie: result.cookie } })).text();
    const csrf = html.match(/const CSRF_TOKEN = "([a-f0-9]+)"/);
    assert(csrf, 'The dashboard did not provide a CSRF token');
    return { cookie: result.cookie, csrf: csrf[1], username, vaultContext: result.data.data.context };
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
    for (const value of ports) phpServers.push(background(php, ['-d', 'session.save_path=' + sessions, '-d', 'log_errors=1', '-d', 'error_log=' + path.join(temporary, 'php-errors.log'), '-S', '127.0.0.1:' + value, '-t', app], app));
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
async function legacySessionChecks() {
    const context = await createUser('legacy-session-check',1);
    const snapshot = await vault(context);
    for (const field of ['key_check_context','csrf_token']) {
        const session = await signIn(context.username,oldPassword);
        const id = session.cookie.match(/(?:^|; )PHPSESSID=([^;]+)/)?.[1];
        assert(id,'The isolated PHP session cookie must be available');
        run(php,['-d','session.save_path='+path.join(temporary,'sessions'),'-r', 'session_id($argv[1]);session_start();unset($_SESSION[$argv[2]]);session_write_close();',id,field]);
        const login = await fetch(origins[0]+'/login.php',{headers:{Cookie:session.cookie},redirect:'manual'});
        assert.equal(login.status,200,'Legacy sessions must reach the sign-in gateway instead of redirecting into a closed vault');
        assert((await login.text()).includes('id="loginFormElement"'));
        assert.equal((await request('api/auth.php?action=me',undefined,session)).status,401,'Legacy sessions must no longer authorize API access');
        const current = await signIn(context.username,oldPassword);
        assert.deepEqual((await vault(current)).accounts,snapshot.accounts,'Retiring a legacy sign-in must preserve the vault');
    }
    console.log('PASS: legacy sessions missing browser-bound metadata return to sign-in and preserve vault entries.');
}

async function loginRateChecks() {
    const context = await createUser('throttle-check', 1);
    const snapshot = await vault(context);
    const id = snapshot.user_id;
    const accountHash = await webcrypto.subtle.digest('SHA-256', Buffer.from('account:' + id)).then(buffer=>Buffer.from(buffer).toString('hex'));
    const counter = () => sql("SELECT failures,reset_at FROM login_throttles WHERE bucket_hash='"+accountHash+"'").trim().split('\t').map(Number);
    const clear = () => sql('DELETE FROM login_throttles');
    const wrong = (username=context.username, server=0, options={}) => request('api/auth.php?action=login', {username,password:'Wrong-sample-key!'}, options, server);
    const valid = () => request('api/auth.php?action=login', {username:context.username,password:oldPassword});
    const sessions = () => Number(sql('SELECT COUNT(*) FROM sessions WHERE user_id='+id).trim());

    // Existing installations fail closed until the additive migration is imported.
    sql('DROP TABLE login_throttles');
    const before = sessions();
    assert.equal((await valid()).status, 503);
    assert.equal(sessions(), before);
    const migration = fs.readFileSync(path.join(root,'migrations/2026-10-27-login-throttles.sql'),'utf8');
    sql(migration); sql(migration);
    assert.deepEqual((await vault(context)).accounts, snapshot.accounts, 'Migration must preserve the existing encrypted vault');
    assert.equal((await vault(context)).salt, snapshot.salt);

    for (const [index,alias] of [context.username,context.username+'@example.test',context.username.toUpperCase(),'throttlé-check'].entries()) {
        assert.equal((await wrong(alias,index%3)).status,401, 'Username, email, case, and database-collation aliases must share failed attempts');
    }
    assert.equal(counter()[0],4);
    const limited = await loginFromAddress(context.username+'@example.test','Wrong-sample-key!','127.0.0.2');
    assert.equal(limited.status,429, 'Changing source addresses must not bypass the account limit');
    assert.match(limited.data.message,/Too many sign-in attempts.*Try again/);
    assert.equal(Object.hasOwn(limited.data,'data'),false,'A cooldown response must not include sign-in metadata');
    assert(Number(limited.retryAfter)>0 && Number(limited.retryAfter)<=900);
    const resetAt = counter()[1];
    assert.equal((await loginFromAddress(context.username,oldPassword,'127.0.0.3')).status,429, 'Even a correct password must wait for the temporary account cooldown');
    assert.equal((await wrong(context.username,1,{cookie:context.cookie})).status,429);
    assert.equal((await wrong()).status,429, 'Clearing cookies must not reset server-side limits');
    assert.equal(counter()[1],resetAt,'Blocked retries must not extend the account cooldown');
    assert.equal(sessions(),before,'Throttled requests must not create authenticated sessions');
    assert.equal((await vault(context)).accounts.length,1,'A cooldown must not revoke an existing session or offline copy');
    await createUser('unaffected-throttle-check',0);
    sql("UPDATE login_throttles SET reset_at=UNIX_TIMESTAMP()-1 WHERE bucket_hash='"+accountHash+"'");
    assert.equal((await valid()).status,200,'Cooldown expiry must allow the correct password again');
    assert.equal(sql("SELECT COUNT(*) FROM login_throttles WHERE bucket_hash='"+accountHash+"'").trim(),'0','Success must clear this account counter');

    clear();
    await wrong(); await wrong();
    sql("UPDATE login_throttles SET reset_at=UNIX_TIMESTAMP()-1 WHERE bucket_hash='"+accountHash+"'");
    assert.equal((await wrong()).status,401);
    assert.equal(counter()[0],1,'Expired observation windows must start a new failed-attempt count');
    assert.equal((await valid()).status,200);

    // A mid-transaction counter failure must neither authenticate nor leave a partial count.
    await wrong();
    const countersBefore = sql('SELECT * FROM login_throttles ORDER BY bucket_hash');
    const sessionsBefore = sessions();
    sql("CREATE TRIGGER fail_login_count BEFORE UPDATE ON login_throttles FOR EACH ROW SET NEW.failures=IF(OLD.bucket_hash='"+accountHash+"',NULL,NEW.failures)");
    assert.equal((await wrong()).status,503);
    sql('DROP TRIGGER fail_login_count');
    assert.equal(sessions(),sessionsBefore);
    assert.equal(sql('SELECT * FROM login_throttles ORDER BY bucket_hash'),countersBefore,'Counter failures must roll back the entire login transaction');

    clear();
    const parallel = await Promise.all(Array.from({length:7},(_,index)=>loginFromAddress(context.username,'Wrong-sample-key!','127.0.0.'+(index+2),index%3)));
    assert.deepEqual(parallel.map(result=>result.status).sort(),[401,401,401,401,429,429,429],'Concurrent requests must not pass more guesses than the threshold');
    assert.equal(counter()[0],5);

    clear();
    const unknown=[];
    for(let attempt=0;attempt<5;attempt++) unknown.push(await wrong('unknown-throttle-check'));
    assert.deepEqual(unknown.map(result=>result.status),[401,401,401,401,429],'Unknown identifiers must get the same failure and cooldown responses');
    assert.deepEqual(Object.keys(unknown[4].data).sort(),Object.keys(limited.data).sort());
    assert.equal(unknown[4].data.message,limited.data.message);
    const stored = sql('SELECT * FROM login_throttles');
    for(const value of ['unknown-throttle-check','127.0.0.1','Wrong-sample-key!',oldPassword]) assert(!stored.includes(value),'Counters must not store raw identifiers, IPs, or passwords');

    clear();
    for(let attempt=0;attempt<22;attempt++) {
        assert.equal((await wrong('spray-'+attempt,attempt%3,{headers:{'X-Forwarded-For':'198.51.100.'+(attempt+1),'X-Real-IP':'203.0.113.'+(attempt+1)}})).status,401);
        if(attempt===11) assert.equal((await valid()).status,200,'A successful sign-in must not erase source-wide failed guesses');
    }
    const sprayed = await Promise.all(Array.from({length:3},(_,index)=>wrong('spray-last-'+index,index)));
    assert.deepEqual(sprayed.map(result=>result.status).sort(),[401,401,429],'Concurrent requests with rotating identifiers must still hit the source-wide limit');
    const size = sql('SELECT COUNT(*) FROM login_throttles').trim();
    assert.equal((await valid()).status,429);
    assert.equal((await wrong('another-unknown')).status,429);
    assert.equal(sql('SELECT COUNT(*) FROM login_throttles').trim(),size,'A blocked source must not create unlimited identifier counters');
    assert.equal((await loginFromAddress(context.username,oldPassword,'127.0.0.2')).status,200,'An unrelated source remains available when only this source is throttled');
    sql('UPDATE login_throttles SET reset_at=UNIX_TIMESTAMP()-1');
    assert.equal((await valid()).status,200,'Expired source counters must release the source cooldown');
    assert.equal(sql('SELECT COUNT(*) FROM login_throttles').trim(),'1','Expired counters must be cleaned up during sign-in');
    assert.equal((await request('api/auth.php?action=login', {'username[]':context.username,password:oldPassword})).status,400);
    assert.equal((await request('api/auth.php?action=login', {username:context.username,'password[]':oldPassword})).status,400);
    clear();
    console.log('PASS: database migration preserves vaults; account aliases, new cookies and IPs cannot bypass cooldowns; concurrent guesses stay bounded; unknown identifiers get generic responses; spoofed proxy headers cannot bypass source limits; expiry and successful sign-in recover safely.');
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
        try {
            await until(() => evaluate('window.testNavigationMarker !== ' + JSON.stringify(marker) + ' && location.pathname === ' + JSON.stringify(endpoint.split('?')[0]) + ' && document.readyState === "complete"'), 'Browser navigation did not finish: ' + endpoint);
        } catch (error) {
            throw new Error(error.message + ' (current page: ' + await evaluate('location.pathname + location.search + "; " + document.readyState + "; " + document.title') + ')');
        }
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

async function offlineIsolationChecks(page, signInBrowser, debugPort) {
    const alice = await createUser('offline-alice', 1);
    const bob = await createUser('offline-bob', 1);
    const a = await vault(alice);
    const b = await vault(bob);
    sql("UPDATE accounts SET service_name='Alice private service' WHERE id=" + a.accounts[0].id + "; UPDATE accounts SET service_name='Bob private service' WHERE id=" + b.accounts[0].id + "; UPDATE categories SET name='Alice private category' WHERE id=" + a.categories[0].id);
    // Simulate the old release's unowned database and an unrelated application's cache.
    await page.evaluate(`(async()=>{const cache=await caches.open('unrelated-app');await cache.put('/unrelated',new Response('keep me'));await new Promise((resolve,reject)=>{const r=indexedDB.open('SecureVaultDB',1);r.onupgradeneeded=()=>r.result.createObjectStore('vault_items');r.onsuccess=()=>{r.result.close();resolve()};r.onerror=()=>reject(r.error)})})()`);
    await signInBrowser(oldPassword, alice.username);
    assert.equal(await page.evaluate(`(async()=>!(await indexedDB.databases()).some(db=>db.name==='SecureVaultDB'))()`), true, 'Online sign-in must remove the old unowned database');
    const oldTab = await browserPage(debugPort);
    await oldTab.navigate('/dashboard.php').catch(error => { throw new Error('Account-switch setup: ' + error.message); });
    await oldTab.evaluate('syncVaultFromNetwork()');
    const oldCsrf = await oldTab.evaluate('CSRF_TOKEN');
    const oldContext = await oldTab.evaluate('VAULT_OWNER.context');
    const dbName = await page.evaluate('VaultDB.DB_NAME');
    assert.match(await oldTab.evaluate('document.body.textContent'), /Alice private service/);
    const html = await page.evaluate(`(async()=>await (await fetch('dashboard.php',{cache:'no-store'})).text())()`);
    for (const value of ['offline-alice','Alice private service','Alice private category']) assert(!html.includes(value), 'Dashboard HTML must remain neutral');

    await signInBrowser(oldPassword, bob.username);
    await until(() => oldTab.evaluate('isVaultLocked'), 'Account switching must close the old tab');
    for (const value of ['offline-alice','Alice private service','Alice private category']) assert(!(await oldTab.evaluate('document.documentElement.outerHTML')).includes(value), 'Old tabs must discard personal DOM and category options');
    assert.equal(await oldTab.evaluate(`(async()=>{try{await VaultDB.getAll();return false}catch{return true}})()`), true, 'Old tabs must not read the new account copy');
    assert.equal(await oldTab.evaluate(`(async()=>{try{await VaultDB.saveAll([{id:999,service_name:'stale'}]);return false}catch{return true}})()`), true, 'Old tabs must not overwrite the new account copy');
    assert.equal(await oldTab.evaluate('syncVaultFromNetwork()'), false);
    assert.equal(await oldTab.evaluate(`(async()=> (await fetch('api/accounts.php?action=sync_vault',{headers:vaultHeaders()})).status)()`), 403, 'The server must reject an old tab context even with the new session cookie');
    assert.equal(await oldTab.evaluate('executeLogout()'), false, 'An old tab must not sign out the new account');
    assert.equal(await page.evaluate(`(async()=> (await fetch('api/auth.php?action=logout',{method:'POST',headers:{'X-CSRF-Token':${JSON.stringify(oldCsrf)}}})).status)()`), 403, 'An old logout request must not end the new session');
    const bobCheck = await page.evaluate('localStorage.getItem(VAULT_KEY_CHECK_STORAGE)');
    await oldTab.evaluate('isVaultLocked=false;__triggerInactivityLock()');
    assert.equal(await page.evaluate('localStorage.getItem(VAULT_KEY_CHECK_STORAGE)'), bobCheck, 'An old inactivity timer must not clear the new account check');
    assert.deepEqual((await page.evaluate('VaultDB.getAll()')).map(account=>account.id), [Number(b.accounts[0].id)]);
    assert.equal(await page.evaluate(`(async()=>{try{await rememberVaultKey('sample','sample',${JSON.stringify(oldContext)},()=>false);return false}catch{return true}})()`), true);
    assert.equal(await page.evaluate('localStorage.getItem(VAULT_KEY_CHECK_STORAGE)'), bobCheck, 'Delayed sign-in cryptography must not replace a newer check');

    const delayed = await browserPage(debugPort);
    await delayed.navigate('/dashboard.php').catch(error => { throw new Error('Delayed-response setup: ' + error.message); });
    await delayed.evaluate('syncVaultFromNetwork()');
    // Hold a real, valid response while another tab signs out offline.
    await delayed.evaluate(`window.realFetch=window.fetch;window.fetch=async(...args)=>{const response=await window.realFetch(...args);if(String(args[0]).includes('sync_vault')){window.responseHeld=true;await new Promise(resolve=>window.releaseResponse=resolve)}return response};window.delayedSync=syncVaultFromNetwork();'started'`);
    await until(() => delayed.evaluate('window.responseHeld'), 'The delayed sync response did not arrive');
    const tokens = await page.evaluate('[CSRF_TOKEN,VAULT_OWNER.context]');
    const cached = await page.evaluate(`(async()=>{const entries=[];for(const name of await caches.keys()){if(!name.startsWith('securevault:'))continue;const cache=await caches.open(name);for(const request of await cache.keys()){const response=await cache.match(request);entries.push({url:request.url,text:await response.text()})}}return entries})()`);
    assert(cached.some(entry=>entry.url.endsWith('dashboard.php?offline_shell=1')), 'The neutral offline shell must be cached');
    for (const entry of cached) for (const value of ['offline-alice','offline-bob','Alice private service','Bob private service',...tokens]) assert(!entry.text.includes(value), 'Cached pages must not contain personal fields or session tokens');
    // Page-level CDP offline emulation does not reliably affect service-worker fetches.
    await stopAppServer();
    await page.command('Network.emulateNetworkConditions', {offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
    await delayed.command('Network.emulateNetworkConditions', {offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
    await page.navigate('/dashboard.php');
    assert.equal(await page.evaluate('OFFLINE_SHELL'), true, 'Disconnected navigation must use the neutral shell');
    await page.evaluate('checkRenderOfflineVault()');
    const offlinePassword = await page.evaluate(`(async()=>{const account=(await VaultDB.getAll())[0];await executeRevealPassword(account.id,null,${JSON.stringify(oldPassword)});return document.querySelector('.password-display-'+account.id).textContent})()`);
    assert.match(offlinePassword, /Saved example/, 'The current account must decrypt with the server stopped');
    await page.evaluate('void executeLogout();"started"');
    await until(() => delayed.evaluate('isVaultLocked'), 'Offline sign-out must close the other tab before waiting on its response');
    await delayed.evaluate('window.releaseResponse();"released"');
    assert.equal(await delayed.evaluate('window.delayedSync'), false, 'A delayed response must not restore signed-out data');
    await until(() => page.evaluate('location.pathname.endsWith("index.php") && document.readyState==="complete"'), 'Offline sign-out did not finish cleanup');
    const rawCounts = () => page.evaluate(`(async()=>new Promise((resolve,reject)=>{const r=indexedDB.open(${JSON.stringify(dbName)});r.onsuccess=()=>{const db=r.result;const tx=db.transaction(['vault_items','vault_meta']);const counts=[];for(const name of ['vault_items','vault_meta']){const c=tx.objectStore(name).count();c.onsuccess=()=>counts.push(c.result)}tx.oncomplete=()=>{db.close();resolve(counts)};tx.onerror=()=>reject(tx.error)};r.onerror=()=>reject(r.error)}))()`);
    assert.deepEqual(await rawCounts(), [0,0], 'Explicit offline sign-out must physically clear entries and metadata');
    assert.equal(await page.evaluate('localStorage.getItem("securevault-key-check")'), null);
    assert.equal(await page.evaluate(`(async()=>await (await caches.match('/unrelated')).text())()`), 'keep me', 'Sign-out must preserve unrelated app caches');
    await page.navigate('/dashboard.php').catch(error => { throw new Error('Offline reload after sign-out: ' + error.message); });
    assert.equal(await page.evaluate('OFFLINE_SHELL'), true);
    assert.equal(await page.evaluate('isVaultLocked'), true);
    assert(!(await page.evaluate('document.body.textContent')).includes('Bob private service'), 'Offline reload after logout must remain closed');
    // Reconnect without letting the browser's online handler navigate; inspect the old cookie directly.
    const cookies = (await page.command('Network.getCookies', {urls:[origins[0]]})).cookies.map(cookie=>cookie.name+'='+cookie.value).join('; ');
    assert(cookies.includes('sv_signed_out=1'));
    assert.equal((await request('api/auth.php?action=me', undefined, {cookie:cookies}, 1)).status, 401, 'Offline logout must invalidate the old server session on its next connected request');
    await restartAppServer();
    await page.command('Network.emulateNetworkConditions', {offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
    await signInBrowser(oldPassword, bob.username);
    await page.evaluate('window.realReset=VaultDB.reset;VaultDB.reset=async()=>{throw new Error("simulated cleanup failure")}');
    assert.equal(await page.evaluate('executeLogout()'), false, 'Cleanup failure must not report success');
    assert.match(await page.evaluate('document.body.textContent'), /browser cleanup could not finish/);
    assert.equal(await page.evaluate('VaultDevice.active()'), null, 'Failed cleanup must still revoke device access');
    await page.evaluate('VaultDB.reset=window.realReset');
    await signInBrowser(oldPassword, bob.username);
    assert.equal((await page.evaluate('VaultDB.getAll()')).length, 1, 'Signing in must recover the correct account after cleanup failure');
    await page.evaluate('void executeLogout();"started"');
    await until(() => page.evaluate('location.pathname.endsWith("index.php") && document.readyState==="complete"'), 'Online sign-out did not finish');
    assert.deepEqual(await rawCounts(), [0,0]);
    console.log('PASS: account switching isolates cache and tabs; neutral cached pages contain no personal data; explicit online/offline logout clears device storage; delayed replies cannot restore it; cleanup failure revokes access and explains recovery.');
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
    for(let attempt=0;attempt<5;attempt++) await request('api/auth.php?action=login',{username:context.username,password:'Wrong-browser-login-key!'});
    await page.navigate('/login.php');
    await page.evaluate(`document.getElementById('login-username').value=${JSON.stringify(context.username)};document.getElementById('login-password').value=${JSON.stringify(oldPassword)};handleLoginSubmit({preventDefault(){},target:document.getElementById('loginFormElement')})`);
    assert.equal(await page.evaluate('location.pathname'),'/login.php');
    assert.match(await page.evaluate('document.getElementById("alertArea").textContent'),/Too many sign-in attempts.*Try again/);
    assert.equal(await page.evaluate('document.getElementById("login-password").value'),'');
    assert.equal(await page.evaluate('document.querySelector("#loginFormElement button[type=submit]").disabled'),false);
    assert.equal(await page.evaluate('localStorage.getItem(VAULT_KEY_CHECK_STORAGE)'),null,'A blocked sign-in must not create a key check');
    assert.equal(await page.evaluate('VaultDevice.active()'),null);
    sql('UPDATE login_throttles SET reset_at=UNIX_TIMESTAMP()-1');
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
    assert.equal((await request('api/accounts.php?action=delete_account', {id:cached[0].id}, {...currentSession,csrf:context.csrf})).status, 403, 'An old tab must not write using its stale page token after another tab signs in again');
    // Exercise the normal add/delete UI after the new CSRF checks.
    await page.evaluate(`openAddAccountModal();document.getElementById('serviceName').value='Disposable sample';document.getElementById('category').value=${JSON.stringify(String(beforeFailure.categories[0].id))};document.getElementById('accountUsername').value='sample';document.getElementById('accountPassword').value='Disposable saved password!';document.getElementById('masterPassword').value=${JSON.stringify(newPassword)};handleAccountSubmit({preventDefault(){}})`);
    await sleep(1100);
    await until(() => page.evaluate('(async()=> (await VaultDB.getAll()).length===3)()'), 'The account form must still save successfully');
    await page.evaluate(`(async()=>{const account=(await VaultDB.getAll()).find(item=>item.service_name==='Disposable sample');await executeDeleteAccount(account.id,${JSON.stringify(newPassword)})})()`);
    assert.equal((await page.evaluate('VaultDB.getAll()')).length, 2, 'The account delete flow must still work');
    await until(() => page.evaluate('Boolean(navigator.serviceWorker.controller)'), 'Service worker did not control the sample dashboard');
    await until(() => page.evaluate('(async()=>Boolean(await caches.match(new URL("dashboard.php?offline_shell=1",location.href))))()'), 'The neutral offline dashboard was not cached');
    await stopAppServer();
    await page.command('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: 0, uploadThroughput: 0 });
    await page.navigate('/dashboard.php');
    assert.equal(await page.evaluate('navigator.onLine'), false);
    await page.evaluate('checkRenderOfflineVault()');
    const revealed = await page.evaluate(`(async()=>{const accounts=await VaultDB.getAll();const id=accounts[0].id;await executeRevealPassword(id,null,${JSON.stringify(newPassword)});return document.querySelector('.password-display-'+id).textContent})()`);
    assert.match(revealed, /Saved example/, 'The new key must reveal an entry after an offline reload');
    assert.equal(await page.evaluate('OFFLINE_SHELL'), true);
    await restartAppServer();
    await page.command('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    await until(() => page.evaluate('OFFLINE_SHELL === false && document.readyState === "complete"'), 'Reconnecting must reload the authenticated dashboard');
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
    await offlineIsolationChecks(page, signInBrowser, debugPort);
}

(async () => {
    try {
        await setup();
        await serverChecks();
        await legacySessionChecks();
        await loginRateChecks();
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
