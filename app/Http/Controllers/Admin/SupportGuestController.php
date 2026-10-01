<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportGuest;
use App\Models\SupportTicket;
use App\Services\Support\SupportGuestService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportGuestController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $guests = SupportGuest::query()
            ->where('registration_status', 'guest')
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D+/', '', $q) ?: '';
                $query->where(function ($inner) use ($q, $digits) {
                    $inner->where('first_name', 'like', '%'.$q.'%')
                        ->orWhere('last_name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%');
                    if ($digits !== '') {
                        $inner->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderByDesc('last_contact_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.customers.guests-index', [
            'guests' => $guests,
            'q' => $q,
        ]);
    }

    public function show(SupportGuest $guest): View
    {
        $guest->load(['customer', 'user']);

        $conversations = $guest->conversations()
            ->where(function ($q) {
                $q->whereNull('channel')
                    ->orWhereNotIn('channel', ['phone', 'walk_in', 'other']);
            })
            ->with(['messages' => fn ($q) => $q->latest('id')->limit(3)])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $interactions = $guest->conversations()
            ->whereIn('channel', ['phone', 'walk_in', 'other'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $tickets = SupportTicket::query()
            ->where(function ($q) use ($guest) {
                $q->where('guest_phone', $guest->phone)
                    ->orWhere('guest_phone', 'like', '%'.substr($guest->phone, -9));
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('admin.customers.guests-show', [
            'guest' => $guest,
            'conversations' => $conversations,
            'interactions' => $interactions,
            'tickets' => $tickets,
        ]);
    }

    public function create(Request $request, SupportGuestService $guests): View|RedirectResponse
    {
        $phone = trim((string) $request->query('phone', ''));
        if ($phone !== '') {
            $identity = $guests->resolvePhoneIdentity($phone);
            if (($identity['kind'] ?? null) === 'member') {
                return redirect()->route('admin.customers.show', $identity['customer'])
                    ->with('status', 'This phone belongs to an existing Member.');
            }
            if (($identity['kind'] ?? null) === 'partner') {
                return redirect()->route('admin.vendors.show', $identity['vendor'])
                    ->with('status', 'This phone belongs to an existing Partner.');
            }
            if (($identity['kind'] ?? null) === 'guest') {
                return redirect()->route('admin.customers.guests.show', $identity['guest']);
            }
        }

        return view('admin.customers.guests-create', [
            'phone' => $phone,
        ]);
    }

    public function store(Request $request, SupportGuestService $guests): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'guest_phone' => ['required', 'string', 'max:32'],
        ]);

        $phone = PhoneNumber::fromRequest($request, 'guest_phone', (string) session('country', 'TZ'))
            ?: PhoneNumber::normalizeForCountry($data['guest_phone'], (string) session('country', 'TZ'));

        if (! $phone) {
            return back()->withInput()->with('error', 'Enter a valid phone number.');
        }

        $identity = $guests->resolvePhoneIdentity($phone);
        if (($identity['kind'] ?? null) === 'member') {
            return redirect()->route('admin.customers.show', $identity['customer'])
                ->with('status', 'This phone belongs to an existing Member.');
        }
        if (($identity['kind'] ?? null) === 'partner') {
            return redirect()->route('admin.vendors.show', $identity['vendor'])
                ->with('status', 'This phone belongs to an existing Partner.');
        }

        $guest = $guests->touchGuest(
            $data['first_name'],
            $data['last_name'],
            $phone,
            SupportGuestService::SOURCE_OTHER,
        );

        if (! $guest) {
            return back()->withInput()->with('error', 'Could not save Guest contact.');
        }

        return redirect()
            ->route('admin.customers.guests.show', $guest)
            ->with('status', str_starts_with(app()->getLocale(), 'sw')
                ? 'Mgeni amehifadhiwa. Hakuna mazungumzo, mwingiliano wala tiketi iliyoundwa.'
                : 'Guest saved. No conversation, interaction or ticket was created.');
    }

    public function edit(SupportGuest $guest): View
    {
        abort_unless($guest->isActiveGuest(), 404);

        return view('admin.customers.guests-edit', [
            'guest' => $guest,
        ]);
    }

    public function update(Request $request, SupportGuest $guest, SupportGuestService $guests): RedirectResponse
    {
        abort_unless($guest->isActiveGuest(), 404);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'guest_phone' => ['required', 'string', 'max:32'],
        ]);

        $phone = PhoneNumber::fromRequest($request, 'guest_phone', (string) session('country', 'TZ'))
            ?: PhoneNumber::normalizeForCountry($data['guest_phone'], (string) session('country', 'TZ'));

        if (! $phone) {
            return back()->withInput()->with('error', 'Enter a valid phone number.');
        }

        if ($phone !== $guest->phone) {
            $identity = $guests->resolvePhoneIdentity($phone);
            if ($identity && (($identity['guest']->id ?? null) !== $guest->id)) {
                return back()->withInput()->with('error', 'That phone already belongs to another contact.');
            }
        }

        $guest->update([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => $phone,
        ]);

        return redirect()
            ->route('admin.customers.guests.show', $guest)
            ->with('status', str_starts_with(app()->getLocale(), 'sw') ? 'Mgeni amesasishwa.' : 'Guest updated.');
    }
}
