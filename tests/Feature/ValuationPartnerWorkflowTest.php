<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PartnerMembershipService;
use App\Services\PartnerMatchingService;
use App\Services\ValuationPartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValuationPartnerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplication(string $region = 'Dar es Salaam'): LoanApplication
    {
        $branch = Branch::create([
            'code' => 'BR-V',
            'name' => 'Branch',
            'region' => $region,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'branch_id'       => $branch->id,
            'customer_number' => 'CU-V-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Asset',
            'last_name'       => 'Owner',
            'phone'           => '255712345678',
            'region'          => $region,
            'district'        => 'Kinondoni',
            'street'          => 'Sample Street',
        ]);

        $product = LoanProduct::create([
            'code'               => 'AB',
            'name'               => 'Asset Backed',
            'is_active'          => true,
            'interest_rate'      => 3.5,
            'min_amount'         => 100_000,
            'max_amount'         => 10_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
        ]);

        return LoanApplication::create([
            'customer_id'        => $customer->id,
            'loan_product_id'    => $product->id,
            'application_number' => 'APP-V-001',
            'status'             => 'submitted',
            'current_stage'      => 'submitted',
            'requested_amount'   => 2_000_000,
            'requested_tenure_months' => 12,
        ]);
    }

    private function readyValuer(Vendor $valuer): Vendor
    {
        $valuer->update([
            'phone' => '255700000010',
            'email' => 'valuer-'.$valuer->id.'@test.local',
            'legal_name' => $valuer->name.' Ltd',
            'registration_number' => 'REG-'.$valuer->id,
            'metadata' => [
                'contact_person' => ['name' => 'Jane Contact'],
                'identity' => [
                    'national_id' => '19800101123456789012',
                    'no_physical_nida_card' => true,
                ],
                'residence' => [
                    'region' => 'Dar es Salaam',
                    'district' => 'Ilala',
                ],
                'payout_account' => ['type' => 'mobile_money'],
            ],
        ]);

        app(PartnerMembershipService::class)->activate($valuer);

        $terms = app(\App\Services\PartnerTermsService::class);
        if ($terms->appliesTo($valuer) && ! $terms->hasSatisfiedTerms($valuer)) {
            $terms->accept($valuer, \Illuminate\Http\Request::create('/partner/terms', 'POST'));
        }

        return $valuer->fresh();
    }

    public function test_matching_service_prefers_valuer_in_borrower_region(): void
    {
        $local = Vendor::create([
            'vendor_number' => 'V-DSM',
            'name'          => 'Dar Valuer',
            'category'      => 'valuer',
            'status'        => 'active',
            'regions'       => ['Dar es Salaam'],
        ]);
        $this->readyValuer($local);

        $remote = Vendor::create([
            'vendor_number' => 'V-ARU',
            'name'          => 'Arusha Valuer',
            'category'      => 'valuer',
            'status'        => 'active',
            'regions'       => ['Arusha'],
        ]);
        $this->readyValuer($remote);

        $application = $this->makeApplication('Dar es Salaam');

        $suggested = app(PartnerMatchingService::class)->suggestValuer($application);

        $this->assertNotNull($suggested);
        $this->assertSame($local->id, $suggested->id);
    }

    public function test_valuation_assignment_enriches_task_and_completes_report(): void
    {
        $valuer = Vendor::create([
            'vendor_number' => 'V-VAL',
            'name'          => 'Copper Fasta Valuer',
            'category'      => 'valuer',
            'status'        => 'active',
            'partner_cost'  => 30_000,
            'regions'       => ['Dar es Salaam'],
        ]);
        $this->readyValuer($valuer);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $application = $this->makeApplication();

        $assignment = app(ValuationPartnerService::class)->assign(
            $application,
            $valuer->fresh(),
            $admin,
            'Inspect vehicle at borrower location',
        );

        $this->assertSame('assigned', $assignment->status);
        $this->assertNotNull($assignment->vendor_task_id);
        $this->assertSame('Sample Street, Kinondoni, Dar es Salaam', $assignment->vendorTask?->location);

        $completed = app(ValuationPartnerService::class)->complete(
            $assignment->fresh(),
            5_000_000,
            4_000_000,
            'Good condition saloon car.',
        );

        $this->assertSame('completed', $completed->status);
        $report = app(ValuationPartnerService::class)->reportForApplication($application->fresh());
        $this->assertSame('completed', $report['status']);
        $this->assertSame(5000000.0, (float) $report['market_value']);
    }
}
