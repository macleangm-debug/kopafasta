<x-site.borrower-layout :title="brand_title(__('plus.home.goals'))" active="dashboard">
    <form method="post" action="{{ route('site.borrower.plus.goals.save') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-4 sm:p-6 space-y-4">
        @csrf
        <h1 class="text-xl font-semibold">{{ __('plus.goals.title') }}</h1>
        <label class="block text-sm">{{ __('plus.goals.kind') }}
            <select name="kind" class="mt-1 w-full min-h-11 rounded-xl border-gray-300">
                <option value="emergency">{{ __('plus.goals.emergency') }}</option>
                <option value="stock">{{ __('plus.goals.stock') }}</option>
                <option value="school">{{ __('plus.goals.school') }}</option>
                <option value="other">{{ __('plus.goals.other') }}</option>
            </select>
        </label>
        <label class="block text-sm">{{ __('plus.goals.name') }}
            <input name="title" required class="mt-1 w-full min-h-11 rounded-xl border-gray-300">
        </label>
        <label class="block text-sm">{{ __('plus.goals.target') }}
            <input name="target_amount" type="number" step="0.01" min="1" inputmode="decimal" class="mt-1 w-full min-h-11 rounded-xl border-gray-300">
        </label>
        <button class="w-full sm:w-auto rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.goals.save') }}</button>
    </form>
    <div class="mt-6 space-y-3">
        @foreach ($goals as $goal)
            <div class="rounded-2xl bg-white ring-1 ring-gray-100 p-4">
                <p class="font-semibold">{{ $goal->title }}</p>
                <p class="text-sm text-gray-600">{{ __('plus.goals.progress', ['saved' => format_money($goal->saved_amount), 'target' => format_money($goal->target_amount)]) }}</p>
                @if ($goal->isComplete())
                    <p class="text-sm text-emerald-700 font-medium mt-1">{{ __('plus.goals.completed') }}</p>
                @else
                    <form method="post" action="{{ route('site.borrower.plus.goals.contribute', $goal) }}" class="mt-3 flex flex-col sm:flex-row gap-2">
                        @csrf
                        <input name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" class="flex-1 min-h-11 rounded-xl border-gray-300" placeholder="{{ __('plus.goals.add') }}">
                        <button class="rounded-xl bg-brand text-white px-4 py-3 text-sm font-semibold">{{ __('plus.goals.add') }}</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-site.borrower-layout>
