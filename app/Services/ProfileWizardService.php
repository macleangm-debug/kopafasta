<?php

namespace App\Services;

use App\Models\Customer;

class ProfileWizardService
{
    /** @return list<array{key: string, label: string, complete: bool, url: string}> */
    public function steps(Customer $customer): array
    {
        $profile = app(ProfileCompletionService::class);
        $nida = app(NidaVerificationService::class);
        $validation = app(ProfileValidationService::class);
        $sections = collect($profile->calculate($customer)['sections'] ?? [])->keyBy('key');

        $nidaComplete = $nida->isVerified($customer)
            && $validation->nationalIdUploadsComplete($customer);
        $faceComplete = app(FaceVerificationService::class)->profileStepComplete($customer);
        $residenceComplete = (bool) ($sections['residence']['complete'] ?? false)
            && (! $validation->requiresResidenceLetter() || $validation->hasResidenceLetter($customer));
        $documentsComplete = $profile->isDocumentsComplete($customer);

        $steps = [
            [
                'key'      => 'nida',
                'label'    => __('borrower.kyc_progress.nida'),
                'complete' => $nidaComplete,
                'url'      => route('site.borrower.profile', ['section' => 'personal', 'wizard' => 1]),
            ],
            [
                'key'      => 'face',
                'label'    => __('borrower.kyc_progress.face'),
                'complete' => $faceComplete,
                'url'      => route('site.borrower.face-verification', ['wizard' => 1]),
            ],
            [
                'key'      => 'residence',
                'label'    => __('borrower.profile.residence'),
                'complete' => $residenceComplete,
                'url'      => route('site.borrower.profile', ['section' => 'residence', 'wizard' => 1]),
            ],
            [
                'key'      => 'activity',
                'label'    => __('borrower.profile.activity'),
                'complete' => (bool) ($sections['activity']['complete'] ?? false),
                'url'      => route('site.borrower.profile', ['section' => 'activity', 'wizard' => 1]),
            ],
            [
                'key'      => 'documents',
                'label'    => __('borrower.profile.documents_proof'),
                'complete' => $documentsComplete,
                'url'      => route('site.borrower.profile', ['section' => 'kyc', 'wizard' => 1]),
            ],
            [
                'key'      => 'kin',
                'label'    => __('borrower.profile.kin'),
                'complete' => $validation->isKinComplete($customer),
                'url'      => route('site.borrower.profile', ['section' => 'personal', 'wizard' => 1, 'focus' => 'kin']).'#next-of-kin',
            ],
        ];

        if (app(ProfileSectionBuilderService::class)->paymentRequiredBeforeLoan()) {
            $steps[] = [
                'key'      => 'payment',
                'label'    => __('borrower.payment_details.section_title'),
                'complete' => app(CustomerDisbursementDetailsService::class)->isComplete($customer),
                'url'      => route('site.borrower.profile', ['section' => 'payment', 'wizard' => 1, 'add' => 1]),
            ];
        }

        return $steps;
    }

    public function isComplete(Customer $customer): bool
    {
        return collect($this->steps($customer))->every(fn (array $step) => $step['complete']);
    }

    public function firstIncompleteUrl(Customer $customer): ?string
    {
        $step = collect($this->steps($customer))->first(fn (array $step) => ! $step['complete']);

        return $step['url'] ?? null;
    }

    public function progress(Customer $customer): array
    {
        $steps = $this->steps($customer);
        $completed = collect($steps)->where('complete', true)->count();
        $total = max(1, count($steps));

        return [
            'percent'   => (int) round(($completed / $total) * 100),
            'completed' => $completed,
            'total'     => count($steps),
            'steps'     => $steps,
        ];
    }

    public function navigation(Customer $customer, string $currentKey): array
    {
        $steps = $this->steps($customer);
        $index = collect($steps)->search(fn (array $step) => $step['key'] === $currentKey);
        $index = $index === false ? 0 : (int) $index;

        return [
            'current'  => $steps[$index] ?? null,
            'index'    => $index,
            'total'    => count($steps),
            'previous' => $index > 0 ? $steps[$index - 1] : null,
            'next'     => $this->nextIncompleteFrom($customer, $index),
            'progress' => $this->progress($customer),
        ];
    }

    /** @return array{key: string, label: string, complete: bool, url: string}|null */
    private function nextIncompleteFrom(Customer $customer, int $afterIndex): ?array
    {
        $steps = $this->steps($customer);

        foreach (array_slice($steps, $afterIndex + 1) as $step) {
            if (! $step['complete']) {
                return $step;
            }
        }

        foreach (array_slice($steps, 0, $afterIndex + 1) as $step) {
            if (! $step['complete']) {
                return $step;
            }
        }

        return null;
    }

    public function resumeUrl(Customer $customer): string
    {
        return $this->firstIncompleteUrl($customer)
            ?? route('site.borrower.profile', ['section' => 'personal', 'wizard' => 1]);
    }
}
