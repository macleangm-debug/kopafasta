<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\GuarantorInvitation;

class ApplicationTrackingShareService
{
    public function trackingUrl(LoanApplication $application): string
    {
        return route('site.borrower.application', $application->id);
    }

    public function message(LoanApplication $application): string
    {
        $application->loadMissing('product');

        return __('borrower.apply.success.tracking_share_message', [
            'ref'     => $application->application_number,
            'product' => $application->product?->name ?? __('borrower.apply.success.tracking_share_product'),
            'url'     => $this->trackingUrl($application),
        ]);
    }

    public function whatsAppShareUrl(LoanApplication $application): string
    {
        return 'https://wa.me/?text='.urlencode($this->message($application));
    }

    public function combinedMessage(LoanApplication $application, ?GuarantorInvitation $invitation, ?string $guarantorApprovalUrl): string
    {
        $parts = [$this->message($application)];

        if ($invitation && filled($guarantorApprovalUrl)) {
            $parts[] = __('borrower.apply.success.combined_guarantor_message', [
                'url' => $guarantorApprovalUrl,
            ]);
        }

        return implode("\n\n", $parts);
    }

    public function combinedWhatsAppShareUrl(
        LoanApplication $application,
        ?GuarantorInvitation $invitation = null,
        ?string $guarantorApprovalUrl = null,
    ): string {
        return 'https://wa.me/?text='.urlencode(
            $this->combinedMessage($application, $invitation, $guarantorApprovalUrl),
        );
    }
}
