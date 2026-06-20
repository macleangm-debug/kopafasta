<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocationCountry;
use App\Models\LocationDistrict;
use App\Models\LocationRegion;
use App\Models\LocationWard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationMasterController extends Controller
{
    public function index(Request $request): View
    {
        $countryId = $request->integer('country_id')
            ?: (int) LocationCountry::query()->where('code', 'TZ')->value('id');
        $regionId = $request->filled('region_id') ? (int) $request->input('region_id') : null;
        $districtId = $request->filled('district_id') ? (int) $request->input('district_id') : null;
        $search = trim((string) $request->input('q', ''));

        $wards = LocationWard::query()
            ->with(['district.region.country'])
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->when($regionId && ! $districtId, fn ($q) => $q->whereHas(
                'district',
                fn ($d) => $d->where('region_id', $regionId),
            ))
            ->when($countryId, fn ($q) => $q->whereHas(
                'district.region',
                fn ($r) => $r->where('country_id', $countryId),
            ))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $countries = LocationCountry::query()->orderBy('name')->get();
        $regions = LocationRegion::query()
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->orderBy('name')
            ->get();
        $districts = LocationDistrict::query()
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->when(! $regionId && $countryId, fn ($q) => $q->whereHas(
                'region',
                fn ($r) => $r->where('country_id', $countryId),
            ))
            ->orderBy('name')
            ->get();

        return view('admin.settings.locations.index', [
            'wards'      => $wards,
            'countries'  => $countries,
            'regions'    => $regions,
            'districts'  => $districts,
            'countryId'  => $countryId,
            'regionId'   => $regionId,
            'districtId' => $districtId,
            'search'     => $search,
            'stats'      => [
                'regions'   => LocationRegion::query()->when($countryId, fn ($q) => $q->where('country_id', $countryId))->count(),
                'districts' => LocationDistrict::query()->when($countryId, fn ($q) => $q->whereHas('region', fn ($r) => $r->where('country_id', $countryId)))->count(),
                'wards'     => LocationWard::query()->when($countryId, fn ($q) => $q->whereHas('district.region', fn ($r) => $r->where('country_id', $countryId)))->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.settings.locations.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        LocationWard::create($this->validated($request));

        return redirect()->route('admin.settings.locations.index')
            ->with('status', 'Ward added.');
    }

    public function edit(LocationWard $location): View
    {
        return view('admin.settings.locations.edit', $this->formData($location) + ['ward' => $location]);
    }

    public function update(Request $request, LocationWard $location): RedirectResponse
    {
        $location->update($this->validated($request));

        return redirect()->route('admin.settings.locations.index')
            ->with('status', 'Ward updated.');
    }

    public function destroy(LocationWard $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('admin.settings.locations.index')
            ->with('status', 'Ward removed.');
    }

    /** @return array<string, mixed> */
    private function formData(?LocationWard $ward = null): array
    {
        $regions = LocationRegion::query()
            ->with(['districts' => fn ($q) => $q->orderBy('name')])
            ->whereHas('country', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return [
            'regions' => $regions,
            'ward'    => $ward,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'district_id' => ['required', 'exists:location_districts,id'],
            'name'        => ['required', 'string', 'max:100'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['name'] = trim($data['name']);

        $ward = $request->route('location');
        $exists = LocationWard::query()
            ->where('district_id', $data['district_id'])
            ->where('name', $data['name'])
            ->when($ward instanceof LocationWard, fn ($q) => $q->where('id', '!=', $ward->id))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'This ward already exists in the selected district.',
            ]);
        }

        return $data;
    }
}
