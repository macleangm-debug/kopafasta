@php($r = $record ?? null)
<x-admin.step title="Section">
    <x-admin.input name="key" label="Key" :value="$r?->key" required placeholder="personal_info" />
    <x-admin.input name="icon" label="Icon (emoji)" :value="$r?->icon" placeholder="👤" />
    <x-admin.input name="name_en" label="Name (English)" :value="$r?->name_en" required />
    <x-admin.input name="name_sw" label="Name (Swahili)" :value="$r?->name_sw" />
    <div class="md:col-span-2">
        <x-admin.textarea name="description_en" label="Description (English)" :value="$r?->description_en" rows="2" />
    </div>
    <div class="md:col-span-2">
        <x-admin.textarea name="description_sw" label="Description (Swahili)" :value="$r?->description_sw" rows="2" />
    </div>
    <x-admin.select name="input_type" label="Input type" :options="$inputTypes" :value="$r?->input_type ?? 'section_link'" required />
    <x-admin.select name="maps_to" label="Maps to existing section" :options="$mapTargets" :value="$r?->metadata['maps_to'] ?? null" placeholder="— Optional —" />
    <x-admin.input name="display_order" label="Display order" type="number" :value="$r?->display_order ?? 0" required />
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" value="1" @checked($r?->is_required ?? true)> Required</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="required_before_loan" value="1" @checked($r?->required_before_loan ?? false)> Required before loan submission</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($r?->is_active ?? true)> Active</label>
    <div class="md:col-span-2">
        <x-admin.textarea name="validation_rules" label="Validation rules (JSON)" rows="3" :value="$r?->validation_rules ? json_encode($r->validation_rules, JSON_PRETTY_PRINT) : ''" />
    </div>
</x-admin.step>
