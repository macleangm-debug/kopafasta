<?php

namespace App\Services;

use App\Models\CustomerGuarantor;
use App\Models\LoanApplicationDocumentRequest;
use App\Models\NotificationLog;

class NotificationCtaService
{
    /**
     * @return array{
     *     accept_url: ?string,
     *     decline_url: ?string,
     *     action_url: ?string,
     *     action_label: ?string,
     *     decline_label: ?string
     * }
     */
    public function resolve(NotificationLog $notification): array
    {
        $empty = [
            'accept_url'    => null,
            'decline_url'   => null,
            'action_url'    => null,
            'action_label'  => null,
            'decline_label' => null,
        ];

        $meta = is_array($notification->meta) ? $notification->meta : [];
        if (! empty($meta['cta_consumed_at'])) {
            return $empty;
        }

        $actionUrl = ($notification->channel === 'in_app'
            && filled($notification->recipient)
            && str_starts_with((string) $notification->recipient, '/'))
            ? (string) $notification->recipient
            : null;

        $template = (string) ($notification->template ?? '');

        if ($template === 'guarantor_request') {
            $linkId = $this->guarantorLinkId($notification, $actionUrl);
            if ($linkId <= 0 || ! $this->guarantorLinkIsPending($linkId)) {
                return $empty;
            }

            return [
                'accept_url'    => route('site.borrower.guarantor-requests.show', $linkId),
                'decline_url'   => route('site.borrower.guarantor-requests.respond', $linkId),
                'action_url'    => null,
                'action_label'  => __('borrower.guarantor_notifications.accept_cta'),
                'decline_label' => __('borrower.guarantor_notifications.decline_cta'),
            ];
        }

        if (in_array($template, ['document_request', 'document_requests', 'application_document_request', 'profile_revision_requested'], true)) {
            if (! $this->documentActionStillOpen($notification, $meta, $actionUrl)) {
                return $empty;
            }
        }

        if (! $actionUrl) {
            return $empty;
        }

        $goUrl = route('site.borrower.notifications.go', $notification);

        $actionLabel = match ($template) {
            'guarantor_loan_arrears' => __('borrower.guarantor_notifications.view_loan'),
            'guarantor_supplement_request' => __('borrower.guarantor_supplement.cta'),
            'loyalty_points_earned' => __('borrower.rewards.points_earned_cta'),
            'document_request', 'document_requests', 'application_document_request' => __('borrower.dashboard.document_requests_cta'),
            'profile_revision_requested' => __('borrower.notifications.profile_revision_cta'),
            default => __('borrower.notifications.view_application'),
        };

        return [
            'accept_url'    => null,
            'decline_url'   => null,
            'action_url'    => $goUrl,
            'action_label'  => $actionLabel,
            'decline_label' => null,
        ];
    }

    public function consume(NotificationLog $notification): void
    {
        $meta = is_array($notification->meta) ? $notification->meta : [];
        if (! empty($meta['cta_consumed_at'])) {
            return;
        }

        $meta['cta_consumed_at'] = now()->toIso8601String();
        $notification->update([
            'meta'    => $meta,
            'read_at' => $notification->read_at ?? now(),
        ]);
    }

    public function consumeGuarantorRequestCtas(CustomerGuarantor $link): void
    {
        NotificationLog::query()
            ->where('template', 'guarantor_request')
            ->where(function ($q) use ($link) {
                $q->where('meta->customer_guarantor_id', $link->id)
                    ->orWhere('recipient', 'like', '%/guarantor-requests/'.$link->id.'%');
            })
            ->orderBy('id')
            ->each(fn (NotificationLog $n) => $this->consume($n));
    }

    public function consumeDocumentRequestCtas(int $applicationId, ?int $requestId = null): void
    {
        $query = NotificationLog::query()
            ->whereIn('template', ['document_request', 'document_requests', 'application_document_request']);

        $query->where(function ($q) use ($applicationId, $requestId) {
            $q->where('meta->loan_application_id', $applicationId)
                ->orWhere('recipient', 'like', '%/loan-profile/'.$applicationId.'%')
                ->orWhere('recipient', 'like', '%/applications/'.$applicationId.'%');
            if ($requestId) {
                $q->orWhere('meta->loan_application_document_request_id', $requestId);
            }
        });

        // Only consume when no open borrower actions remain on the application.
        $stillOpen = LoanApplicationDocumentRequest::query()
            ->where('loan_application_id', $applicationId)
            ->whereIn('status', ['pending', 'rejected'])
            ->exists();

        if ($stillOpen) {
            return;
        }

        $query->orderBy('id')->each(fn (NotificationLog $n) => $this->consume($n));
    }

    private function guarantorLinkId(NotificationLog $notification, ?string $actionUrl): int
    {
        $meta = is_array($notification->meta) ? $notification->meta : [];
        $linkId = (int) ($meta['customer_guarantor_id'] ?? 0);
        if ($linkId <= 0 && $actionUrl && preg_match('#/guarantor-requests/(\d+)#', $actionUrl, $m)) {
            $linkId = (int) $m[1];
        }

        return $linkId;
    }

    private function guarantorLinkIsPending(int $linkId): bool
    {
        return CustomerGuarantor::query()
            ->where('id', $linkId)
            ->where('status', 'pending')
            ->exists();
    }

    private function documentActionStillOpen(NotificationLog $notification, array $meta, ?string $actionUrl): bool
    {
        $requestId = (int) ($meta['loan_application_document_request_id'] ?? 0);
        if ($requestId > 0) {
            return LoanApplicationDocumentRequest::query()
                ->where('id', $requestId)
                ->whereIn('status', ['pending', 'rejected'])
                ->exists();
        }

        $applicationId = (int) ($meta['loan_application_id'] ?? 0);
        if ($applicationId <= 0 && $actionUrl && preg_match('#/(?:loan-profile|applications)/(\d+)#', $actionUrl, $m)) {
            $applicationId = (int) $m[1];
        }

        if ($applicationId <= 0) {
            return true;
        }

        return LoanApplicationDocumentRequest::query()
            ->where('loan_application_id', $applicationId)
            ->whereIn('status', ['pending', 'rejected'])
            ->exists();
    }
}
