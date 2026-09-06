<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LendingPolicyVersion;
use App\Models\Setting;
use App\Services\Governance\LendingPolicyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GovernancePolicyController extends Controller
{
    public function index(LendingPolicyResolver $resolver): View
    {
        $resolved = $resolver->resolve();
        $versions = LendingPolicyVersion::query()->orderByDesc('id')->limit(20)->get();
        $register = $this->documentRegister();

        return view('admin.settings.governance.index', compact('resolved', 'versions', 'register'));
    }

    public function lendingPolicy(LendingPolicyResolver $resolver): View
    {
        $resolved = $resolver->resolve();
        $current = $resolver->currentApproved();
        $versions = LendingPolicyVersion::query()->orderByDesc('id')->limit(30)->get();

        return view('admin.settings.governance.lending-policy', compact('resolved', 'current', 'versions'));
    }

    public function approveLendingPolicy(Request $request, LendingPolicyResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'approved_by' => ['nullable', 'string', 'max:120'],
        ]);

        $version = $resolver->approveSnapshot($data['approved_by'] ?? null);

        return back()->with('status', 'Lending Policy snapshot approved as '.$version->version);
    }

    public function saveSocial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'social' => ['nullable', 'array'],
            'social.*.platform' => ['required', 'string', 'max:40'],
            'social.*.url' => ['nullable', 'url', 'max:255'],
            'social.*.enabled' => ['nullable', 'boolean'],
            'social.*.sort' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $rows = collect($data['social'] ?? [])
            ->map(fn (array $row, int $i) => [
                'platform' => strtolower((string) $row['platform']),
                'url' => (string) ($row['url'] ?? ''),
                'enabled' => (bool) ($row['enabled'] ?? false),
                'sort' => (int) ($row['sort'] ?? $i),
            ])
            ->sortBy('sort')
            ->values()
            ->all();

        Setting::set('company.social_links', $rows);

        return back()->with('status', 'Social links saved.');
    }

    /** @return list<array{key: string, label: string, audience: string, status: string, route: string|null}> */
    private function documentRegister(): array
    {
        return [
            ['key' => 'terms', 'label' => 'Terms of Use', 'audience' => 'public', 'status' => 'available', 'route' => 'site.legal.terms'],
            ['key' => 'privacy', 'label' => 'Privacy Policy', 'audience' => 'public', 'status' => 'available', 'route' => 'site.legal.privacy'],
            ['key' => 'responsible_lending', 'label' => 'Responsible Lending', 'audience' => 'public', 'status' => 'available', 'route' => 'site.responsible-lending'],
            ['key' => 'complaints', 'label' => 'Complaints Procedure', 'audience' => 'public', 'status' => 'available', 'route' => 'site.legal.complaints'],
            ['key' => 'lending_policy', 'label' => 'Lending Policy', 'audience' => 'internal', 'status' => Setting::get('governance.lending_policy_status') ?: 'draft', 'route' => 'admin.settings.governance.lending-policy'],
            ['key' => 'aml', 'label' => 'AML / KYC procedures', 'audience' => 'internal', 'status' => 'needs_review', 'route' => 'admin.settings.aml'],
            ['key' => 'consumer_protection', 'label' => 'Consumer Protection Policy', 'audience' => 'internal', 'status' => 'draft', 'route' => null],
            ['key' => 'recovery', 'label' => 'Recovery / Collections Policy', 'audience' => 'internal', 'status' => 'needs_review', 'route' => null],
            ['key' => 'data_protection', 'label' => 'Data Protection governance', 'audience' => 'internal', 'status' => 'needs_review', 'route' => 'admin.settings.legal'],
            ['key' => 'risk', 'label' => 'Risk Management Policy', 'audience' => 'internal', 'status' => 'draft', 'route' => null],
        ];
    }
}
