<x-admin.layout title="Governance & Policies">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Governance & Policies</h1>
            <p class="mt-1 text-sm text-gray-600">Controlled public disclosures and internal/regulatory documents. Settings remain source of truth.</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <h2 class="font-bold text-gray-900">Public</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($register as $row)
                        @continue($row['audience'] !== 'public')
                        <li class="flex items-center justify-between gap-3">
                            <span>{{ $row['label'] }}</span>
                            <span class="text-xs uppercase tracking-wide text-gray-500">{{ $row['status'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <h2 class="font-bold text-gray-900">Internal / Regulatory</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($register as $row)
                        @continue($row['audience'] !== 'internal')
                        <li class="flex items-center justify-between gap-3">
                            <span>{{ $row['label'] }}</span>
                            <span class="text-xs uppercase tracking-wide text-gray-500">{{ $row['status'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.settings.governance.lending-policy') }}" class="mt-4 inline-flex text-sm font-semibold text-brand hover:underline">Open Lending Policy →</a>
            </div>
        </div>

        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
            <h2 class="font-bold text-gray-900">Social links</h2>
            <p class="mt-1 text-sm text-gray-600">Only enabled platforms with a valid URL appear in the public footer.</p>
            @php
                $social = \App\Models\Setting::get('company.social_links') ?: [
                    ['platform' => 'instagram', 'url' => 'https://www.instagram.com/kopafasta/?hl=en', 'enabled' => true, 'sort' => 1],
                    ['platform' => 'facebook', 'url' => 'https://www.facebook.com/kopafasta/', 'enabled' => true, 'sort' => 2],
                    ['platform' => 'linkedin', 'url' => '', 'enabled' => false, 'sort' => 3],
                    ['platform' => 'x', 'url' => '', 'enabled' => false, 'sort' => 4],
                    ['platform' => 'tiktok', 'url' => '', 'enabled' => false, 'sort' => 5],
                    ['platform' => 'youtube', 'url' => '', 'enabled' => false, 'sort' => 6],
                ];
            @endphp
            <form method="POST" action="{{ route('admin.settings.governance.social.save') }}" class="mt-4 space-y-3">
                @csrf
                @method('PUT')
                @foreach ($social as $i => $row)
                    <div class="grid md:grid-cols-[140px_1fr_80px_70px] gap-2 items-center">
                        <input type="hidden" name="social[{{ $i }}][platform]" value="{{ $row['platform'] }}">
                        <div class="text-sm font-semibold capitalize">{{ $row['platform'] }}</div>
                        <input type="url" name="social[{{ $i }}][url]" value="{{ $row['url'] }}" placeholder="https://" class="rounded-lg border-gray-200 text-sm">
                        <label class="text-xs flex items-center gap-1"><input type="checkbox" name="social[{{ $i }}][enabled]" value="1" @checked(!empty($row['enabled']))> Enabled</label>
                        <input type="number" name="social[{{ $i }}][sort]" value="{{ $row['sort'] ?? $i }}" class="rounded-lg border-gray-200 text-sm">
                    </div>
                @endforeach
                <button class="rounded-xl bg-brand text-white font-semibold px-4 py-2.5 text-sm">Save social links</button>
            </form>
        </div>
    </div>
</x-admin.layout>
