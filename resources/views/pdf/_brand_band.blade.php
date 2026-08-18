@php
    $mark = pdf_brand_mark_path();
    $bandTitle = $bandTitle ?? brand('legal_name');
    $bandTag = $bandTag ?? '';
    $bandMeta = $bandMeta ?? '';
@endphp
<table class="band" width="100%" cellspacing="0" cellpadding="0" bgcolor="#0f3d2e">
    <tr>
        <td class="brand-cell" bgcolor="#0f3d2e" valign="middle">
            <table cellspacing="0" cellpadding="0">
                <tr>
                    @if ($mark)
                        <td valign="middle" style="padding-right:12px">
                            <img src="{{ $mark }}" class="logo" alt="kopafasta">
                        </td>
                    @endif
                    <td valign="middle">
                        <div class="wordmark">kopafasta</div>
                        <div class="legal">{{ pdf_text($bandTitle) }}</div>
                        @if ($bandTag !== '')
                            <div class="tag">{{ pdf_text($bandTag) }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td class="meta" bgcolor="#0f3d2e" valign="middle">{!! $bandMeta !!}</td>
    </tr>
</table>
<table class="gold-bar" width="100%" cellspacing="0" cellpadding="0" bgcolor="#f5c842">
    <tr><td bgcolor="#f5c842" style="height:4px;font-size:0;line-height:0">&nbsp;</td></tr>
</table>
