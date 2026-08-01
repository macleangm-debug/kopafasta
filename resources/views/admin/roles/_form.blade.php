@php
    $r = $record ?? null;
    $selected = is_array($r?->permissions) ? $r->permissions : [];
    if (old('permissions')) {
        $selected = old('permissions');
    }
    $catalog = app(\App\Services\PermissionService::class)->catalogByModule();
@endphp
<x-admin.step title="Role">
    <x-admin.input  name="code" label="Code" :value="$r?->code" required placeholder="e.g. officer" />
    <x-admin.input  name="name" label="Name" :value="$r?->name" required />
    <x-admin.select name="is_system" label="System role" :options="['1'=>'Yes','0'=>'No']" :value="(string)($r?->is_system ?? '0')" />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="2" />
    </div>
    <div class="md:col-span-2">
        <p class="text-sm font-medium text-gray-700 mb-3">Permissions</p>
        <div class="space-y-4 max-h-96 overflow-y-auto rounded-lg border border-gray-200 p-4">
            @foreach ($catalog as $moduleKey => $module)
                @if (count($module['permissions']) === 0)
                    @continue
                @endif
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ $module['label'] }}</p>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach ($module['permissions'] as $perm)
                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="permissions[]" value="{{ $perm['key'] }}"
                                       @checked(in_array($perm['key'], $selected, true))
                                       class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                                <span>{{ $perm['label'] }}<span class="block text-[10px] font-mono text-gray-400">{{ $perm['key'] }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-admin.step>
