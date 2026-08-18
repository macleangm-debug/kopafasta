@php
    $logo = public_path('images/brand/kopafasta-mark.png');
    if (! is_file($logo)) {
        $logo = public_path('images/brand/kopafasta-logo.png');
    }
    $bandTitle = $bandTitle ?? brand('legal_name');
    $bandTag = $bandTag ?? '';
    $bandMeta = $bandMeta ?? '';
@endphp
<div class="band">
    <table style="width:100%"><tr>
        <td>
            @if (is_file($logo))
                <img src="{{ $logo }}" class="logo" alt="">
            @endif
            <h1>{{ $bandTitle }}</h1>
            @if ($bandTag !== '')
                <div class="tag">{{ $bandTag }}</div>
            @endif
        </td>
        <td class="meta">{!! $bandMeta !!}</td>
    </tr></table>
</div>
<div class="gold-bar"></div>
