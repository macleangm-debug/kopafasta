<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportGuest;
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
            ->with(['messages' => fn ($q) => $q->latest('id')->limit(5)])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('admin.customers.guests-show', [
            'guest' => $guest,
            'conversations' => $conversations,
        ]);
    }

    public function create(Request $request, SupportGuestService $guests): View|RedirectResponse
    {
        $phone = trim((string) $request->query('phone', ''));
        if ($phone !== '') {
            $identity = $guests->resolvePhoneIdentity($phone);
            if (($identity['kind'] ?? null) === 'member') {
                return redirect()->route('admin.support.interactions.new', [
                    'customer_id' => $identity['customer']->id,
                    'party' => 'registered',
                    'channel' => 'phone',
                ]);
            }
            if (($identity['kind'] ?? null) === 'partner') {
                return redirect()->route('admin.support.interactions.new', [
                    'partner_id' => $identity['vendor']->id,
                    'party' => 'registered',
                    'channel' => 'phone',
                ]);
            }
            if (($identity['kind'] ?? null) === 'guest') {
                return redirect()->route('admin.customers.guests.show', $identity['guest']);
            }
        }

        return view('admin.customers.guests-create', [
            'phone' => $phone,
            'channel' => (string) $request->query('channel', 'phone'),
        ]);
    }

    public function store(Request $request, SupportGuestService $guests): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'guest_phone' => ['required', 'string', 'max:32'],
            'channel' => ['required', 'in:phone,walk_in,other'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $phone = PhoneNumber::fromRequest($request, 'guest_phone', (string) session('country', 'TZ'))
            ?: PhoneNumber::normalizeForCountry($data['guest_phone'], (string) session('country', 'TZ'));

        if (! $phone) {
            return back()->withInput()->with('error', 'Enter a valid phone number.');
        }

        $identity = $guests->resolvePhoneIdentity($phone);
        if (($identity['kind'] ?? null) === 'member') {
            return redirect()->route('admin.support.interactions.new', [
                'customer_id' => $identity['customer']->id,
                'party' => 'registered',
                'channel' => $data['channel'],
            ])->with('status', 'This phone belongs to an existing Member. Record the call on their account.');
        }
        if (($identity['kind'] ?? null) === 'partner') {
            return redirect()->route('admin.support.interactions.new', [
                'partner_id' => $identity['vendor']->id,
                'party' => 'registered',
                'channel' => $data['channel'],
            ])->with('status', 'This phone belongs to an existing Partner. Record the call on their account.');
        }

        $source = match ($data['channel']) {
            'walk_in' => SupportGuestService::SOURCE_WALK_IN,
            'other' => SupportGuestService::SOURCE_OTHER,
            default => SupportGuestService::SOURCE_PHONE_CALL,
        };

        $guest = $guests->touchGuest(
            $data['first_name'],
            $data['last_name'],
            $phone,
            $source,
        );

        if (! $guest) {
            return back()->withInput()->with('error', 'Could not save Guest contact.');
        }

        // Reuse Support interaction recorder for the call note.
        return redirect()->route('admin.support.interactions.new', [
            'party' => 'non_member',
            'channel' => $data['channel'],
            'guest_first_name' => $guest->first_name,
            'guest_last_name' => $guest->last_name,
            'guest_phone' => $guest->phone,
        ])->with('status', 'Guest saved. Add the call notes below.');
    }
}
