<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerPayment;
use App\Models\LoanApplication;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\VendorTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collateral insurance cover via insurance partners.
 * Premium = insured value × (cover rate% + platform markup%).
 * Partner wallet share = insured value × cover rate% only.
 */
class CollateralInsurancePartnerService
{
    public const TASK_TYPE = 'vehicle_insurance';

    public function ratePercent(?\App\Models\Partner $partner = null): float
    {
        return app(PartnerDefaultsService::class)->insuranceRatePercent($partner);
    }

    public function markupPercent(?\App\Models\Partner $partner = null): float
    {
        return app(PartnerDefaultsService::class)->insuranceMarkupPercent($partner);
    }

    /**
     * @return array{
     *   insured_value: int,
     *   rate_percent: float,
     *   markup_percent: float,
     *   effective_rate_percent: float,
     *   base_premium: int,
     *   markup_amount: int,
     *   premium: int
     * }
     */
    public function quote(int $insuredValue, ?\App\Models\Partner $partner = null): array
    {
        $insuredValue = max(0, $insuredValue);
        $rate = $this->ratePercent($partner);
        $markupPct = $this->markupPercent($partner);
        // Both percents apply to insured value (additive rates), not markup-on-base.
        $base = (int) round($insuredValue * ($rate / 100));
        $markup = (int) round($insuredValue * ($markupPct / 100));

        return [
            'insured_value' => $insuredValue,
            'rate_percent' => $rate,
            'markup_percent' => $markupPct,
            'effective_rate_percent' => $rate + $markupPct,
            'base_premium' => $base,
            'markup_amount' => $markup,
            'premium' => $base + $markup,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Vendor> */
    public function insurersForRegion(?string $region)
    {
        $query = Vendor::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('category', 'insurance')->orWhere('roles', 'like', '%"insurance"%');
            });

        if (blank($region)) {
            return $query->orderBy('name')->get();
        }

        $matches = $query->get()->filter(function (Vendor $vendor) use ($region): bool {
            if (($vendor->coverage_type ?? 'regions') === 'nationwide') {
                return true;
            }

            $regions = $vendor->regions ?? [];

            return $regions === [] || in_array($region, $regions, true);
        });

        if ($matches->isNotEmpty()) {
            return $matches->sortBy('name')->values();
        }

        return $query->orderBy('name')->get();
    }

    public function suggestInsurer(LoanApplication $application): ?Vendor
    {
        $application->loadMissing('customer');

        return $this->insurersForRegion($application->customer?->region)->first();
    }

    /**
     * Open an insurance partner case for a specific customer asset after premium is collected.
     *
     * @return array{task: ?VendorTask, quote: array<string, mixed>, partner: ?Vendor}
     */
    public function openCoverCase(
        LoanApplication $application,
        CustomerAsset $asset,
        int $insuredValue,
        Customer $payer,
        ?CustomerPayment $payment = null,
    ): array {
        abort_unless((int) $asset->customer_id === (int) $payer->id
            || (int) $application->customer_id === (int) $payer->id, 403);

        $partner = $this->suggestInsurer($application);
        $quote = $this->quote($insuredValue, $partner);
        if ($quote['premium'] <= 0) {
            throw ValidationException::withMessages([
                'insured_value' => __('borrower.collateral_secure.insurance_value_required'),
            ]);
        }

        return DB::transaction(function () use ($application, $asset, $quote, $partner, $payer, $payment) {
            $customer = $asset->customer ?? $application->customer;
            $profile = $this->assetProfilePayload($asset);
            $details = collect([
                $asset->label,
                $asset->registration_number,
                $asset->detail('make'),
                $asset->detail('model'),
                $asset->detail('year') ? 'Year '.$asset->detail('year') : null,
                $asset->detail('chassis_number') ? 'Chassis '.$asset->detail('chassis_number') : null,
                $asset->detail('colour'),
            ])->filter()->implode(' · ');

            $instructions = implode("\n", array_filter([
                'Issue comprehensive cover for this collateral asset within 1–2 days.',
                'Insured value: '.format_money($quote['insured_value']),
                'Your payout on completion: '.format_money($quote['base_premium'])
                    .' ('.rtrim(rtrim(number_format($quote['rate_percent'], 2), '0'), '.').'% of insured value)'
                    .($quote['markup_amount'] > 0
                        ? ' — borrower paid '.format_money($quote['premium'])
                            .' at '.rtrim(rtrim(number_format($quote['effective_rate_percent'], 2), '0'), '.').'% incl. platform markup'
                        : ''),
                $payment?->reference ? 'Payment reference: '.$payment->reference : null,
                'Full asset profile is included below — enter policy number and expiry to update the owner’s asset automatically.',
            ]));

            $task = null;
            if ($partner) {
                $partnerShare = (int) $quote['base_premium'];
                $task = VendorTask::create([
                    'vendor_id' => $partner->id,
                    'loan_id' => $application->loan_id,
                    'loan_application_id' => $application->id,
                    'task_type' => self::TASK_TYPE,
                    'status' => 'assigned',
                    'due_at' => now()->addDays(max(1, app(UnderwritingSettingsService::class)->insuranceRenewalDecisionDays())),
                    'customer_name' => $customer?->legalDisplayName() ?? trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
                    'customer_phone' => $customer?->phone,
                    'vehicle_details' => $details ?: $asset->label,
                    'location' => trim(collect([
                        $customer?->street,
                        $customer?->district,
                        $customer?->region,
                    ])->filter()->implode(', ')) ?: null,
                    'instructions' => $instructions,
                    'notes' => json_encode([
                        'customer_asset_id' => $asset->id,
                        'insured_value' => $quote['insured_value'],
                        'premium' => $quote['premium'],
                        'base_premium' => $quote['base_premium'],
                        'markup_amount' => $quote['markup_amount'],
                        'rate_percent' => $quote['rate_percent'],
                        'markup_percent' => $quote['markup_percent'],
                        'effective_rate_percent' => $quote['effective_rate_percent'],
                        'partner_share' => $partnerShare,
                        'payer_customer_id' => $payer->id,
                        'payment_id' => $payment?->id,
                        'payment_reference' => $payment?->reference,
                        'insurance_type' => 'comprehensive',
                        'asset_profile' => $profile,
                        'wallet_accrued_at' => null,
                    ]),
                    // Partner wallet share (base premium) — credited when cover is recorded.
                    'fee_amount' => $partnerShare,
                ]);

                try {
                    app(NotificationService::class)->notifyPartner(
                        $partner,
                        'partner_cover_job_assigned',
                        [
                            'partner' => $partner->name,
                            'customer' => $task->customer_name,
                            'asset' => $asset->label,
                            'premium' => format_money($partnerShare),
                        ],
                        route('site.partner.task', $task),
                    );
                } catch (\Throwable) {
                    // Non-blocking: cover job remains assigned even if notice fails.
                }
            }

            return [
                'task' => $task,
                'quote' => $quote,
                'partner' => $partner,
            ];
        });
    }

