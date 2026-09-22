<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\GuarantorInvitationService;
use App\Services\GuarantorOnboardingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportProductionUatApplicationsCommand extends Command
{
    protected $signature = 'uat:import-production-applications {dir : Export directory containing manifest.json}';

    protected $description = 'One-way import of the three production UAT applications. Does not contact production.';

    public function handle(GuarantorInvitationService $guarantors, GuarantorOnboardingService $onboarding): int
    {
        $dir = rtrim((string) $this->argument('dir'), '/');
        $manifestPath = $dir.'/manifest.json';
        if (! is_file($manifestPath)) {
            $this->error('manifest.json was not found.');

            return self::FAILURE;
        }

        /** @var array<string, mixed> $bundle */
        $bundle = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($bundle)) {
            $this->error('manifest.json is not valid.');

            return self::FAILURE;
        }

        $numbers = $bundle['application_numbers'] ?? [];
        $existing = LoanApplication::query()->whereIn('application_number', $numbers)->pluck('application_number');
        if ($existing->isNotEmpty()) {
            $this->error('Staging already has: '.$existing->implode(', '));

            return self::FAILURE;
        }

        config(['kopafasta.suppress_guarantor_release' => true]);

        $maps = [
            'users' => [],
            'customers' => [],
            'guarantors' => [],
            'loan_applications' => [],
            'customer_guarantors' => [],
            'customer_documents' => [],
            'products' => [],
            'branches' => [],
            'document_types' => [],
        ];

        DB::transaction(function () use ($bundle, $dir, &$maps): void {
            $maps['products'] = $this->mapProducts($bundle['products'] ?? []);
            $maps['branches'] = $this->mapBranches($bundle['branches'] ?? []);
            $maps['document_types'] = $this->mapDocumentTypes($bundle['document_types'] ?? []);
            $maps['users'] = $this->insertUsers($bundle['users'] ?? []);
            $maps['customers'] = $this->insertCustomers($bundle['customers'] ?? [], $maps);
            $this->insertSimple('customer_kycs', $bundle['customer_kyc'] ?? [], [
                'customer_id' => $maps['customers'],
                'verified_by' => [],
            ]);
            $maps['guarantors'] = $this->insertKeepingMap('guarantors', $bundle['guarantors'] ?? []);
            $maps['loan_applications'] = $this->insertApplications($bundle['loan_applications'] ?? [], $maps);
            $maps['customer_guarantors'] = $this->insertKeepingMap('customer_guarantors', $bundle['customer_guarantors'] ?? [], [
                'customer_id' => $maps['customers'],
                'guarantor_id' => $maps['guarantors'],
                'loan_application_id' => $maps['loan_applications'],
            ]);
            $this->insertSimple('guarantor_invitations', $bundle['guarantor_invitations'] ?? [], [
                'customer_id' => $maps['customers'],
                'guarantor_customer_id' => $maps['customers'],
                'customer_guarantor_id' => $maps['customer_guarantors'],
                'loan_application_id' => $maps['loan_applications'],
                'loan_product_id' => $maps['products'],
            ], ['token']);
            $requestMap = $this->insertKeepingMap('loan_application_document_requests', $bundle['loan_application_document_requests'] ?? [], [
                'loan_application_id' => $maps['loan_applications'],
                'requested_by' => [],
                'customer_id' => $maps['customers'],
            ]);
            $maps['customer_documents'] = $this->insertKeepingMap('customer_documents', $bundle['customer_documents'] ?? [], [
                'customer_id' => $maps['customers'],
                'document_type_id' => $maps['document_types'],
                'loan_application_id' => $maps['loan_applications'],
                'loan_application_document_request_id' => $requestMap,
                'loan_product_requirement_id' => [],
                'verified_by' => [],
            ]);
            $this->insertSimple('customer_disbursement_accounts', $bundle['customer_disbursement_accounts'] ?? [], [
                'customer_id' => $maps['customers'],
            ]);
            $this->insertSimple('face_verifications', $bundle['face_verifications'] ?? [], [
                'customer_id' => $maps['customers'],
            ]);
            $this->insertSimple('loan_application_assets', $bundle['loan_application_assets'] ?? [], [
                'loan_application_id' => $maps['loan_applications'],
                'customer_id' => $maps['customers'],
            ]);
            $this->insertSimple('application_stage_histories', $bundle['application_stage_histories'] ?? [], [
                'loan_application_id' => $maps['loan_applications'],
                'user_id' => [],
                'actor_id' => [],
            ]);
            $this->insertSimple('loan_application_document_reviews', $bundle['loan_application_document_reviews'] ?? [], [
                'loan_application_id' => $maps['loan_applications'],
                'customer_document_id' => $maps['customer_documents'],
                'reviewer_id' => [],
                'reviewed_by' => [],
            ]);
            $this->insertSimple('credit_histories', $bundle['credit_histories'] ?? [], [
                'customer_id' => $maps['customers'],
                'loan_application_id' => $maps['loan_applications'],
            ]);
            $this->insertSimple('customer_payments', $bundle['customer_payments'] ?? [], [
                'customer_id' => $maps['customers'],
                'loan_product_id' => $maps['products'],
                'source_id' => $maps['loan_applications'],
            ], [], [
                'journal_entry_id' => null,
                'loan_id' => null,
                'partner_id' => null,
                'bank_account_id' => null,
                'mobile_money_account_id' => null,
                'verified_by' => null,
                'created_by' => null,
            ]);
            $this->copyFiles($dir, $bundle['files'] ?? []);
        });

        config(['kopafasta.suppress_guarantor_release' => false]);

        $report = [];
        foreach ($numbers as $number) {
            $application = LoanApplication::query()->where('application_number', $number)->first();
            if (! $application) {
                $report[] = ['application' => $number, 'imported' => false];
                continue;
            }
            $before = (string) $application->status;
            $blocker = $guarantors->guarantorHoldBlocker($application);
            $released = $guarantors->tryReleaseApplicationFromGuarantorHold($application->fresh());
            $fresh = $application->fresh();
            $link = $fresh->customerGuarantors()->orderByDesc('id')->first();
            $guarantor = $link ? app(\App\Services\GuarantorAccessService::class)->guarantorCustomerForLink($link) : null;
            $profile = $guarantor ? $onboarding->guarantorProfileStatus($guarantor) : null;
            $missing = collect($profile['checklist']['items'] ?? [])->where('complete', false)->pluck('label')->values()->all();
            $report[] = [
                'application' => $number,
                'staging_id' => $fresh->id,
                'applicant' => trim(($fresh->customer->first_name ?? '').' '.($fresh->customer->last_name ?? '')),
                'stored_status_before_resolver' => $before,
                'blocker' => $blocker,
                'released' => $released,
                'status_after' => $fresh->status,
                'stage_after' => $fresh->current_stage,
                'guarantor' => $guarantor ? trim($guarantor->first_name.' '.$guarantor->last_name) : null,
                'link_status' => $link->status ?? null,
                'profile_percent' => $profile['percent'] ?? null,
                'profile_met' => $profile['met'] ?? null,
                'missing' => $missing,
                'url' => $fresh->status === 'submitted' && $fresh->current_stage === 'screening'
                    ? url('/admin/loan-applications/'.$fresh->id.'/guided-screening')
                    : null,
            ];
        }

        $this->line(json_encode([
            'customers' => count($maps['customers']),
            'applications' => count($maps['loan_applications']),
            'documents' => count($maps['customer_documents']),
            'files' => count($bundle['files'] ?? []),
            'payments' => count($bundle['customer_payments'] ?? []),
            'collateral_assets' => count($bundle['loan_application_assets'] ?? []) + count($bundle['customer_assets'] ?? []),
            'report' => $report,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function mapProducts(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $product = LoanProduct::query()->where('code', $row['code'] ?? '')->first();
            if (! $product) {
                throw new \RuntimeException('Staging has no loan product '.($row['code'] ?? ''));
            }
            $map[(int) $row['id']] = (int) $product->id;
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function mapBranches(array $rows): array
    {
        $fallback = (int) (Branch::query()->where('is_active', true)->value('id') ?: Branch::query()->value('id'));
        $map = [];
        foreach ($rows as $row) {
            $branch = Branch::query()->where('code', $row['code'] ?? '')->first();
            $map[(int) $row['id']] = (int) ($branch->id ?? $fallback);
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function mapDocumentTypes(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $type = DocumentType::query()->where('code', $row['code'] ?? '')->first();
            if ($type) {
                $map[(int) $row['id']] = (int) $type->id;
            }
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function insertUsers(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $oldId = (int) $row['id'];
            unset($row['id']);
            $email = (string) ($row['email'] ?? '');
            if ($email !== '' && DB::table('users')->where('email', $email)->exists()) {
                throw new \RuntimeException('Staging already has user email '.$email);
            }
            $phone = (string) ($row['phone'] ?? '');
            if ($phone !== '' && Schema::hasColumn('users', 'phone') && DB::table('users')->where('phone', $phone)->exists()) {
                throw new \RuntimeException('Staging already has user phone '.$phone);
            }
            $row['password'] = Hash::make(Str::random(40));
            $row['production_uat_marker'] = Schema::hasColumn('users', 'production_uat_marker') ? 'production-uat-copy' : null;
            $map[$oldId] = $this->insertRow('users', $row);
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $rows
     * @param  array<string, array<int, int>>  $maps
     */
    private function insertCustomers(array $rows, array $maps): array
    {
        $map = [];
        $referrals = [];
        foreach ($rows as $row) {
            $oldId = (int) $row['id'];
            $referrals[$oldId] = isset($row['referred_by_customer_id']) ? (int) $row['referred_by_customer_id'] : null;
            $row['user_id'] = $maps['users'][(int) ($row['user_id'] ?? 0)] ?? null;
            $row['branch_id'] = $maps['branches'][(int) ($row['branch_id'] ?? 0)] ?? null;
            $row['referred_by_customer_id'] = null;
            $row['affiliate_partner_id'] = null;
            $row['grade_override_by'] = null;
            $row['production_uat_marker'] = 'production-uat-copy';
            foreach (['customer_number', 'phone', 'email', 'national_id'] as $unique) {
                $value = $row[$unique] ?? null;
                if (filled($value) && Schema::hasColumn('customers', $unique) && Customer::query()->where($unique, $value)->exists()) {
                    throw new \RuntimeException('Staging already has customer '.$unique.' '.$value);
                }
            }
            unset($row['id']);
            $map[$oldId] = $this->insertRow('customers', $row);
        }

        foreach ($referrals as $oldId => $referrerId) {
            $newReferrer = $map[$referrerId] ?? null;
            if ($newReferrer) {
                DB::table('customers')->where('id', $map[$oldId])->update([
                    'referred_by_customer_id' => $newReferrer,
                ]);
            }
        }

        return $map;
    }

    /** @param  list<array<string, mixed>>  $rows
     * @param  array<string, array<int, int>>  $maps
     */
    private function insertApplications(array $rows, array $maps): array
    {
        $map = [];
        foreach ($rows as $row) {
            $oldId = (int) $row['id'];
            $row['customer_id'] = $maps['customers'][(int) $row['customer_id']] ?? null;
            $row['loan_product_id'] = $maps['products'][(int) ($row['loan_product_id'] ?? 0)] ?? null;
            $row['branch_id'] = $maps['branches'][(int) ($row['branch_id'] ?? 0)] ?? ($row['branch_id'] ?? null);
            $row['production_uat_marker'] = 'production-uat-copy';
            foreach (['assigned_analyst_id', 'recommended_by', 'alternative_loan_product_id', 'disbursement_account_id', 'loan_group_id', 'loan_id'] as $drop) {
                if (array_key_exists($drop, $row)) {
                    $row[$drop] = null;
                }
            }
            unset($row['id']);
            $map[$oldId] = $this->insertRow('loan_applications', $row);
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, array<int, int>>  $relations
     * @return array<int, int>
     */
    private function insertKeepingMap(string $table, array $rows, array $relations = []): array
    {
        $map = [];
        if (! Schema::hasTable($table)) {
            return $map;
        }
        foreach ($rows as $row) {
            $oldId = (int) ($row['id'] ?? 0);
            $map[$oldId] = $this->insertRow($table, $this->remap($row, $relations));
        }

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, array<int, int>>  $relations
     * @param  list<string>  $uniqueSuffix
     * @param  array<string, mixed>  $force
     */
    private function insertSimple(string $table, array $rows, array $relations = [], array $uniqueSuffix = [], array $force = []): void
    {
        if (! Schema::hasTable($table) || $rows === []) {
            return;
        }
        foreach ($rows as $row) {
            $row = $this->remap($row, $relations);
            foreach ($uniqueSuffix as $column) {
                if (filled($row[$column] ?? null) && DB::table($table)->where($column, $row[$column])->exists()) {
                    $row[$column] = $row[$column].'-uat';
                }
            }
            foreach ($force as $column => $value) {
                if (Schema::hasColumn($table, $column)) {
                    $row[$column] = $value;
                }
            }
            if (($row['source_type'] ?? null) && ! str_contains((string) $row['source_type'], 'LoanApplication')) {
                $row['source_type'] = null;
                $row['source_id'] = null;
            }
            $this->insertRow($table, $row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<int, int>>  $relations
     * @return array<string, mixed>
     */
    private function remap(array $row, array $relations): array
    {
        unset($row['id']);
        foreach ($relations as $column => $map) {
            if (! array_key_exists($column, $row) || $row[$column] === null) {
                continue;
            }
            $old = (int) $row[$column];
            $row[$column] = $map === [] ? null : ($map[$old] ?? null);
        }

        return $row;
    }

    /** @param  array<string, mixed>  $row */
    private function insertRow(string $table, array $row): int
    {
        $columns = Schema::getColumnListing($table);
        $payload = [];
        foreach ($row as $column => $value) {
            if (! in_array($column, $columns, true) || $column === 'id') {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $payload[$column] = $value;
        }

        return (int) DB::table($table)->insertGetId($payload);
    }

    /** @param  array<string, mixed>  $files */
    private function copyFiles(string $dir, array $files): void
    {
        foreach (array_keys($files) as $path) {
            $path = ltrim((string) $path, '/');
            if ($path === '' || str_contains($path, '..')) {
                continue;
            }
            $source = $dir.'/files/'.$path;
            if (! is_file($source)) {
                continue;
            }
            $target = storage_path('app/public/'.$path);
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            copy($source, $target);
        }
    }
}
