{{-- Partner tabs partial. Prefer role chips on the hub; keep for legacy includes. --}}
<x-admin.tabs :items="[
    ['label' => 'Partners hub',         'route' => 'admin.partners.index'],
    ['label' => 'Partner applications', 'route' => 'admin.partner-applications.index'],
    ['label' => 'Recovery partners',    'route' => 'admin.recovery.partners.index'],
]" />