    /**
     * After PayIn / verification confirms insurance_premium, open partner case and update collateral state.
     */
    public function fulfillPremiumPayment(\App\Models\CustomerPayment $payment): void
    {
        if ($payment->payment_type !== 'insurance_premium' || ! $payment->isVerified()) {
            return;
        }

        $meta = (array) ($payment->provider_meta ?? []);
        $ctx = (array) ($meta['collateral_insurance'] ?? []);
        if (($ctx['fulfilled_at'] ?? null)) {
            return;
        }

        $applicationId = (int) ($ctx['loan_application_id'] ?? $payment->source_id ?? 0);
        $assetId = (int) ($ctx['customer_asset_id'] ?? 0);
        $insuredValue = (int) ($ctx['insured_value'] ?? 0);
        $payerId = (int) ($ctx['payer_customer_id'] ?? $payment->customer_id ?? 0);

        $application = LoanApplication::query()->find($applicationId);
        $asset = CustomerAsset::query()->find($assetId);
        $payer = Customer::query()->find($payerId);
        if (! $application || ! $asset || ! $payer || $insuredValue <= 0) {
            return;
        }

        $opened = $this->openCoverCase($application, $asset, $insuredValue, $payer, $payment);
        $quote = $opened['quote'];

        app(CollateralSecureService::class)->recordInsurancePurchase($application, [
            'insured_value' => $quote['insured_value'],
            'premium' => $quote['premium'],
            'rate_percent' => $quote['rate_percent'],
            'markup_percent' => $quote['markup_percent'],
            'partner_task_id' => $opened['task']?->id,
            'partner_id' => $opened['partner']?->id,
            'payment_id' => $payment->id,
            'payment_reference' => $payment->reference,
            'paid_at' => now()->toIso8601String(),
        ]);

        $meta['collateral_insurance'] = array_merge($ctx, [
            'fulfilled_at' => now()->toIso8601String(),
            'partner_task_id' => $opened['task']?->id,
            'partner_id' => $opened['partner']?->id,
        ]);
        $payment->update(['provider_meta' => $meta]);
    }

