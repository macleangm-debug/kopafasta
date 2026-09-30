<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\PinService;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportQuickReplyService;
use App\Services\Support\SupportTicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Realistic Support Inbox/Case UAT data. Staging only — never production.
 */
class SupportStagingUatSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! app()->environment('staging')) {
            $this->command?->warn('SupportStagingUatSeeder skipped: not a staging environment.');

            return;
        }

        app(SupportQuickReplyService::class)->save(app(SupportQuickReplyService::class)->defaults());

        $agent = User::query()->updateOrCreate(
            ['email' => 'uat.support.agent@staging.kopafasta.com'],
            [
                'name' => 'Rogathe Nyela',
                'phone' => '255700000021',
                'role' => 'agent',
                'roles' => ['agent'],
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($agent, '1234');

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'uat.support.supervisor@staging.kopafasta.com'],
            [
                'name' => 'Upendo Supervisor',
                'phone' => '255700000024',
                'role' => 'admin',
                'roles' => ['admin', 'agent'],
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($supervisor, '1234');

        $memberUser = User::query()->updateOrCreate(
            ['email' => 'uat.support.member@staging.kopafasta.com'],
            [
                'name' => 'Maclean Mwaijonga',
                'phone' => '255700000022',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($memberUser, '1234');

        $member = Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-SUP-UAT-01'],
            [
                'user_id' => $memberUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Maclean',
                'last_name' => 'Mwaijonga',
                'phone' => '255700000022',
                'country_code' => 'TZ',
                'loyalty_points' => 120,
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
                'nida_verification_status' => 'verified',
                'face_verification_status' => 'verified',
                'date_of_birth' => now()->subYears(34)->toDateString(),
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
            ]
        );

        $loanMemberUser = User::query()->updateOrCreate(
            ['email' => 'uat.support.loan@staging.kopafasta.com'],
            [
                'name' => 'Asha Loanholder',
                'phone' => '255700000023',
                'role' => 'borrower',
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($loanMemberUser, '1234');

        $loanMember = Customer::query()->updateOrCreate(
            ['customer_number' => 'CU-SUP-UAT-02'],
            [
                'user_id' => $loanMemberUser->id,
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Asha',
                'last_name' => 'Loanholder',
                'phone' => '255700000023',
                'country_code' => 'TZ',
                'loyalty_points' => 80,
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear(),
                'nida_verification_status' => 'verified',
                'face_verification_status' => 'verified',
                'date_of_birth' => now()->subYears(29)->toDateString(),
                'region' => 'Dar es Salaam',
                'district' => 'Ilala',
            ]
        );

        $product = LoanProduct::query()->where('is_active', true)->first()
            ?? LoanProduct::query()->first();

        $application = null;
        if ($product) {
            try {
                $application = LoanApplication::query()->updateOrCreate(
                    ['application_number' => 'APP-SUP-UAT-01'],
                    [
                        'customer_id' => $member->id,
                        'loan_product_id' => $product->id,
                        'requested_amount' => 2000000,
                        'requested_tenure_months' => 12,
                        'status' => 'under_review',
                        'current_stage' => 'underwriting',
                        'submitted_at' => now()->subDays(3),
                    ]
                );
            } catch (\Throwable $e) {
                $this->command?->warn('Support UAT application seed skipped: '.$e->getMessage());
            }
        }

        $loan = null;
        if ($product) {
            try {
                $loan = Loan::query()->updateOrCreate(
                    ['loan_number' => 'LN-SUP-UAT-01'],
                    [
                        'customer_id' => $loanMember->id,
                        'loan_product_id' => $product->id,
                        'principal_amount' => 1500000,
                        'approved_amount' => 1500000,
                        'outstanding_balance' => 920000,
                        'interest_rate' => 0.15,
                        'tenure_months' => 12,
                        'status' => 'active',
                    ]
                );
            } catch (\Throwable $e) {
                $this->command?->warn('Support UAT loan seed skipped: '.$e->getMessage());
            }
        }

        $conversations = app(SupportConversationService::class);
        $tickets = app(SupportTicketService::class);
        $workspace = app(\App\Services\Support\CustomerSupportWorkspaceService::class);

        // Accept / assign require Online — seed agents available before Accept.
        $workspace->setAvailability($agent, 'online');
        $workspace->setAvailability($supervisor, 'online');

        // Waiting unread — Maclean application stuck (queue timer)
        $waiting = $conversations->requestHuman(
            $member,
            $memberUser,
            'Nimeomba mkopo lakini sijui kwa nini ombi langu halijasonga. Nimekwama kwenye guarantor.',
            'Loan application',
        );
        $waiting->update([
            'needs_human' => true,
            'status' => 'waiting',
            'assigned_to' => null,
            'accepted_at' => null,
            'waiting_since' => now()->subMinutes(4)->subSeconds(12),
            'waiting_nudge_level' => 2,
            'last_message_at' => now()->subMinutes(4),
        ]);
        $waiting->messages()->where('sender_type', 'customer')->update(['read_at' => null]);
        if (! $waiting->messages()->where('body', 'like', 'Samahani kwa kusubiri%')->exists()) {
            $conversations->appendMessage($waiting, 'staff', $conversations->waitingFollowUpAcknowledgement(), null, true, false);
        }

        // Active conversation (accepted) — payment enquiry
        $mine = $conversations->openConversationFor($loanMember, $loanMemberUser);
        $conversations->appendMessage($mine, 'customer', 'Niliweka malipo jana lakini bado ninaona outstanding sawa.', $loanMemberUser->id);
        $conversations->accept($mine, $agent);
        $conversations->appendMessage($mine, 'staff', 'Asante Asha — niko kwenye malipo yako sasa.', $agent->id, false, true);
        $conversations->appendMessage($mine, 'customer', 'Asante — bado najibu? Nimeona SMS lakini saldo haijabadilika.', $loanMemberUser->id);
        $mine->update([
            'assigned_to' => $agent->id,
            'needs_human' => true,
            'status' => 'active',
            'topic' => 'Payment',
            'accepted_at' => now()->subMinutes(20),
            'waiting_since' => null,
            'last_message_at' => now()->subMinutes(12),
        ]);
        $mine->messages()->where('sender_type', 'customer')->latest('id')->limit(1)->update(['read_at' => null]);

        // Guest waiting conversation
        $guest = SupportConversation::query()->updateOrCreate(
            ['guest_phone' => '255711000099', 'status' => 'waiting'],
            [
                'channel' => 'web_chat',
                'needs_human' => true,
                'guest_name' => 'Guest Juma',
                'topic' => 'Registration help',
                'assigned_to' => null,
                'waiting_since' => now()->subMinutes(2),
                'last_message_at' => now()->subMinutes(2),
            ]
        );
        if ($guest->messages()->count() === 0) {
            $conversations->appendMessage($guest, 'guest', 'Nataka kujua jinsi ya kujiunga kabla sijajisajili.');
            $conversations->appendMessage($guest, 'staff', $conversations->waitingAcknowledgement(), null, true, false);
        }

        // Resolved + rated conversation (history)
        $rated = SupportConversation::query()->updateOrCreate(
            ['customer_id' => $member->id, 'topic' => 'Sign-in help', 'status' => 'resolved'],
            [
                'user_id' => $memberUser->id,
                'channel' => 'web_chat',
                'needs_human' => false,
                'assigned_to' => $agent->id,
                'rating' => 5,
                'rated_at' => now()->subDay(),
                'resolution_category' => 'technical_fixed',
                'last_message_at' => now()->subDays(2),
            ]
        );
        if ($rated->messages()->count() === 0) {
            $conversations->appendMessage($rated, 'customer', 'Siwezi kuingia kwenye akaunti.', $memberUser->id);
            $conversations->appendMessage($rated, 'staff', 'Tumerekebisha. Tafadhali jaribu kuingia tena.', $agent->id, true);
            $conversations->appendMessage($rated, 'staff', 'Habari Maclean, suala lako limekamilishwa. Tunatumaini tumekusaidia. Tafadhali tathmini huduma yetu kwa kuchagua nyota 1–5.', $agent->id, true);
        }

        // Ticket approaching SLA (urgent, due soon)
        $slaTicket = SupportTicket::query()->where('subject', 'Failing to progress application')->latest('id')->first();
        if (! $slaTicket) {
            $slaTicket = $tickets->create([
                'customer_id' => $member->id,
                'support_conversation_id' => $waiting->id,
                'related_type' => $application ? 'application' : 'account',
                'related_id' => $application?->id ?? $member->id,
                'subject' => 'Failing to progress application',
                'category' => 'loan_application',
                'priority' => 'urgent',
                'description' => 'Member stuck after guarantor step — needs investigation ticket.',
                'source' => 'chatbot',
                'assigned_to' => $agent->id,
                'status' => 'open',
                'actor' => $agent,
            ]);
        }
        $slaTicket->update([
            'sla_due_at' => now()->addMinutes(38),
            'assigned_at' => now()->subHour(),
            'priority' => 'urgent',
            'status' => 'open',
            'support_conversation_id' => $waiting->id,
        ]);

        // Escalated ticket
        $escalated = SupportTicket::query()->where('subject', 'Payment not reflecting')->latest('id')->first();
        if (! $escalated) {
            $escalated = $tickets->create([
                'customer_id' => $loanMember->id,
                'support_conversation_id' => $mine->id,
                'related_type' => $loan ? 'loan' : 'account',
                'related_id' => $loan?->id ?? $loanMember->id,
                'subject' => 'Payment not reflecting',
                'category' => 'payments',
                'priority' => 'high',
                'description' => 'Repayment SMS received but balance unchanged.',
                'source' => 'chatbot',
                'assigned_to' => $agent->id,
                'status' => 'in_progress',
                'actor' => $agent,
            ]);
            $tickets->escalate(
                $escalated,
                'credit',
                'Payment appears stuck after mobile money confirmation',
                $agent,
                'Please check ledger vs PSP reference.',
                false,
            );
        }

        // Resolved + rated ticket
        $resolved = SupportTicket::query()->where('subject', 'Failing to sign in')->latest('id')->first();
        if (! $resolved) {
            $resolved = $tickets->create([
                'customer_id' => $member->id,
                'support_conversation_id' => $rated->id,
                'related_type' => 'account',
                'related_id' => $member->id,
                'subject' => 'Failing to sign in',
                'category' => 'account_access',
                'priority' => 'normal',
                'description' => 'Login issue fixed.',
                'source' => 'chatbot',
                'assigned_to' => $agent->id,
                'status' => 'open',
                'actor' => $agent,
            ]);
            $tickets->resolveCase($resolved, [
                'resolution_type' => 'technical_fixed',
                'resolution_notes' => 'Password/PIN reset guidance provided.',
                'invite_rating' => false,
            ], $agent);
            $tickets->recordRating($resolved->fresh(), 5, 'Huduma nzuri sana');
        }

        // Resolved but unrated (rating optional after close)
        $unrated = SupportTicket::query()->where('subject', 'Guarantor acceptance not progressing')->latest('id')->first();
        if (! $unrated) {
            $unrated = $tickets->create([
                'customer_id' => $member->id,
                'related_type' => $application ? 'application' : 'account',
                'related_id' => $application?->id ?? $member->id,
                'subject' => 'Guarantor acceptance not progressing',
                'category' => 'loan_application',
                'priority' => 'normal',
                'description' => 'Similar-ticket sample. Resolved without rating yet.',
                'source' => 'chatbot',
                'assigned_to' => $agent->id,
                'status' => 'open',
                'actor' => $agent,
            ]);
            $tickets->resolveCase($unrated, [
                'resolution_type' => 'information_provided',
                'resolution_notes' => 'Resent guarantor invitation; borrower replaced wrong phone.',
                'invite_rating' => true,
            ], $agent);
        }

        // Overdue ticket for dashboard red flags
        $overdue = SupportTicket::query()->where('subject', 'PIN reset not arriving')->latest('id')->first();
        if (! $overdue) {
            $overdue = $tickets->create([
                'customer_id' => $loanMember->id,
                'related_type' => 'account',
                'related_id' => $loanMember->id,
                'subject' => 'PIN reset not arriving',
                'category' => 'account_access',
                'priority' => 'high',
                'description' => 'Overdue sample for Support supervisor UAT.',
                'source' => 'chatbot',
                'assigned_to' => $agent->id,
                'status' => 'open',
                'actor' => $agent,
            ]);
        }
        $overdue->update([
            'sla_due_at' => now()->subHours(3),
            'assigned_at' => now()->subHours(6),
            'priority' => 'high',
            'status' => 'in_progress',
            'category' => 'account_access',
        ]);

        // Second similar payment ticket for Create Ticket assistance
        $similarPay = SupportTicket::query()->where('subject', 'Payment pending after successful payment')->latest('id')->first();
        if (! $similarPay) {
            $similarPay = $tickets->create([
                'customer_id' => $loanMember->id,
                'related_type' => $loan ? 'loan' : 'account',
                'related_id' => $loan?->id ?? $loanMember->id,
                'subject' => 'Payment pending after successful payment',
                'category' => 'payments',
                'priority' => 'normal',
                'description' => 'Similar-ticket counterpart for Payment not reflecting.',
                'source' => 'chatbot',
                'assigned_to' => $agent->id,
                'status' => 'open',
                'actor' => $agent,
            ]);
            $tickets->resolveCase($similarPay, [
                'resolution_type' => 'technical_fixed',
                'resolution_notes' => 'Ledger sync caught up; confirmed payment against PSP reference.',
                'invite_rating' => false,
            ], $agent);
        }

        Setting::set('support.uat_credentials', [
            'member_email' => 'uat.support.member@staging.kopafasta.com',
            'member_phone' => '255700000022',
            'member_pin' => '1234',
            'member_password' => 'StagingUat!2026',
            'loan_member_email' => 'uat.support.loan@staging.kopafasta.com',
            'loan_member_phone' => '255700000023',
            'loan_member_pin' => '1234',
            'agent_email' => 'uat.support.agent@staging.kopafasta.com',
            'agent_name' => 'Rogathe Nyela',
            'agent_pin' => '1234',
            'agent_password' => 'StagingUat!2026',
            'supervisor_email' => 'uat.support.supervisor@staging.kopafasta.com',
            'supervisor_name' => 'Upendo Supervisor',
            'supervisor_password' => 'StagingUat!2026',
            'admin_email' => 'uat.admin@staging.kopafasta.com',
            'waiting_conversation_id' => $waiting->id,
            'active_conversation_id' => $mine->id,
            'sla_ticket' => $slaTicket->fresh()->ticket_number,
            'overdue_ticket' => $overdue->fresh()->ticket_number,
            'escalated_ticket' => $escalated->fresh()->ticket_number,
            'resolved_rated_ticket' => $resolved->fresh()->ticket_number,
            'resolved_unrated_ticket' => $unrated->fresh()->ticket_number,
            'resolved_rated_conversation_id' => $rated->id,
            'notes' => 'Help Center first. Waiting → Accept only. Create ticket only for follow-up. Rating optional after resolve.',
        ]);

        $this->command?->info('Support Pass 3 UAT seeded.');
        $this->command?->info('Member: uat.support.member@staging.kopafasta.com / StagingUat!2026 (PIN 1234)');
        $this->command?->info('Agent: uat.support.agent@staging.kopafasta.com · Supervisor: uat.support.supervisor@staging.kopafasta.com');
        $this->command?->info('Waiting #'.$waiting->id.' · Active #'.$mine->id);
        $this->command?->info('SLA '.$slaTicket->fresh()->ticket_number.' · Overdue '.$overdue->fresh()->ticket_number);
        $this->command?->info('Resolved rated '.$resolved->fresh()->ticket_number.' · Unrated '.$unrated->fresh()->ticket_number);
    }
}
