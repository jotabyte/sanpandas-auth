<?php
$pageTitle = 'Auth | San Pandas';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="https://sanpandas.com/media/identity/icon.png
">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-page {
            height: 100vh;
            width: 100vw;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bg-main) 0%, rgba(30, 58, 138, 0.1) 100%);
            overflow: hidden;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            padding: 50px;
        }

        .role-selector {
            display: flex;
            background: rgba(0, 0, 0, 0.05);
            border-radius: var(--radius-pill);
            padding: 5px;
            margin-bottom: 30px;
        }

        .role-btn {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: var(--radius-pill);
            cursor: pointer;
            font-weight: 600;
            color: var(--text-muted);
            transition: var(--transition);
            border: none;
            background: transparent;
        }

        .role-btn.active {
            background: white;
            color: var(--primary-color);
            box-shadow: var(--shadow-soft);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            font-family: inherit;
            background: rgba(255, 255, 255, 0.8);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }
    </style>
</head>

<body>

    <div class="login-page">
        <div class="login-card glass-panel" style="backdrop-filter: blur(20px);">
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="https://sanpandas.com/media/identity/logo-nbg.webp" alt="San Pandas"
                    style="width: 80px; height: auto; display: block; margin: 0 auto 12px;">
                <a href="http://academy.sanpandas.com/" class="logo-text" style="font-size: 2rem;">San
                    <span>Pandas</span></a>
                <p class="text-muted" style="margin-top: 10px;">Enter your registered email to receive a secure login
                    code.</p>
            </div>

            <form id="magicLoginForm">
                <div class="form-group" id="emailGroup">
                    <label>Email Address</label>
                    <input type="email" id="emailInput" class="form-control" placeholder="student@example.com" required>
                </div>

                <div class="form-group" id="codeGroup" style="display: none;">
                    <label>4-Digit Security Code</label>
                    <input type="text" id="codeInput" class="form-control" placeholder="0000" maxlength="4"
                        pattern="\d{4}" inputmode="numeric" autocomplete="one-time-code"
                        style="text-align: center; letter-spacing: 4px; font-size: 1.2rem; font-weight: bold;">
                    <p class="text-muted" style="margin-top: 5px; font-size: 0.8rem; text-align: center;">
                        We've sent a code to <span id="sentToEmail" style="font-weight:600"></span>.
                        Didn't receive it?
                        <a href="#" id="resendLink" style="color: var(--primary-color); font-weight:600; text-decoration:none;">Resend code</a>
                    </p>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary" style="width: 100%;">Get Code</button>
            </form>

            <div id="statusMessage" style="margin-top: 20px; text-align: center; font-size: 0.95rem;"></div>

            <div style="text-align: center; margin-top: 20px; font-size: 0.9rem;">
                <a href="http://academy.sanpandas.com/" style="color: var(--text-muted);"><span
                        style="margin-right:5px;">←</span> Back to Website</a>
            </div>
        </div>
    </div>

    <script>
        const PENDING_EMAIL_KEY = 'sanpandas_pending_login_email';
        const RESEND_COOLDOWN_SEC = 20;

        const emailInput  = document.getElementById('emailInput');
        const codeInput   = document.getElementById('codeInput');
        const emailGroup  = document.getElementById('emailGroup');
        const codeGroup   = document.getElementById('codeGroup');
        const btn         = document.getElementById('submitBtn');
        const status      = document.getElementById('statusMessage');
        const resendLink  = document.getElementById('resendLink');
        const sentToEmail = document.getElementById('sentToEmail');

        let currentStep = 1;

        // ── Step management ──────────────────────────────────────────────────
        function enterCodeStep(email) {
            currentStep = 2;
            emailGroup.style.display = 'none';
            codeGroup.style.display  = 'block';
            codeInput.required = true;
            codeInput.value = '';                       // always start empty on refresh
            btn.textContent = 'Verify Code';
            sentToEmail.textContent = email;
            sessionStorage.setItem(PENDING_EMAIL_KEY, email);
            setTimeout(() => codeInput.focus(), 30);
        }

        function exitCodeStep() {
            currentStep = 1;
            emailGroup.style.display = 'block';
            codeGroup.style.display  = 'none';
            codeInput.required = false;
            codeInput.value = '';
            btn.textContent = 'Get Code';
            sessionStorage.removeItem(PENDING_EMAIL_KEY);
        }

        // ── On page load: restore Step 2 if a request was already made ───────
        // A refresh in the middle of the flow just clears the code input, not
        // the whole step — the user doesn't have to re-enter their email.
        (function restoreState() {
            const saved = sessionStorage.getItem(PENDING_EMAIL_KEY);
            if (saved) {
                emailInput.value = saved;
                enterCodeStep(saved);
            }
        })();

        // ── Request-code helper (used by initial submit AND resend) ──────────
        async function requestCode(email) {
            const fd = new FormData();
            fd.append('action', 'request_magic_link');
            fd.append('email', email);
            const res  = await fetch('api/auth.php', { method: 'POST', body: fd });
            return res.json();
        }

        // ── Resend flow ──────────────────────────────────────────────────────
        resendLink.addEventListener('click', async (e) => {
            e.preventDefault();
            if (resendLink.dataset.disabled === '1') return;

            const email = sessionStorage.getItem(PENDING_EMAIL_KEY) || emailInput.value.trim();
            if (!email) return;

            resendLink.dataset.disabled = '1';
            resendLink.style.opacity = '0.6';
            resendLink.style.pointerEvents = 'none';
            resendLink.textContent = 'Sending…';
            status.innerHTML = '';

            try {
                const data = await requestCode(email);
                if (data.success) {
                    status.style.color = '#25D366';
                    status.textContent = 'Code resent — check your inbox.';
                    startResendCooldown();
                } else {
                    status.style.color = '#D32F2F';
                    status.textContent = data.error || 'Could not resend code.';
                    resetResendLink();
                }
            } catch (err) {
                status.style.color = '#D32F2F';
                status.textContent = 'Network error resending code.';
                resetResendLink();
            }
        });

        function startResendCooldown() {
            let left = RESEND_COOLDOWN_SEC;
            resendLink.textContent = `Resend code (${left}s)`;
            const tick = setInterval(() => {
                left -= 1;
                if (left <= 0) {
                    clearInterval(tick);
                    resetResendLink();
                } else {
                    resendLink.textContent = `Resend code (${left}s)`;
                }
            }, 1000);
        }

        function resetResendLink() {
            resendLink.dataset.disabled = '';
            resendLink.style.opacity = '';
            resendLink.style.pointerEvents = '';
            resendLink.textContent = 'Resend code';
        }

        // ── Main form submit (Get Code → Verify Code) ────────────────────────
        document.getElementById('magicLoginForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const email = emailInput.value.trim();
            const code  = codeInput.value.trim();

            status.innerHTML = '';

            if (currentStep === 1) {
                btn.disabled = true;
                btn.textContent = 'Sending...';

                requestCode(email)
                    .then(data => {
                        btn.disabled = false;
                        if (data.success) {
                            status.style.color = '#25D366';
                            status.textContent = data.message;
                            enterCodeStep(email);
                        } else {
                            status.style.color = '#D32F2F';
                            status.textContent = data.error || 'An error occurred';
                            btn.textContent = 'Get Code';
                        }
                    })
                    .catch(() => {
                        btn.disabled = false;
                        btn.textContent = 'Get Code';
                        status.style.color = '#D32F2F';
                        status.textContent = 'Network error requesting code.';
                    });

            } else {
                btn.disabled = true;
                btn.textContent = 'Verifying...';

                const fd = new FormData();
                fd.append('action', 'verify_code');
                fd.append('email', email);
                fd.append('code', code);

                fetch('api/auth.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            sessionStorage.removeItem(PENDING_EMAIL_KEY);
                            status.style.color = '#25D366';
                            status.innerHTML = '<strong>Success! Redirecting to dashboard...</strong>';
                            setTimeout(() => {
                                window.location.href = data.redirect || 'https://academy.sanpandas.com/dashboard.php';
                            }, 800);
                        } else {
                            btn.disabled = false;
                            btn.textContent = 'Verify Code';
                            status.style.color = '#D32F2F';
                            status.textContent = data.error || 'Invalid code.';
                        }
                    })
                    .catch(() => {
                        btn.disabled = false;
                        btn.textContent = 'Verify Code';
                        status.style.color = '#D32F2F';
                        status.textContent = 'Network error verifying code.';
                    });
            }
        });
    </script>

</body>

</html>