@php
    $demoCreateAlpine = 'demoCreate('.json_encode([
        'personas' => $personas->map(fn ($p) => [
            'key' => $p->key,
            'name' => $p->name,
            'role' => $p->role,
        ])->values(),
        'scenarios' => collect($scenarios)->map(fn ($row, $key) => [
            'key' => $key,
            'label' => $row['label'] ?? $key,
            'roles' => $row['roles'] ?? ['borrower'],
        ])->values(),
    ]).')';
@endphp
<x-admin.create-page
    title="Create demo"
    heading="Create a marketing demo"
    subheading="Under a minute. Isolated presentation — cannot move money or write to real tables."
    :action="route('admin.growth.demos.store')"
    :cancelUrl="route('admin.growth.demos.index')"
    submitLabel="Create demo"
    :alpine="$demoCreateAlpine">

    <x-admin.step title="Who?">
        <div class="md:col-span-2 flex flex-wrap gap-2">
            @foreach (['borrower' => 'Borrower', 'plus' => 'Plus member', 'affiliate' => 'Affiliate'] as $key => $label)
                <label class="rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/40">
                    <input type="radio" name="who" value="{{ $key }}" class="mr-1 text-brand" x-model="who" @checked($key === 'borrower')>
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <p class="md:col-span-2 text-sm text-gray-600">Affiliate demos use approved templates only unless you have unrestricted demo permission.</p>
    </x-admin.step>

    <x-admin.step title="Persona">
        <label class="md:col-span-2 block text-sm text-gray-700">Persona
            <select name="persona_key" required class="mt-1 w-full rounded-xl border-gray-300" x-model="personaKey">
                <template x-for="persona in filteredPersonas" :key="persona.key">
                    <option :value="persona.key" x-text="persona.name"></option>
                </template>
            </select>
        </label>
    </x-admin.step>

    <x-admin.step title="Scenario">
        <label class="md:col-span-2 block text-sm text-gray-700">What should they see?
            <select name="scenario_key" required class="mt-1 w-full rounded-xl border-gray-300" x-model="scenarioKey">
                <template x-for="row in filteredScenarios" :key="row.key">
                    <option :value="row.key" x-text="row.label"></option>
                </template>
            </select>
        </label>
    </x-admin.step>

    <x-admin.step title="Customize">
        <x-admin.input name="display_name" label="Name" placeholder="Asha Mushi" />
        <x-admin.input name="amount" label="Amount (TZS)" :money="true" />
        <x-admin.select name="grade" label="Grade" :options="['' => 'Use persona', 'bronze' => 'Bronze', 'silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum']" />
        <x-admin.input name="trust" label="Trust" type="number" />
        @unless ($unrestricted)
            <p class="md:col-span-2 text-xs text-gray-500">Without unrestricted permission, persona defaults win over custom amounts for affiliate templates.</p>
        @endunless
    </x-admin.step>

    <x-admin.step title="Duration">
        <x-admin.select name="duration" label="How long?" :options="$durations" required x-model="duration" />
        <div class="md:col-span-2" x-show="duration === 'custom'" x-cloak>
            <x-admin.input name="custom_expires_at" label="Custom end" type="datetime-local" />
        </div>
    </x-admin.step>
</x-admin.create-page>