    /** @return array<string, mixed> */
    public function assetProfilePayload(CustomerAsset $asset): array
    {
        $typeOptions = CustomerAsset::typeOptions();
        $photos = collect($asset->galleryPaths())
            ->filter()
            ->map(fn ($path) => asset('storage/'.$path))
            ->values()
            ->all();

        $meta = $asset->metadata ?? [];
        $ownershipPath = $meta['ownership_document_path'] ?? null;
        $insuranceDocPath = $meta['insurance_document_path'] ?? null;
        $personPath = $meta['person_with_asset_path'] ?? null;

        $detailFields = CustomerAsset::detailFieldsFor((string) $asset->asset_type);
        $details = $asset->details();
        $labeledDetails = [];
        foreach ($detailFields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $value = ! empty($field['column']) && $key === 'registration_number'
                ? $asset->registration_number
                : ($details[$key] ?? null);
            if (! filled($value)) {
                continue;
            }
            $labeledDetails[] = [
                'key' => $key,
                'label' => __('borrower.profile.collateral_fields.'.$key),
                'value' => is_scalar($value) ? $value : json_encode($value),
            ];
        }
        // Include any extra detail keys not in the schema (e.g. insurance fields).
        foreach ($details as $key => $value) {
            if (! filled($value) || collect($labeledDetails)->contains('key', $key)) {
                continue;
            }
            $labelKey = 'borrower.profile.collateral_fields.'.$key;
            $labeledDetails[] = [
                'key' => (string) $key,
                'label' => \Illuminate\Support\Facades\Lang::has($labelKey)
                    ? __($labelKey)
                    : str_replace('_', ' ', ucfirst((string) $key)),
                'value' => is_scalar($value) ? $value : json_encode($value),
            ];
        }

        return [
            'id' => $asset->id,
            'label' => $asset->label,
            'description' => $asset->description,
            'asset_type' => $asset->asset_type,
            'type_label' => $typeOptions[$asset->asset_type] ?? $asset->asset_type,
            'registration_number' => $asset->registration_number,
            'estimated_value' => $asset->estimated_value,
            'details' => $details,
            'labeled_details' => $labeledDetails,
            'insurance_type' => $asset->insuranceType(),
            'insurance_policy_number' => $asset->detail('insurance_policy_number'),
            'insurance_expires_at' => $asset->detail('insurance_expires_at'),
            'thumbnail' => $asset->thumbnailPath() ? asset('storage/'.$asset->thumbnailPath()) : null,
            'photos' => $photos,
            'person_with_asset_url' => filled($personPath) ? asset('storage/'.$personPath) : null,
            'ownership_document_url' => filled($ownershipPath) ? asset('storage/'.$ownershipPath) : null,
            'insurance_document_url' => filled($insuranceDocPath) ? asset('storage/'.$insuranceDocPath) : null,
            'owner_customer_id' => $asset->customer_id,
        ];
    }

    /**
     * Partner (or ops) confirms cover — writes expiry onto the specific CustomerAsset
     * and accrues the partner’s base premium to their wallet (markup stays with the platform).
     */
    public function completeCover(
        VendorTask $task,
        string $expiresAt,
        ?string $policyNumber = null,
        string $insuranceType = 'comprehensive',
    ): CustomerAsset {
        abort_unless($task->task_type === self::TASK_TYPE, 422);

        $meta = [];
        if (is_string($task->notes)) {
            $decoded = json_decode($task->notes, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        $assetId = (int) ($meta['customer_asset_id'] ?? 0);
        $asset = CustomerAsset::query()->findOrFail($assetId);

        $details = $asset->details();
        // Insure It cover jobs are always comprehensive — never third party.
        $details['insurance_type'] = 'comprehensive';
        $details['insurance_expires_at'] = \Carbon\Carbon::parse($expiresAt)->toDateString();
        if (filled($policyNumber)) {
            $details['insurance_policy_number'] = $policyNumber;
        }

        $assetMeta = $asset->metadata ?? [];
        $assetMeta['details'] = $details;
        $asset->update(['metadata' => $assetMeta]);

        $partnerShare = (int) ($meta['partner_share'] ?? $meta['base_premium'] ?? 0);
        if ($partnerShare <= 0 && filled($meta['insured_value'] ?? null)) {
            $partnerShare = (int) $this->quote((int) $meta['insured_value'])['base_premium'];
        }
        if ($partnerShare <= 0) {
            $partnerShare = (int) ($task->fee_amount ?? 0);
        }

        $walletAccruedAt = $meta['wallet_accrued_at'] ?? null;
        if (! filled($walletAccruedAt) && $partnerShare > 0 && $task->vendor_id) {
            $already = VendorPayment::query()
                ->where('partner_task_id', $task->id)
                ->where('source_type', 'insurance_premium')
                ->where('status', '!=', 'cancelled')
                ->exists();

            if (! $already) {
                $partner = Vendor::query()->find($task->vendor_id);
                if ($partner) {
                    app(PartnerSettlementService::class)->accrue(
                        $partner,
                        $partnerShare,
                        'insurance_premium',
                        isset($meta['payment_id']) ? (int) $meta['payment_id'] : null,
                        'Collateral insurance cover '.($meta['payment_reference'] ?? ''),
                        $task->id,
                    );
                }
            }
            $walletAccruedAt = now()->toIso8601String();
        }

        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'fee_amount' => $partnerShare > 0 ? $partnerShare : $task->fee_amount,
            'notes' => json_encode(array_merge($meta, [
                'insurance_expires_at' => $details['insurance_expires_at'],
                'insurance_policy_number' => $details['insurance_policy_number'] ?? null,
                'insurance_type' => $details['insurance_type'],
                'partner_share' => $partnerShare,
                'wallet_accrued_at' => $walletAccruedAt,
                'completed_at' => now()->toIso8601String(),
            ])),
        ]);

        if ($task->loan_application_id) {
            $application = LoanApplication::query()->find($task->loan_application_id);
            if ($application) {
                app(CollateralSecureService::class)->recheckAfterInsuranceUpdate($application, $asset->fresh());
            }
        }

        return $asset->fresh();
    }
}
