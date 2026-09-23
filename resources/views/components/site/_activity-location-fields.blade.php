{{-- Same Region → District control as Residence / Profile. Do not add a second geography engine. --}}
@php
    $mwanzaDistricts = location_districts('Mwanza');
    $dsmDistricts = location_districts('Dar es Salaam');
@endphp
<div class="sm:col-span-2"
     data-kf-activity-location
     data-kf-mwanza-district-count="{{ count($mwanzaDistricts) }}"
     data-kf-dsm-district-count="{{ count($dsmDistricts) }}"
     x-show="hasLocationFields()"
     x-cloak
     x-effect="syncLocationEnabled()">
    <x-site.address-fields
        form-key="activity_details"
        :region="old('activity_details.region', $details['region'] ?? '')"
        :district="old('activity_details.district', $details['district'] ?? '')"
        :show-ward="false"
        :show-street="false"
        :emit-hidden="false"
    />
</div>
