@php
    $accountTheme = app(\App\Services\AccountThemeService::class)->resolved(auth()->user());
@endphp
<meta name="kf-theme-url" content="{{ route('site.account.theme') }}">
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
    })();
</script>
