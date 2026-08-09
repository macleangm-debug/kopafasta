<?php

namespace App\Services\Integrations;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\CrbService;
use App\Services\CustomerPaymentService;
use App\Services\NotificationService;
use App\Services\PayInService;
use Illuminate\Support\Str;

class IntegrationLiveTestService
{
    public function __construct(
        protected PayInService $payIn,
        protected NotificationService $notifications,
        protected CrbService $crb,
        protected CustomerPaymentService $payments,
    ) {}

    /**
     * Create a small test collection and open the shared payment gate path.
     * Phone does not need to belong to an existing member — a sandbox customer is created if needed.
     *
     * @return array{ok: bool, title: string, message: string, lines: list<string>, payment_url?: string, payment_id?: int}
     */
    public function testPayment(string $phone, float $amount = 1000): array
    {
        $phone = trim($phone);
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
                'notes' => 'Admin integration live test '.now()->toDateTimeString(),
                'provider_meta' => [
                    'integration_live_test' => true,
                    'triggered_by' => auth()->id(),
                ],
            ]);

            if ($payment->status === 'awaiting_payment' || $payment->status === 'pending') {
                try {
                    $payment = $this->payments->initiateCollection($payment, $phone);
                } catch (\Throwable $e) {
                    // Gate URL still useful even if collect fails (dummy / misconfig).
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
                        'payment_url' => route('site.borrower.payments.show', $payment),
                        'admin_url' => route('admin.payments.show', $payment),
                    ];
                }
            }

            return [
                'ok' => true,
                'title' => 'Payment live test ready',
                'message' => 'Test payment created. Open the payment gate (payments.show) to verify the flow.',
                'lines' => [
                    'Payment #'.$payment->id,
                    'Phone: '.$phone,
                    'Amount: '.number_format((float) $payment->amount, 0).' TZS',
                    'Status: '.$payment->status,
                    'Gateway: '.(payment_gateway_is_dummy() ? 'dummy' : 'live/sandbox'),
                ],
                'payment_id' => $payment->id,
                'payment_url' => route('site.borrower.payments.show', $payment),
                'admin_url' => route('admin.payments.show', $payment),
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
        $phone = trim($phone);
        $body = trim((string) ($message ?: 'Kopafasta integration live test at '.now()->format('H:i:s').'.'));

        try {
            $log = $this->notifications->sendSms($phone, $body, $this->findCustomerByPhone($phone), null);

            return [
                'ok' => in_array($log->status, ['sent', 'queued', 'delivered', 'logged'], true),
                'title' => 'Messaging live test',
                'message' => 'SMS dispatch finished with status “'.$log->status.'”.',
                'lines' => [
                    'Recipient: '.$phone,
                    'Log #'.$log->id,
                    'Channel: sms',
                    Str::limit($body, 120),
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
     * @return array{ok: bool, title: string, message: string, lines: list<string>}
     */
    public function testCrb(?string $nida = null, ?string $fullName = null, ?string $dob = null): array
    {
        $sample = config('crb_samples.scenarios.verified', []);
        $nida = trim((string) ($nida ?: ($sample['nida'] ?? '19810713-00001-23456-78')));
        $fullName = $fullName ?: ($sample['full_name'] ?? null);
        $dob = $dob ?: ($sample['date_of_birth'] ?? null);

        try {
            $result = $this->crb->verifyConsumerIdentity($nida, $fullName, $dob);
            $driver = $this->crb->usesStub() ? 'stub/sandbox' : 'live';

            if ($result->success) {
                return [
                    'ok' => true,
                    'title' => 'CRB live test succeeded',
                    'message' => ($result->fullName ?: 'Identity matched').' via '.$driver.'.',
                    'lines' => array_values(array_filter([
                        'NIDA: '.$nida,
                        $result->dateOfBirth ? 'DOB: '.$result->dateOfBirth : null,
                        $result->gender ? 'Gender: '.$result->gender : null,
                        $result->message ? 'Note: '.$result->message : null,
                    ])),
                ];
            }

            return [
                'ok' => false,
                'title' => 'CRB live test failed',
                'message' => $result->message ?: 'No match from CRB.',
                'lines' => ['NIDA: '.$nida, 'Driver: '.$driver],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'title' => 'CRB live test failed',
                'message' => $e->getMessage(),
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
