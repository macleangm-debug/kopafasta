<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LocationCountry;
use App\Models\LocationRegion;
use App\Models\User;
use App\Services\LocationLookupService;
use Database\Seeders\LocationMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TanzaniaLocationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function requiredRegions(): array
    {
        return ['Mwanza', 'Dar es Salaam', 'Arusha', 'Dodoma'];
    }

    public function test_canonical_config_has_districts_for_every_tanzania_region(): void
    {
        $tree = config('tanzania_locations', []);

        $this->assertNotEmpty($tree);
        $empty = [];
        foreach ($tree as $region => $districts) {
            if (! is_array($districts) || $districts === []) {
                $empty[] = (string) $region;
            }
        }

        $this->assertSame([], $empty, 'Every Tanzania region in config must have districts.');

        foreach ($this->requiredRegions() as $region) {
            $this->assertArrayHasKey($region, $tree);
            $this->assertNotEmpty($tree[$region], $region.' must have districts.');
        }

        $this->assertContains('Ilemela', $tree['Mwanza']);
        $this->assertContains('Ilala', $tree['Dar es Salaam']);
        $this->assertContains('Arusha City', $tree['Arusha']);
        $this->assertContains('Dodoma City', $tree['Dodoma']);
    }

    public function test_location_tree_after_seeder_covers_entire_tanzania_dataset(): void
    {
        $this->seed(LocationMasterSeeder::class);

        $config = config('tanzania_locations', []);
        $tree = app(LocationLookupService::class)->treeForCountry('TZ');

        foreach ($config as $region => $districts) {
            $this->assertArrayHasKey($region, $tree, $region.' missing from location_tree()');
            $this->assertNotEmpty($tree[$region], $region.' has no districts in location_tree()');
            foreach ($districts as $district) {
                $this->assertContains($district, $tree[$region], $region.' is missing '.$district);
            }
        }
    }

    public function test_empty_database_districts_fall_back_to_canonical_config(): void
    {
        $country = LocationCountry::query()->create([
            'code' => 'TZ',
            'name' => 'Tanzania',
            'is_active' => true,
        ]);

        foreach ($this->requiredRegions() as $name) {
            LocationRegion::query()->create([
                'country_id' => $country->id,
                'name' => $name,
                'is_active' => true,
            ]);
        }

        $service = app(LocationLookupService::class);
        $tree = $service->treeForCountry('TZ');

        foreach ($this->requiredRegions() as $region) {
            $this->assertNotEmpty($tree[$region], $region.' must not return an empty district list when Settings Hub has no districts.');
        }

        $this->assertContains('Ilemela', $service->districtsForRegion('Mwanza'));
        $this->assertContains('Ilemela', $service->districtsForRegion('mwanza'));
        $this->assertContains('Ilala', $service->districtsForRegion('Dar es Salaam'));
        $this->assertSame([], $service->districtsForRegion(''));
    }

    public function test_activity_page_embeds_shared_tree_and_hydrates_saved_mwanza_district(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-LOC'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mwanza',
            'phone' => '255712345678',
            'activity_type' => 'business_owner',
            'income_range' => array_key_first(config('income_ranges', ['0-500000' => []])),
            'activity_details' => [
                'business_name' => 'Asha Shop',
                'region' => 'Mwanza',
                'district' => 'Ilemela',
                'street' => 'Kenya Road',
                'employee_count' => '1',
            ],
        ]);

        $html = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.borrower.profile', ['section' => 'activity']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('function activityForm', $html);
        $this->assertStringContainsString('refreshDistricts', $html);
        $this->assertStringContainsString('districtOptions', $html);
        $this->assertStringContainsString('Mwanza', $html);
        $this->assertStringContainsString('Ilemela', $html);
        $this->assertStringContainsString('Nyamagana', $html);
        $this->assertStringContainsString('Ilala', $html);
        $this->assertStringContainsString('Arusha City', $html);
        $this->assertStringContainsString('Dodoma City', $html);
        $this->assertStringContainsString('Unable to load districts. Try again.', $html);
        $this->assertStringContainsString('Loading districts', $html);
        $this->assertStringNotContainsString('districtsForRegion(details.region)', $html);
    }

    public function test_activity_and_address_fields_use_swahili_empty_states(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        Customer::create([
            'user_id' => $user->id,
            'customer_number' => 'C-LOCSW'.random_int(100000, 999999),
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Asha',
            'last_name' => 'Mwanza',
            'phone' => '255712345679',
            'activity_type' => 'business_owner',
            'activity_details' => [
                'business_name' => 'Duka',
                'region' => 'Mwanza',
                'district' => 'Nyamagana',
                'street' => 'Pamba',
                'employee_count' => '1',
            ],
        ]);

        $activity = $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.borrower.profile', ['section' => 'activity']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Chagua wilaya', $activity);
        $this->assertStringContainsString('Imeshindwa kupakia wilaya. Jaribu tena.', $activity);
        $this->assertStringContainsString('Inapakia wilaya', $activity);
        $this->assertStringContainsString('lg:hidden', $activity);

        $residence = $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.borrower.profile', ['section' => 'residence']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-kf-address-fields', $residence);
        $this->assertStringContainsString('Imeshindwa kupakia wilaya. Jaribu tena.', $residence);
        $this->assertStringContainsString('Mwanza', $residence);
        $this->assertStringContainsString('Nyamagana', $residence);
    }

    public function test_shared_address_fields_and_activity_component_use_location_tree(): void
    {
        $address = file_get_contents(resource_path('views/components/site/address-fields.blade.php'));
        $activity = file_get_contents(resource_path('views/components/site/activity-fields.blade.php'));
        $wizard = file_get_contents(resource_path('views/site/apply/wizard.blade.php'));

        $this->assertStringContainsString("location_tree('TZ')", $address);
        $this->assertStringContainsString("location_tree('TZ')", $activity);
        $this->assertStringContainsString("location_tree('TZ')", $wizard);
        $this->assertStringNotContainsString("config('tanzania_locations')", $activity);
        $this->assertStringContainsString('retryDistricts', $address);
        $this->assertStringContainsString('retryDistricts', $activity);
        $this->assertStringContainsString('openDistrictPicker()', $address);
        $this->assertStringNotContainsString(':disabled="!details.region', $activity);
        $this->assertStringNotContainsString(':disabled="!region || districtStatus === \'loading\'"', $address);

        $sheet = file_get_contents(resource_path('views/components/site/bottom-sheet.blade.php'));
        $this->assertStringNotContainsString('max-width: 1023px', $sheet);
    }

    public function test_login_locks_viewport_and_refuses_browser_translate(): void
    {
        $html = $this->get(route('site.login'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('translate="no"', $html);
        $this->assertStringContainsString('notranslate', $html);
        $this->assertStringContainsString('name="google" content="notranslate"', $html);
        $this->assertStringContainsString('kf-auth-lock', $html);
        $this->assertStringContainsString('100dvh', $html);
    }
}
