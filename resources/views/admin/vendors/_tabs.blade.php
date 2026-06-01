{{-- Vendor tabs partial. --}}
<x-admin.tabs :items="[
    ['label' => 'All Vendors',         'route' => 'admin.vendors.index'],
    ['label' => 'Vendor Applications', 'route' => 'admin.vendors.applications'],
    ['label' => 'GPS Installers',      'route' => 'admin.vendors.gps-installers'],
    ['label' => 'Insurance Providers', 'route' => 'admin.vendors.insurance-providers'],
    ['label' => 'Valuers',             'route' => 'admin.vendors.valuers'],
    ['label' => 'Suppliers',           'route' => 'admin.vendors.suppliers'],
    ['label' => 'Affiliates',          'route' => 'admin.vendors.affiliates'],
    ['label' => 'Vendor Tasks',        'route' => 'admin.vendors.tasks'],
]" />
