<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Services\GroupMemberOnboardingService;
use App\Services\GuarantorInvitationService;
use Illuminate\Support\Collection;

/**
 * Canonical borrower in-app inbox for bell dropdown + Notifications page.
 * Ensures invitation rows exist so Bell and full page cannot disagree.
 */
class NotificationInboxService
{
    public function __construct(
        private readonly PortalContextService $portal,
        private readonly NotificationCtaService $ctas,
        private readonly NotificationCenterService $center,
    ) {}

    /**
     * Ensure pending guarantor / group invitations have an in-app notification row.
     * Idempotent — never duplicates unread invitation rows for the same link/invite.
     */
    public function ensureInvitationNotifications(Customer $customer): void
    {
        $guarantorService = app(GuarantorInvitationService::class);

        foreach ($this->portal->pendingGuarantorLinks($customer) as $row) {
            $link = $row->link ?? null;
            $invitation = $row->invitation ?? null;
            $borrower = $row->borrower ?? null;
            if (! $link || ! $invitation || ! $borrower) {
                continue;
            }

            $exists = NotificationLog::query()
                ->where('customer_id', $customer->id)
                ->where('channel', 'in_app')
                ->where('template', 'guarantor_request')
                ->where(function ($q) use ($link) {
                    $q->where('meta->customer_guarantor_id', $link->id)
                        ->orWhere('recipient', 'like', '%/guarantor-requests/'.$link->id.'%')
                        ->orWhere('recipient', 'like', '%tab=guarantor%');
                })
                ->exists();

            if ($exists) {
                continue;
            }

            $guarantorService->notifyInternalGuarantorRequest(
                $borrower,
                $customer,
                $link,
                $invitation,
                $row->application ?? $invitation->application,
            );
        }

        // Group member invitations — ensure in-app row exists for pending invite.
        $group = app(GroupMemberOnboardingService::class);
        $invite = $group->pendingInvitationForCustomer($customer);
        if ($invite) {
            $group->notifyLinkedInvitation($invite, $customer);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewItems(Customer $customer, int $limit = 12): array
    {
        $this->ensureInvitationNotifications($customer);

        $base = $this->portal->borrowerNotificationsQuery($customer);
        $inviteTemplates = ['guarantor_request', 'group_loan_invitation'];

        $pinned = (clone $base)->whereIn('template', $inviteTemplates)->latest()->limit(8)->get();
        $recent = (clone $base)->latest()->limit($limit)->get();

        return $pinned->concat($recent)
            ->unique(fn (NotificationLog $n) => (int) $n->id)
            ->take($limit)
            ->values()
            ->map(fn (NotificationLog $n) => $this->serialize($n))
            ->all();
    }

    /**
     * @return Collection<int, NotificationLog>
     */
    public function allForPage(Customer $customer): Collection
    {
        $this->ensureInvitationNotifications($customer);

        return $this->portal->borrowerNotificationsQuery($customer)->latest()->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(NotificationLog $n): array
    {
        $category = $this->center->normalizeCategory((string) ($n->category ?: 'general'));
        $ctas = $this->ctas->resolve($n);

        return [
            'id' => $n->id,
            'title' => $n->displayTitle(),
            'body' => $n->displayBody(),
            'message' => trim($n->displayTitle().' '.$n->displayBody()),
            'category' => $category,
            'category_label' => $this->center->categoryLabel($category),
            'template' => $n->template,
            'read' => (bool) $n->read_at,
            'when' => $n->created_at?->diffForHumans(),
            'action_url' => $ctas['action_url'],
            'action_label' => $ctas['action_label'],
            'accept_url' => $ctas['accept_url'],
            'decline_url' => $ctas['decline_url'],
            'decline_label' => $ctas['decline_label'],
        ];
    }
}
