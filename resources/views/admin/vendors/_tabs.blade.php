{{-- Partner tabs partial. --}}
<x-admin.tabs :items="[
    ['label' => 'All Partners',         'route' => 'admin.vendors.index'],
    ['label' => 'Partner Applications', 'route' => 'admin.vendors.applications'],
    ['label' => 'GPS Installers',      'route' => 'admin.vendors.gps-installers'],
    ['label' => 'Insurance Providers', 'route' => 'admin.vendors.insurance-providers'],
    ['label' => 'Valuers',             'route' => 'admin.vendors.valuers'],
    ['label' => 'Suppliers',           'route' => 'admin.vendors.suppliers'],
    ['label' => 'Affiliates',          'route' => 'admin.vendors.affiliates'],
    ['label' => 'Partner Tasks',        'route' => 'admin.vendors.tasks'],
]" />
