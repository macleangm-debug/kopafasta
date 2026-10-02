{{-- Support Customers tabs: Members · Guests · Partners (reuses existing CRM routes) --}}
@php
    $supportShellActive = app(\App\Services\Support\CustomerSupportWorkspaceService::class)->inSupportShell(auth()->user());
    $contactsTab = match (true) {
        request()->routeIs('admin.customers.guests.*') => 'guests',
        request()->routeIs('admin.partners.*') => 'partners',
        request()->routeIs('admin.customers.*', 'admin.support.members') => 'members',
        default => null,
    };
@endphp
@if ($supportShellActive && $contactsTab)
    <div class="mb-5 -mt-1">
        <div class="inline-flex flex-wrap gap-1 rounded-2xl bg-white ring-1 ring-brand/15 p-1 shadow-sm">
            <a href="{{ route('admin.customers.index') }}"
               @class([
                   'rounded-xl px-3.5 py-2 text-xs font-bold transition',
                   'bg-brand text-white' => $contactsTab === 'members',
                   'text-gray-700 hover:bg-brand-muted/40' => $contactsTab !== 'members',
               ])>{{ __('admin.support.contacts.members') }}</a>
            <a href="{{ route('admin.customers.guests.index') }}"
               @class([
                   'rounded-xl px-3.5 py-2 text-xs font-bold transition',
                   'bg-brand text-white' => $contactsTab === 'guests',
                   'text-gray-700 hover:bg-brand-muted/40' => $contactsTab !== 'guests',
               ])>{{ __('admin.support.contacts.guests') }}</a>
            <a href="{{ route('admin.partners.index') }}"
               @class([
                   'rounded-xl px-3.5 py-2 text-xs font-bold transition',
                   'bg-brand text-white' => $contactsTab === 'partners',
                   'text-gray-700 hover:bg-brand-muted/40' => $contactsTab !== 'partners',
               ])>{{ __('admin.support.contacts.partners') }}</a>
        </div>
        <p class="mt-2 text-xs text-gray-500">{{ __('admin.support.contacts.hint') }}</p>
    </div>
@endif
