<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale'   => ['required', 'in:en,sw'],
            'redirect' => ['nullable', 'string', 'max:2048'],
            'scope'    => ['nullable', 'in:site,admin'],
        ]);

        $target = $this->safeRedirectTarget($request, $data['redirect'] ?? null);
        $path = is_string($target) ? (parse_url($target, PHP_URL_PATH) ?: '') : '';
        $adminScope = ($data['scope'] ?? '') === 'admin'
            || str_starts_with($path, '/admin')
            || str_starts_with($path, '/staff');

        if ($adminScope) {
            $request->session()->put('admin_locale', $data['locale']);
        } else {
            $request->session()->put('locale', $data['locale']);
        }

        $user = $request->user('admin') ?? $request->user();
        if ($user) {
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $prefs[$adminScope ? 'admin_locale' : 'preferred_locale'] = $data['locale'];
            $user->preferences = $prefs;
            $user->save();
        }

        return $target ? redirect()->to($target) : back();
    }

    private function safeRedirectTarget(Request $request, ?string $redirect): ?string
    {
        if (blank($redirect)) {
            return null;
        }

        if (str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return $redirect;
        }

        $parts = parse_url($redirect);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        if (strcasecmp((string) $parts['host'], (string) $request->getHost()) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }
}
