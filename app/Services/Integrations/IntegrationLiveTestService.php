<?php

namespace App\Services\Integrations;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\NotificationLog;
use App\Services\CrbService;
use App\Services\CustomerPaymentService;
use App\Services\Mail\GatewayMailConfigurator;
use App\Services\PayInService;
use App\Services\Sms\SmsManager;
use App\Support\NidaNumber;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class IntegrationLiveTestService
{
    public function __construct(
        protected PayInService $payIn,
        protected CrbService $crb,
        protected CustomerPaymentService $payments,
        protected SmsManager $sms,
        protected GatewayMailConfigurator $mailConfig,
    ) {}

    /**
     * Create a small test collection and open the shared payment gate path.
     * Phone does not need to belong to an existing member — a sandbox customer is created if needed.
     *
     * @return array{ok: bool, title: string, message: string, lines: list<string>, payment_url?: string, payment_id?: int}
     */
    public function testPayment(string $phone, float $amount = 1000): array
    {
        $phone = PhoneNumber::normalizeForCountry($phone, 'TZ') ?? trim($phone);
        if ($phone === '') {
            return [
                'ok' => false,
                'title' => 'Payment live test',
                'message' => 'Enter a phone number to charge for the test.',
                'lines' => [],
            ];
        }

        try {
            $customer = $this->resolveOrCreateTestCustomer($phone);

            $payment = $this->payments->create([
                'customer' => $customer,
                'payment_type' => 'registration_fee',
                'payment_method' => 'mobile_money',
                'amount' => max(500, round($amount, 2)),
                'mobile_number' => $phone,
                'notes' => 'Integration rehearsal / PayIn live test '.now()->toDateTimeString(),
                'provider_meta' => [
                    'integration_live_test' => true,
                    'integration_rehearsal' => true,
                    'integration_partner' => 'payin',
                    'triggered_by' => auth()->id(),
                ],
            ]);

            if ($payment->status === 'awaiting_payment' || $payment->status === 'pending') {
                try {
                    $payment = $this->payments->initiateCollection($payment, $phone);
                } catch (\Throwable $e) {
                    $preview = route('admin.settings.integrations.live-test.payment', $payment);

                    return [
                        'ok' => false,
                        'title' => 'Payment created — collect failed',
                        'message' => $e->getMessage(),
                        'lines' => [
                            'Payment #'.$payment->id,
                            'Status: '.$payment->status,
                            'Open the payment gate to inspect the UI.',
                        ],
                        'payment_id' => $payment->id,
                        'payment_url' => $preview,
                        'admin_url' => route('admin.payments.show', $payment),
                        'secondaryLabel' => 'Open payment.show',
                        'secondaryHref' => $preview,
                    ];
                }
            }

            $preview = route('admin.settings.integrations.live-test.payment', $payment);

            return [
                'ok' => true,
                'title' => 'PayIn live test ready',
                'message' => 'Controlled test payment created. Continue on the canonical payment.show journey to complete the rehearsal.',
                'lines' => [
                    'Payment #'.$payment->id,
                    'Phone: '.$phone,
                    'Amount: '.number_format((float) $payment->amount, 0).' TZS',
                    'Status: '.$payment->status,
                    'Tagged: integration rehearsal',
                    'Gateway: '.(payment_gateway_is_dummy() ? 'dummy' : 'live'),
                ],
                'statuses' => [
                    ['key' => 'payment', 'label' => 'Test payment', 'value' => 'Created', 'state' => 'success'],
                    ['key' => 'gate', 'label' => 'Next step', 'value' => 'Open payment.show', 'state' => 'neutral'],
                ],
                'payment_id' => $payment->id,
                'payment_url' => $preview,
                'admin_url' => route('admin.payments.show', $payment),
                'secondaryLabel' => 'Continue to payment.show',
                'secondaryHref' => $preview,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'title' => 'Payment live test failed',
                'message' => $e->getMessage(),
                'lines' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, title: string, message: string, lines: list<string>}
     */
    public function testMessaging(string $phone, ?string $message = null): array
    {
        $phone = PhoneNumber::normalizeForCountry($phone, 'TZ') ?? trim($phone);
        $body = trim((string) ($message ?: 'Kopafasta Unitxt live test at '.now()->format('H:i:s').'.'));

        if ($phone === '') {
            return [
                'ok' => false,
                'title' => 'Unitxt SMS live test failed',
                'message' => 'Enter a recipient phone number.',
                'lines' => [],
            ];
        }

        try {
            $log = NotificationLog::create([
                'channel' => 'sms',
                'template' => 'integration_live_test',
                'recipient' => $phone,
                'message' => Str::limit($body, 800, ''),
                'status' => 'queued',
                'category' => 'integration',
            ]);

            $result = $this->sms->driver()->send($phone, $body);
            $status = ($result['ok'] ?? false) ? 'sent' : 'failed';
            $providerRef = $result['provider_id'] ?? null;
            $log->update([
                'status' => $status,
                'sent_at' => ($result['ok'] ?? false) ? now() : null,
                'meta' => array_filter([
                    'integration_live_test' => true,
                    'provider_ref' => $providerRef,
                    'error' => $result['error'] ?? null,
                ]),
            ]);

            return [
                'ok' => $status === 'sent',
                'title' => 'Unitxt SMS live test',
                'message' => $status === 'sent'
                    ? 'SMS dispatch finished with status “'.$status.'”.'
                    : (string) ($result['error'] ?? 'SMS dispatch failed.'),
                'lines' => array_values(array_filter([
                    'Recipient: '.$phone,
                    'Log #'.$log->id,
                    'Channel: sms',
                    $providerRef ? 'Provider ref: '.$providerRef : null,
                    Str::limit($body, 120),
                ])),
                'statuses' => [
                    ['key' => 'sms', 'label' => 'Delivery', 'value' => ucfirst($status), 'state' => $status === 'sent' ? 'success' : 'error'],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'title' => 'Messaging live test failed',
                'message' => $e->getMessage(),
                'lines' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, title: string, message: string, lines: list<string>, statuses?: list<array{key?:string,label:string,value:string,state:string}>}
     */
    public function testEmail(string $to, ?string $subject = null, ?string $body = null): array
    {
        $to = trim($to);
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'title' => 'Email live test failed',
                'message' => 'Enter a valid recipient email address.',
                'lines' => [],
            ];
        }

        if (! $this->mailConfig->isConfigured()) {
            return [
                'ok' => false,
                'title' => 'Email live test failed',
                'message' => 'Configure SMTP host and from address under Email (SMTP) before sending a live test.',
                'lines' => [],
                'statuses' => [
                    ['key' => 'email', 'label' => 'Delivery', 'value' => 'Not configured', 'state' => 'warning'],
                ],
            ];
        }

        $subject = trim((string) ($subject ?: 'Kopafasta integration email live test'));
        $body = trim((string) ($body ?: 'This is a controlled Kopafasta Email (SMTP) live test at '.now()->toDateTimeString().'.'));

        try {
            $this->mailConfig->apply();

            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            NotificationLog::create([
                'channel' => 'email',
                'template' => 'integration_live_test',
                'recipient' => $to,
                'message' => '['.$subject.'] '.Str::limit($body, 500, ''),
                'status' => 'sent',
                'sent_at' => now(),
                'category' => 'integration',
                'meta' => ['integration_live_test' => true],
            ]);

            return [
                'ok' => true,
                'title' => 'Email live test submitted',
                'message' => 'Test email was handed to the configured mail provider/SMTP.',
                'lines' => [
                    'To: '.$to,
                    'Subject: '.$subject,
                    'Mailer: '.(string) config('mail.default'),
                ],
                'statuses' => [
                    ['key' => 'email', 'label' => 'Delivery', 'value' => 'Submitted', 'state' => 'success'],
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'title' => 'Email live test failed',
                'message' => 'The configured mailer could not send the test email.',
                'lines' => [],
                'statuses' => [
                    ['key' => 'email', 'label' => 'Delivery', 'value' => 'Failed', 'state' => 'error'],
                ],
            ];
        }
    }

    /**
     * @return array{ok: bool, title: string, message: string, lines: list<string>}
     */
    public function testCrb(?string $nida = null, ?string $fullName = null, ?string $dob = null): array
    {
        $sample = config('crb_samples.scenarios.verified', []);
        $nida = trim((string) ($nida ?: ''));

        if ($nida !== '' && ! NidaNumber::isValid($nida)) {
            return [
                'ok' => false,
                'title' => 'CRB live test failed',
                'message' => 'Enter a valid NIDA number (XXXXXXXX-XXXXX-XXXXX-XX).',
                'lines' => [],
            ];
        }

        $nida = $nida !== ''
            ? (NidaNumber::format($nida) ?? $nida)
            : (string) ($sample['nida'] ?? '19810713-00001-23456-78');
        $fullName = $fullName ?: ($sample['full_name'] ?? null);
        $dob = $dob ?: ($sample['date_of_birth'] ?? null);

        try {
            $result = $this->crb->verifyConsumerIdentity($nida, $fullName, $dob);
            $driver = $this->crb->usesStub() ? 'stub/sandbox' : 'live';

            if ($result->success) {
                return [
                    'ok' => true,
                    'title' => 'CRB live test succeeded',
                    'message' => 'CRB enquiry completed via '.$driver.'.',
                    'lines' => array_values(array_filter([
                        'NIDA format validated',
                        $result->message ? 'Note: '.app(IntegrationFeedback::class)->sanitizeReason((string) $result->message) : null,
                    ])),
                    'statuses' => [
                        ['key' => 'crb', 'label' => 'Enquiry', 'value' => 'Matched', 'state' => 'success'],
                        ['key' => 'driver', 'label' => 'Driver', 'value' => $driver, 'state' => 'neutral'],
                    ],
                ];
            }

            return [
                'ok' => false,
                'title' => 'CRB live test failed',
                'message' => app(IntegrationFeedback::class)
                    ->sanitizeReason((string) ($result->message ?: 'No match from CRB.')),
                'lines' => ['Driver: '.$driver],
                'statuses' => [
                    ['key' => 'crb', 'label' => 'Enquiry', 'value' => 'Failed', 'state' => 'error'],
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'title' => 'CRB live test failed',
                'message' => 'CRB enquiry could not be completed.',
                'lines' => [],
            ];
        }
    }

    protected function resolveOrCreateTestCustomer(string $phone): Customer
    {
        $existing = $this->findCustomerByPhone($phone);
        if ($existing) {
            return $existing;
        }

        $digits = preg_replace('/\D/', '', $phone) ?: Str::random(9);

        return Customer::query()->create([
            'customer_number' => 'TST-'.strtoupper(Str::random(8)),
            'type' => 'individual',
            'status' => 'active',
            'branch_id' => app(\App\Services\BranchService::class)->headOfficeId(),
            'first_name' => 'Integration',
            'last_name' => 'LiveTest',
            'phone' => $digits,
            'country_code' => 'TZ',
            'activity_details' => [
                'integration_live_test' => true,
                'created_by_admin' => auth()->id(),
            ],
        ]);
    }

    protected function findCustomerByPhone(string $phone): ?Customer
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        $suffix = substr($digits, -9);

        return Customer::query()
            ->where(function ($q) use ($phone, $digits, $suffix) {
                $q->where('phone', $phone)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', 'like', '%'.$suffix);
            })
            ->first();
    }
}
