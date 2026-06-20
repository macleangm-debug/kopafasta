<?php

namespace Tests\Feature;

use App\Models\LocationDistrict;
use App\Models\LocationWard;
use App\Models\User;
use App\Services\LocationLookupService;
use Database\Seeders\LocationMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase51FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_master_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.settings.locations.index'));
        $this->assertTrue(Route::has('admin.settings.locations.create'));
        $this->assertTrue(Route::has('admin.settings.locations.store'));
    }

    public function test_admin_can_create_ward_and_lookup_service_finds_it(): void
    {
        $this->seed(LocationMasterSeeder::class);

        $district = LocationDistrict::query()
            ->where('name', 'Ilala')
            ->whereHas('region', fn ($q) => $q->where('name', 'Dar es Salaam'))
            ->first();

        $this->assertNotNull($district);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.locations.store'), [
                'district_id' => $district->id,
                'name'        => 'Kijitonyama Test',
                'is_active'   => '1',
            ])
            ->assertRedirect(route('admin.settings.locations.index'));

        $this->assertDatabaseHas('location_wards', [
            'district_id' => $district->id,
            'name'        => 'Kijitonyama Test',
            'is_active'   => true,
        ]);

        $wards = app(LocationLookupService::class)->wardsForDistrictName('Ilala', 'Dar es Salaam');

        $this->assertTrue(collect($wards)->contains('name', 'Kijitonyama Test'));
    }

    public function test_admin_location_index_lists_wards(): void
    {
        $this->seed(LocationMasterSeeder::class);

        $district = LocationDistrict::query()->first();
        LocationWard::create([
            'district_id' => $district->id,
            'name'        => 'Listed Ward P51',
            'is_active'   => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.locations.index', ['q' => 'Listed Ward P51']))
            ->assertOk()
            ->assertSee('Listed Ward P51', false)
            ->assertSee('Location master', false);
    }
}
