<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f07c59">
    <title>Login - Dianne Seafood House</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/icons/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/icons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/icons/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('assets/icons/site.webmanifest') }}">
    <link href="{{ asset('css/styles-old.css') }}" rel="stylesheet" />
    <link href="{{ asset('css/style.css') }}" rel="stylesheet" />
</head>
<body class="login-page">
<div class="login-container">
    <div class="row g-0 min-vh-100">
        <div class="col-lg-6 d-none d-lg-flex login-brand-side">
            <div class="login-brand-image" style="background-image: linear-gradient(135deg, rgba(14, 19, 30, 0.82), rgba(14, 19, 30, 0.46)), url('{{ asset('assets/img/backgrounds/login-background.jpg') }}');"></div>
            <div class="login-brand-content">
                <p class="login-kicker">Dianne Seafood House</p>
                <h1>Operations made calmer.</h1>
                <p>Track inventory, production, expenses, and restaurant activity in one focused workspace.</p>
                <div class="login-feature-row">
                    <span>Inventory</span>
                    <span>Production</span>
                    <span>Reports</span>
                </div>
            </div>
        </div>
        <div class="col-lg-6 d-flex align-items-center justify-content-center login-form-side">
            <div class="login-panel">
                <div class="login-heading">
                    <p class="login-kicker text-primary">Secure Access</p>
                    <h2>Welcome back</h2>
                    <p>Sign in with your staff account to continue.</p>
                </div>

                <div class="card login-card">
                    <div class="card-body p-4 p-sm-5">
                        @if($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif
                        <form method="POST" action="{{ route('login.attempt') }}" autocomplete="on" data-login-form>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control form-control-lg" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" placeholder="name@diannesseafood.local">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control form-control-lg" required autocomplete="current-password" placeholder="Enter your password">
                            </div>
                            <button class="btn btn-primary btn-lg w-100">Sign In</button>
                        </form>
                    </div>
                </div>
                <p class="login-support text-muted">For account access, contact your branch administrator.</p>
            </div>
        </div>
    </div>
</div>
<script>
    (() => {
        const form = document.querySelector('[data-login-form]');
        const tokenInput = form?.querySelector('input[name="_token"]');

        if (!form || !tokenInput) {
            return;
        }

        const refreshCsrfToken = async () => {
            const response = await fetch(@json(route('csrf-token')), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (typeof data.token === 'string' && data.token.length > 0) {
                tokenInput.value = data.token;
            }
        };

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                refreshCsrfToken().catch(() => {});
            }
        });

        form.addEventListener('submit', async (event) => {
            if (form.dataset.csrfRefreshed === 'true') {
                return;
            }

            event.preventDefault();
            form.dataset.csrfRefreshed = 'true';

            try {
                await refreshCsrfToken();
            } catch (error) {
                // If refresh fails, keep the normal Laravel CSRF validation path.
            }

            form.submit();
        });
    })();
</script>
</body>
</html>
