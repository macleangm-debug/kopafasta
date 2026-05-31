<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('site.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['login']);
        $user = User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()
                ->withErrors(['login' => 'Those credentials do not match. Please try again.'])
                ->onlyInput('login');
        }

        Auth::login($user, $request->boolean('remember'));

        // Public site login is for borrowers, vendors and investors.
        // Admin and staff roles must use the admin console login.
        if (! in_array($user->role, ['borrower', 'vendor', 'investor'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Staff accounts must sign in from the admin console.']);
        }

        $request->session()->regenerate();

        return $this->redirectAfterLogin($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('site.home');
    }

    public function showRegisterBorrower(): View
    {
        return view('site.auth.register-borrower');
    }

    public function registerBorrower(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'country'    => ['required', 'string', 'in:TZ,KE,UG'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name'  => ['required', 'string', 'max:60'],
            'email'      => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone'      => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $data['email'] ?? null;
        if (empty($email)) {
            $digits = preg_replace('/\D/', '', $data['phone']) ?: Str::random(8);
            $email = $digits.'@phone.kopafasta.local';
        }

        $user = DB::transaction(function () use ($data, $email) {
            $user = User::create([
                'name'      => $data['first_name'].' '.$data['last_name'],
                'email'     => $email,
                'phone'     => $data['phone'],
                'password'  => Hash::make($data['password']),
                'role'      => 'borrower',
                'is_active' => true,
            ]);

            Customer::create([
                'user_id'         => $user->id,
                'customer_number' => 'C-'.strtoupper(Str::random(6)),
                'type'            => 'individual',
                'status'          => 'active',
                'first_name'      => $data['first_name'],
                'last_name'       => $data['last_name'],
                'email'           => $data['email'] ?? null,
                'phone'           => $data['phone'],
                'onboarded_at'    => now(),
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('site.membership.renew')
            ->with('status', 'Welcome! Pay your registration fee to unlock loans and services.');
    }

    public function storeWaitlistRequest(Request $request): RedirectResponse
    {
        $countryLabels = [
            'TZ' => 'Tanzania',
            'KE' => 'Kenya',
            'UG' => 'Uganda',
            'RW' => 'Rwanda',
            'BI' => 'Burundi',
            'SS' => 'South Sudan',
        ];

        $data = $request->validate([
            'country' => ['required', 'string', 'in:TZ,KE,UG,RW,BI,SS'],
            'email'   => ['required', 'email'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'step'    => ['nullable', 'integer'],
        ]);

        \App\Models\CountryWaitlistRequest::updateOrCreate([
            'country_code' => $data['country'],
            'email'        => strtolower($data['email']),
        ], [
            'phone' => $data['phone'] ?? null,
        ]);

        return back()
            ->with('waitlist_status', 'Thanks! We will notify you when Kopafasta launches in '.$countryLabels[$data['country']].'.')
            ->withInput([
                'country' => $data['country'],
                'step' => 1,
                'waitlist_email' => $data['email'],
                'waitlist_phone' => $data['phone'] ?? '',
            ]);
    }

    public function showRegisterVendor(): View
    {
        return view('site.auth.register-vendor');
    }

    public function registerVendor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'category'   => ['required', 'string', 'max:60'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'phone'      => ['required', 'string', 'max:20'],
            'address'    => ['nullable', 'string', 'max:255'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'phone'     => $data['phone'],
                'password'  => Hash::make($data['password']),
                'role'      => 'vendor',
                'is_active' => true,
            ]);

            Vendor::create([
                'user_id'       => $user->id,
                'vendor_number' => 'V-'.strtoupper(Str::random(6)),
                'name'          => $data['name'],
                'category'      => $data['category'],
                'phone'         => $data['phone'],
                'email'         => $data['email'],
                'address'       => $data['address'] ?? null,
                'status'        => 'pending',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('site.vendor.dashboard');
    }

    public function redirectAfterLogin($user): RedirectResponse
    {
        return match ($user->role) {
            'borrower' => redirect()->route('site.borrower.dashboard'),
            'vendor'   => redirect()->route('site.vendor.dashboard'),
            'investor' => redirect()->route('site.investor.dashboard'),
            default    => redirect()->route('admin.dashboard'),
        };
    }

    /**
     * Branded "no, you want the admin console" redirector,
     * in case anyone hits /login looking for staff access.
     */
    public function staffHint(): RedirectResponse
    {
        return redirect()->route('admin.login');
    }

    public function showRegisterInvestor(): View
    {
        return view('site.auth.register-investor');
    }

    public function registerInvestor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'type'           => ['required', 'in:individual,institution,fund'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'phone'          => ['required', 'string', 'max:20'],
            'address'        => ['nullable', 'string', 'max:255'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'phone'     => $data['phone'],
                'password'  => Hash::make($data['password']),
                'role'      => 'investor',
                'is_active' => true,
            ]);

            Lender::create([
                'user_id'           => $user->id,
                'code'              => 'INV-'.strtoupper(Str::random(6)),
                'name'              => $data['name'],
                'type'              => $data['type'],
                'contact_person'    => $data['name'],
                'email'             => $data['email'],
                'phone'             => $data['phone'],
                'address'           => $data['address'] ?? null,
                'credit_limit'      => 0,
                'available_balance' => 0,
                'risk_preference'   => 'medium',
                'status'            => 'active',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('site.investor.dashboard');
    }

    public function showRegisterCapital(): View
    {
        return view('site.auth.register-capital');
    }

    public function registerCapital(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization'    => ['required', 'string', 'max:160'],
            'org_type'        => ['required', 'in:bank,mfi,dfi,family_office,asset_manager,other'],
            'contact_name'    => ['required', 'string', 'max:120'],
            'contact_role'    => ['nullable', 'string', 'max:80'],
            'email'           => ['required', 'email', 'unique:users,email'],
            'phone'           => ['required', 'string', 'max:20'],
            'country'         => ['required', 'string', 'max:60'],
            'commitment_band' => ['required', 'in:50k_250k,250k_1m,1m_5m,5m_plus'],
            'address'         => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'      => $data['contact_name'],
                'email'     => $data['email'],
                'phone'     => $data['phone'],
                'password'  => Hash::make($data['password']),
                'role'      => 'investor',
                'is_active' => true,
            ]);

            $lenderAttrs = [
                'user_id'           => $user->id,
                'code'              => 'CAP-'.strtoupper(Str::random(6)),
                'name'              => $data['organization'],
                'type'              => 'institution',
                'contact_person'    => $data['contact_name'],
                'email'             => $data['email'],
                'phone'             => $data['phone'],
                'address'           => $data['address'] ?? null,
                'credit_limit'      => 0,
                'available_balance' => 0,
                'risk_preference'   => 'medium',
                'status'            => 'pending',
            ];

            // Only set metadata if the column exists.
            if (\Schema::hasColumn('lenders', 'metadata')) {
                $lenderAttrs['metadata'] = [
                    'org_type'        => $data['org_type'],
                    'contact_role'    => $data['contact_role'] ?? null,
                    'country'         => $data['country'],
                    'commitment_band' => $data['commitment_band'],
                    'notes'           => $data['notes'] ?? null,
                    'channel'         => 'capital_partner_signup',
                ];
            }

            Lender::create($lenderAttrs);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('site.investor.dashboard')
            ->with('status', 'Your capital partner application has been received. A relationship manager will be in touch within 24 hours.');
    }
}
