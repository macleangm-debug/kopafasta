{{-- Partner tabs partial. Prefer role chips on the hub; keep for legacy includes. --}}
<x-admin.tabs :items="[
    ['label' => 'Partners hub',         'route' => 'admin.partners.index'],
    ['label' => 'Enrollment apps',      'route' => 'admin.partner-applications.index'],
    ['label' => 'Partner Applications', 'route' => 'admin.partners.applications'],
    ['label' => 'Partner Tasks',        'route' => 'admin.partners.tasks'],
    ['label' => 'Recovery partners',    'route' => 'admin.recovery.partners.index'],
]" />
