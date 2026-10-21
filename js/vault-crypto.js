// Shared browser cryptography. Keep salt encoding and the encrypted entry format stable.
async function deriveKey(password, saltHex) {
    const enc = new TextEncoder();
    const keyMaterial = await window.crypto.subtle.importKey(
        "raw", enc.encode(password), { name: "PBKDF2" }, false, ["deriveBits", "deriveKey"]
    );
    const saltBytes = enc.encode(saltHex);
    return window.crypto.subtle.deriveKey(
        { name: "PBKDF2", salt: saltBytes, iterations: 100000, hash: "SHA-256" },
        keyMaterial, { name: "AES-GCM", length: 256 }, true, ["encrypt", "decrypt"]
    );
}

function bufToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    return window.btoa(binary);
}

function base64ToBuf(base64) {
    const binary = window.atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes.buffer;
}

async function encryptPasswordLocal(password, key) {
    const enc = new TextEncoder();
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    const encrypted = await window.crypto.subtle.encrypt(
        { name: "AES-GCM", iv: iv }, key, enc.encode(password)
    );
    const result = new Uint8Array(iv.length + encrypted.byteLength);
    result.set(iv, 0);
    result.set(new Uint8Array(encrypted), iv.length);
    return bufToBase64(result);
}

async function decryptPasswordLocal(encryptedBase64, key) {
    try {
        const data = base64ToBuf(encryptedBase64);
        const dataArray = new Uint8Array(data);
        if (dataArray.length < 28) throw new Error("Data too short");
        const iv = dataArray.slice(0, 12);
        const ciphertext = dataArray.slice(12);
        const decrypted = await window.crypto.subtle.decrypt(
            { name: "AES-GCM", iv: iv }, key, ciphertext
        );
        const dec = new TextDecoder();
        return dec.decode(decrypted);
    } catch (e) {
        return null;
    }
}

const VAULT_KEY_CHECK_STORAGE = 'securevault-key-check';

async function rememberVaultKey(password, salt, context, isCurrent = () => true) {
    if (!salt || typeof context !== 'string' || !/^[a-f0-9]{32}$/.test(context)) {
        throw new Error('Reload the sign-in page and try again.');
    }
    const key = await deriveKey(password, salt);
    const encrypted = await encryptPasswordLocal('SecureVault key check:' + context, key);
    if (!isCurrent()) throw new Error('Your sign-in changed');
    // Only ciphertext and public metadata persist, never the password or derived key.
    localStorage.setItem(VAULT_KEY_CHECK_STORAGE, JSON.stringify({ context, salt, encrypted }));
}

async function getVerifiedVaultKey(password, salt, context) {
    let encoded;
    let check;
    try {
        encoded = localStorage.getItem(VAULT_KEY_CHECK_STORAGE);
        check = JSON.parse(encoded);
    } catch {}
    if (!context || !check || check.context !== context || check.salt !== salt || typeof check.encrypted !== 'string') {
        throw new Error('Sign out, then sign in online again before saving or deleting entries.');
    }
    const key = await deriveKey(password, salt);
    if (await decryptPasswordLocal(check.encrypted, key) !== 'SecureVault key check:' + context) {
        throw new Error('Incorrect master key. Nothing was changed. If your key is correct, sign out and sign in again.');
    }
    // Another tab may have signed in or signed out while the key was being derived.
    if (localStorage.getItem(VAULT_KEY_CHECK_STORAGE) !== encoded) {
        throw new Error('Your sign-in changed. Sign in online again before making changes.');
    }
    return key;
}

function clearVaultKeyCheck() {
    try { localStorage.removeItem(VAULT_KEY_CHECK_STORAGE); } catch {}
}
