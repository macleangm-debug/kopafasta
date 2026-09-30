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
                'name' => 'UAT Support Agent',
                'phone' => '255700000021',
                'role' => 'agent',
                'roles' => ['agent'],
                'is_active' => true,
                'password' => Hash::make('StagingUat!2026'),
                'email_verified_at' => now(),
            ]
        );
        app(PinService::class)->setPin($agent, '1234');

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

        // Waiting unread — Maclean application stuck
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
            'last_message_at' => now()->subMinutes(3),
        ]);
        $waiting->messages()->where('sender_type', 'customer')->update(['read_at' => null]);

        // Mine / assigned with unread reply waiting
        $mine = $conversations->openConversationFor($loanMember, $loanMemberUser);
        $conversations->appendMessage($mine, 'customer', 'Niliweka malipo jana lakini bado ninaona outstanding sawa.', $loanMemberUser->id);
        $conversations->appendMessage($mine, 'staff', 'Tumepokea. Tunachunguza malipo yako sasa.', $agent->id);
        $conversations->appendMessage($mine, 'customer', 'Asante — bado najibu? Nimeona SMS lakini saldo haijabadilika.', $loanMemberUser->id);
        $mine->update([
            'assigned_to' => $agent->id,
            'needs_human' => true,
            'status' => 'assigned',
            'topic' => 'Payment',
            'last_message_at' => now()->subMinutes(12),
        ]);
        $mine->messages()->where('sender_type', 'customer')->latest('id')->limit(1)->update(['read_at' => null]);

        // Guest conversation
        $guest = SupportConversation::query()->updateOrCreate(
            ['guest_phone' => '255711000099', 'status' => 'open'],
            [
                'channel' => 'web_chat',
                'needs_human' => true,
                'guest_name' => 'Guest Juma',
                'topic' => 'Registration help',
                'last_message_at' => now()->subMinutes(8),
            ]
        );
        if ($guest->messages()->count() === 0) {
            $conversations->appendMessage($guest, 'guest', 'Nataka kujua jinsi ya kujiunga kabla sijajisajili.');
        }

        // Read conversation (already replied)
        $read = $conversations->openConversationFor(
            Customer::query()->where('customer_number', 'CU-UAT-0001')->first(),
            User::query()->where('email', 'uat.borrower@staging.kopafasta.com')->first(),
        );
        if ($read->customer_id) {
            $conversations->appendMessage($read, 'customer', 'Je, ada ya uanachama ni kiasi gani?', $read->user_id);
            $conversations->appendMessage($read, 'staff', 'Ada ya uanachama inaonekana kwenye akaunti yako chini ya Membership.', $agent->id);
            $read->update([
                'assigned_to' => $agent->id,
                'needs_human' => false,
                'status' => 'replied',
                'topic' => 'Membership',
                'last_message_at' => now()->subHour(),
            ]);
            $read->messages()->update(['read_at' => now()]);
        }

        // Open case linked to waiting conversation + application
        $openCase = SupportTicket::query()->where('ticket_number', 'SUP-2026-UAT001')->first();
        if (! $openCase) {
            $openCase = $tickets->create([
                'ticket_number' => 'SUP-2026-UAT001',
                'customer_id' => $member->id,
                'support_conversation_id' => $waiting->id,
                'related_type' => $application ? 'application' : 'account',
                'related_id' => $application?->id ?? $member->id,
                'subject' => 'Failing to progress application',
                'category' => 'application',
                'priority' => 'normal',
                'description' => 'Member stuck after guarantor step.',
                'source' => 'chatbot',
                'assigned_to' => $agent->id,
                'status' => 'open',
                'actor' => $agent,
            ]);
        }

        // Escalated case
        $escalated = SupportTicket::query()->where('ticket_number', 'SUP-2026-UAT002')->first();
        if (! $escalated) {
            $escalated = $tickets->create([
                'ticket_number' => 'SUP-2026-UAT002',
                'customer_id' => $loanMember->id,
                'support_conversation_id' => $mine->id,
                'related_type' => $loan ? 'loan' : 'account',
                'related_id' => $loan?->id ?? $loanMember->id,
                'subject' => 'Payment not reflecting',
                'category' => 'payment',
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

        // Resolved case
        $resolved = SupportTicket::query()->where('ticket_number', 'SUP-2026-UAT003')->first();
        if (! $resolved) {
            $resolvedConv = SupportConversation::query()->create([
                'customer_id' => $member->id,
                'user_id' => $memberUser->id,
                'channel' => 'web_chat',
                'status' => 'resolved',
                'needs_human' => false,
                'topic' => 'Sign-in help',
                'assigned_to' => $agent->id,
                'last_message_at' => now()->subDays(2),
            ]);
            $conversations->appendMessage($resolvedConv, 'customer', 'Siwezi kuingia kwenye akaunti.', $memberUser->id);
            $conversations->appendMessage($resolvedConv, 'staff', 'Tumerekebisha. Tafadhali jaribu kuingia tena.', $agent->id, true);
            $resolved = $tickets->create([
                'ticket_number' => 'SUP-2026-UAT003',
                'customer_id' => $member->id,
                'support_conversation_id' => $resolvedConv->id,
                'related_type' => 'account',
                'related_id' => $member->id,
                'subject' => 'Failing to sign in',
                'category' => 'profile',
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
        }

        Setting::set('support.uat_credentials', [
            'member_email' => 'uat.support.member@staging.kopafasta.com',
            'member_phone' => '255700000022',
            'member_pin' => '1234',
            'member_password' => 'StagingUat!2026',
            'agent_email' => 'uat.support.agent@staging.kopafasta.com',
            'agent_pin' => '1234',
            'agent_password' => 'StagingUat!2026',
            'admin_email' => 'uat.admin@staging.kopafasta.com',
            'notes' => 'Login as Maclean member → Support → Speak to Support. Admin Account/Role → Support → Inbox.',
        ]);

        $this->command?->info('Support UAT seeded. Member: uat.support.member@staging.kopafasta.com / StagingUat!2026 (PIN 1234)');
    }
}
