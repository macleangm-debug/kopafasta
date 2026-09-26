@php
    $accountTheme = app(\App\Services\AccountThemeService::class)->resolved(auth()->user());
@endphp
<meta name="kf-theme-url" content="{{ route('site.account.theme') }}">
<style>
    html.kf-account-shell { background: #faf8f5; }
    html.kf-account-shell[data-theme="dark"],
    html.kf-account-shell[data-theme="dark"] body { background: #0c1110 !important; color-scheme: dark; }
</style>
<script>
    (function () {
        var key = 'kf-account-theme';
        var theme = null;
        try { theme = localStorage.getItem(key); } catch (e) {}
        if (theme !== 'dark' && theme !== 'light') {
            theme = @json($accountTheme);
        }
        if (theme !== 'dark' && theme !== 'light') {
            theme = 'light';
        }
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.colorScheme = theme;
        document.documentElement.style.backgroundColor = theme === 'dark' ? '#0c1110' : '#faf8f5';
    })();
</script>
