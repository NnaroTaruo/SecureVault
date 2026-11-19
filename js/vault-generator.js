// ==============================================================================
// SecureVault — Cryptographic Password Generator
// ==============================================================================

let currentGeneratedPassword = '';

/**
 * Unbiased cryptographic random integer in [0, max) using rejection sampling.
 */
function getSecureRandomInt(max) {
    const limit = Math.floor(0x100000000 / max) * max;
    const array = new Uint32Array(1);
    let value;
    do {
        window.crypto.getRandomValues(array);
        value = array[0];
    } while (value >= limit);
    return value % max;
}

function generateSecureRandomPassword(length = 18, options = {}) {
    const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lowercase = 'abcdefghijklmnopqrstuvwxyz';
    const numbers   = '0123456789';
    const symbols   = '!@#$%^&*()_+-=[]{}|;:,.<>?';

    const useUpper   = options.upper !== false;
    const useNumbers = options.numbers !== false;
    const useSymbols = options.symbols !== false;

    let charPool = lowercase;
    const guaranteedChars = [lowercase[getSecureRandomInt(lowercase.length)]];

    if (useUpper) {
        charPool += uppercase;
        guaranteedChars.push(uppercase[getSecureRandomInt(uppercase.length)]);
    }
    if (useNumbers) {
        charPool += numbers;
        guaranteedChars.push(numbers[getSecureRandomInt(numbers.length)]);
    }
    if (useSymbols) {
        charPool += symbols;
        guaranteedChars.push(symbols[getSecureRandomInt(symbols.length)]);
    }

    const passwordArr = [...guaranteedChars];
    const remainingLength = Math.max(0, length - guaranteedChars.length);

    for (let i = 0; i < remainingLength; i++) {
        passwordArr.push(charPool[getSecureRandomInt(charPool.length)]);
    }

    // Cryptographic Fisher-Yates shuffle
    for (let i = passwordArr.length - 1; i > 0; i--) {
        const j = getSecureRandomInt(i + 1);
        const temp = passwordArr[i];
        passwordArr[i] = passwordArr[j];
        passwordArr[j] = temp;
    }

    return passwordArr.join('');
}

function togglePasswordGenerator() {
    const panel = document.getElementById('passwordGeneratorPanel');
    const toggleBtn = document.getElementById('toggleGenBtn');
    if (!panel) return;

    const isHidden = panel.classList.contains('hidden');
    if (isHidden) {
        panel.classList.remove('hidden');
        if (toggleBtn) {
            toggleBtn.classList.add('bg-primary', 'text-dark-bg');
            toggleBtn.classList.remove('text-primary', 'bg-primary/10');
        }
        if (!currentGeneratedPassword) generateNewPassword();
    } else {
        panel.classList.add('hidden');
        if (toggleBtn) {
            toggleBtn.classList.remove('bg-primary', 'text-dark-bg');
            toggleBtn.classList.add('text-primary', 'bg-primary/10');
        }
    }
}

function generateNewPassword() {
    const lengthInput   = document.getElementById('genLength');
    const upperInput    = document.getElementById('genUpper');
    const numbersInput  = document.getElementById('genNumbers');
    const symbolsInput  = document.getElementById('genSymbols');
    const displayEl     = document.getElementById('generatedPasswordDisplay');
    const strengthBadge = document.getElementById('genStrengthBadge');

    const length = lengthInput ? parseInt(lengthInput.value, 10) : 18;
    const options = {
        upper: upperInput ? upperInput.checked : true,
        numbers: numbersInput ? numbersInput.checked : true,
        symbols: symbolsInput ? symbolsInput.checked : true
    };

    currentGeneratedPassword = generateSecureRandomPassword(length, options);
    if (displayEl) displayEl.textContent = currentGeneratedPassword;

    if (strengthBadge) {
        let score = 0;
        if (length >= 12) score++;
        if (length >= 18) score++;
        if (options.upper) score++;
        if (options.numbers) score++;
        if (options.symbols) score++;

        if (score >= 4) {
            strengthBadge.textContent = 'Very Strong';
            strengthBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
        } else if (score >= 3) {
            strengthBadge.textContent = 'Strong';
            strengthBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-400 border border-blue-500/30';
        } else {
            strengthBadge.textContent = 'Moderate';
            strengthBadge.className = 'text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30';
        }
    }
}

function applyGeneratedPassword() {
    if (!currentGeneratedPassword) generateNewPassword();
    const pwInput = document.getElementById('accountPassword');
    if (!pwInput) return;

    pwInput.value = currentGeneratedPassword;
    pwInput.classList.add('border-primary', 'ring-2', 'ring-primary/40');
    setTimeout(() => pwInput.classList.remove('ring-2', 'ring-primary/40'), 1200);
    showAlert('Generated password applied to field!', 'success');
}

function copyGeneratedPassword() {
    if (!currentGeneratedPassword) generateNewPassword();
    if (!currentGeneratedPassword) return;

    if (!navigator.clipboard) {
        const ta = document.createElement('textarea');
        ta.value = currentGeneratedPassword;
        ta.style.position = 'fixed';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showAlert('Generated password copied to clipboard!', 'success');
        } catch {
            showAlert('Copy failed', 'error');
        }
        document.body.removeChild(ta);
        return;
    }
    navigator.clipboard.writeText(currentGeneratedPassword)
        .then(() => showAlert('Generated password copied to clipboard!', 'success'))
        .catch(() => showAlert('Copy failed', 'error'));
}
