@php
    $request = request();
    if (in_array($request->query('lang'), ['sw', 'en'], true)) {
        app()->setLocale($request->query('lang'));
    } else {
        $locale = null;
        if ($request->hasSession()) {
            $locale = $request->session()->get('locale');
        }
        $user = $request->user();
        $preferred = data_get($user?->preferences, 'preferred_locale')
            ?? data_get($user?->preferences, 'locale');
        if ((! is_string($locale) || $locale === '') && is_string($preferred) && in_array($preferred, ['en', 'sw'], true)) {
            $locale = $preferred;
        }
        if (is_string($locale) && in_array($locale, ['en', 'sw'], true)) {
            app()->setLocale($locale);
        }
    }
    $allowed = ['403', '404', '419', '429', '500', '503'];
    $raw = $code ?? (string) ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
        ? $exception->getStatusCode()
        : 500);
    $code = in_array((string) $raw, $allowed, true) ? (string) $raw : '500';
    $home = '/';
    $support = '/borrower/support';
    $sw = '?lang=sw';
    $en = '?lang=en';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('errors.'.$code.'.title') }} · Kopafasta</title>
    <link rel="icon" href="/images/brand/kopafasta-mark.png" type="image/png">
    <link rel="apple-touch-icon" href="/images/brand/kopafasta-mark.png">
    <style>
        :root { --brand:#0b3d2e; --gold:#f5c842; --paper:#faf8f5; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; font-family: ui-sans-serif, system-ui, sans-serif; background:var(--paper); color:#111; display:flex; flex-direction:column; }
        header { display:flex; justify-content:space-between; align-items:center; padding:1rem 1.25rem; padding-top:max(1rem, env(safe-area-inset-top)); }
        header img { height:28px; }
        .locale a { font-size:12px; font-weight:700; color:var(--brand); text-decoration:none; margin-left:8px; }
        main { flex:1; display:grid; place-items:center; padding:1.5rem; padding-bottom:max(1.5rem, env(safe-area-inset-bottom)); }
        .card { width:100%; max-width:32rem; background:#fff; border-radius:1.5rem; padding:2rem 1.5rem; box-shadow:0 12px 40px rgba(11,61,46,.08); border:1px solid rgba(11,61,46,.08); }
        .badge { display:inline-flex; font-size:11px; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:var(--brand); background:#e8f3ee; border-radius:999px; padding:.35rem .7rem; }
        h1 { font-size:1.6rem; line-height:1.2; margin:.85rem 0 .5rem; }
        p { color:#4b5563; margin:0 0 1.25rem; }
        .actions { display:flex; flex-direction:column; gap:.6rem; }
        a.btn { display:block; text-align:center; text-decoration:none; font-weight:700; border-radius:12px; padding:.9rem 1rem; }
        a.primary { background:var(--gold); color:var(--brand); }
        a.ghost { background:#fff; color:var(--brand); border:1px solid rgba(11,61,46,.18); }
        .note { font-size:12px; color:#6b7280; margin-top:1rem; }
        footer { text-align:center; font-size:11px; color:#9ca3af; padding:1rem; padding-bottom:max(1rem, env(safe-area-inset-bottom)); }
        @media (min-width:640px) { .actions { flex-direction:row; } a.btn { flex:1; } h1 { font-size:2rem; } }
    </style>
</head>
<body>
    <header>
        <a href="{{ $home }}"><img src="/images/brand/kopafasta-logo.png" alt="Kopafasta"></a>
        <div class="locale">
            <a href="{{ $sw }}">{{ __('errors.locale_sw') }}</a>
            <a href="{{ $en }}">{{ __('errors.locale_en') }}</a>
        </div>
    </header>
    <main>
        <div class="card">
            <span class="badge">{{ $code }}</span>
            <h1>{{ __('errors.'.$code.'.title') }}</h1>
            <p>{{ __('errors.'.$code.'.lead') }}</p>
            <div class="actions">
                <a class="btn ghost" href="#" onclick="if (history.length > 1) { history.back(); } else { location.href = '{{ $home }}'; } return false;">{{ __('errors.back') }}</a>
                <a class="btn primary" href="{{ $home }}">{{ __('errors.home') }}</a>
            </div>
            <p class="note">{{ __('errors.logged') }}</p>
        </div>
    </main>
    <footer>Kopafasta</footer>
</body>
</html>
