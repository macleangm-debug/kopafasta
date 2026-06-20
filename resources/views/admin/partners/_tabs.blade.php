{{-- Partner tabs partial. --}}
<x-admin.tabs :items="[
    ['label' => 'Partners hub',         'route' => 'admin.partners.index'],
    ['label' => 'All Partners',         'route' => 'admin.partners.all'],
    ['label' => 'Partner Applications', 'route' => 'admin.partners.applications'],
    ['label' => 'GPS Installers',      'route' => 'admin.partners.gps-installers'],
    ['label' => 'Insurance Providers', 'route' => 'admin.partners.insurance-providers'],
    ['label' => 'Valuers',             'route' => 'admin.partners.valuers'],
    ['label' => 'Suppliers',           'route' => 'admin.partners.suppliers'],
    ['label' => 'Affiliates',          'route' => 'admin.partners.affiliates'],
    ['label' => 'Partner Tasks',        'route' => 'admin.partners.tasks'],
]" />
