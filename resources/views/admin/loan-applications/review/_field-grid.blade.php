{{-- Compact field grid used inside credit profile tabs --}}
@props([
    'fields' => [],
])

<dl class="grid sm:grid-cols-2 gap-x-5 gap-y-4 text-sm">
    @foreach ($fields as $field)
        <div @class(['sm:col-span-2' => ! empty($field['span'])])>
            <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $field['label'] }}</dt>
            <dd class="mt-1 font-medium text-gray-900 {{ $field['class'] ?? '' }}">
                @if (array_key_exists('html', $field))
                    {!! $field['html'] !!}
                @else
                    {{ $field['value'] ?? '—' }}
                @endif
            </dd>
        </div>
    @endforeach
</dl>
