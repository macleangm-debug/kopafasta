@php
    $logo = public_path('images/brand/kopafasta-mark.png');
    if (! is_file($logo)) {
        $logo = public_path('images/brand/kopafasta-logo.png');
    }
@endphp
<style>
    @page { margin: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1c2b24; line-height: 1.45; margin: 0; }
    h1 { font-size: 18px; margin: 0; letter-spacing: 0.4px; color: #f5c842; }
    h2 { font-size: 11.5px; margin: 14px 0 6px; color: #0f3d2e; text-transform: uppercase; letter-spacing: 0.6px; }
    h3 { font-size: 10.5px; margin: 10px 0 4px; color: #0f3d2e; }
    .muted { color: #6b7c74; font-size: 9px; }
    .sw { color: #3d4f46; font-size: 9.5px; margin-top: 3px; }
    .band { background: #0f3d2e; color: #fff; padding: 20px 26px 16px; }
    .band .tag { font-size: 9.5px; color: rgba(255,255,255,0.75); margin-top: 4px; }
    .gold-bar { height: 4px; background: #f5c842; }
    .wrap { padding: 18px 26px 24px; }
    .logo { max-height: 34px; margin-bottom: 6px; }
    .meta { text-align: right; color: rgba(255,255,255,0.85); font-size: 9px; }
    .notice { margin: 10px 0 12px; padding: 10px 12px; background: #f7faf8; border-left: 3px solid #f5c842; }
    .notice strong { color: #0f3d2e; }
    table.kv { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.kv td { padding: 3px 5px; vertical-align: top; }
    table.kv td.label { color: #6b7c74; width: 38%; font-size: 8.5px; text-transform: uppercase; }
    table.kv td.value { color: #12241c; font-weight: 600; }
    table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 8.5px; }
    table.grid th, table.grid td { border: 1px solid #e5ebe7; padding: 3px 5px; }
    table.grid th { background: #f7faf8; text-transform: uppercase; font-size: 8px; color: #0f3d2e; }
    .charges { width: 100%; border-collapse: collapse; font-size: 9.5px; margin-top: 6px; }
    .charges td { padding: 4px 6px; border: 1px solid #e5ebe7; vertical-align: top; }
    .charges td:first-child { background: #f7faf8; width: 32%; font-weight: 600; }
    .signbox { margin-top: 16px; padding: 10px; border: 1px dashed #0f3d2e; background: #f7faf8; }
    .sig-img { max-height: 56px; max-width: 160px; }
    .stamp-img { max-height: 70px; max-width: 70px; margin-top: 4px; }
    .na { color: #8a9a92; font-style: italic; }
    .annex { page-break-before: always; }
    ol.acks li { margin-bottom: 3px; }
</style>
