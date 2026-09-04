<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PayIn Tanzania mobile-money API (docs.payin.co.tz).
 */
class PayInService
{
    public function settings(): array
    {
        $group = Setting::group('payin');

        $environment = (string) ($group['environment'] ?? config('payin.environment', 'sandbox'));
        if (app()->environment('staging') && ! app()->isProduction()) {
            $environment = 'sandbox';
        }

        return [
            'enabled' => (bool) ($group['enabled'] ?? config('payin.enabled', false)),
            'environment' => $environment === 'production' && ! app()->isProduction() ? 'sandbox' : $environment,
            'api_key' => (string) ($group['api_key'] ?? config('payin.api_key', '')),
            'api_secret' => (string) ($group['api_secret'] ?? config('payin.api_secret', '')),
            'webhook_secret' => (string) ($group['webhook_secret'] ?? config('payin.webhook_secret', '')),
            'default_callback_url' => (string) ($group['default_callback_url'] ?? config('payin.default_callback_url', '')),
        ];
    }

    public function isConfigured(): bool
    {
        $s = $this->settings();

        return $s['enabled']
            && filled($s['api_key'])
            && filled($s['api_secret']);
    }

    /** Live mobile-money collections via PayIn (USSD push). */
    public function isLiveCollectionEnabled(): bool
    {
        return $this->isConfigured() && ! payment_gateway_is_dummy();
    }

    public function baseUrl(): string
    {
        $env = $this->settings()['environment'] === 'production' ? 'production' : 'sandbox';

        return rtrim((string) config("payin.base_urls.{$env}"), '/');
    }

    public function callbackUrl(): string
    {
        $configured = trim($this->settings()['default_callback_url']);
        if ($configured !== '') {
            return $configured;
        }

        return route('webhooks.payin');
    }

    /** @return list<string> */
    public function operatorCodes(): array
    {
        return array_keys(config('payin.operators', []));
    }

    public function normalizeOperator(?string $operator): ?string
    {
        $code = strtolower(trim((string) $operator));
        if ($code === '') {
            return null;
        }

        $aliases = [
            'tigo' => 'tigopesa',
            'tigo pesa' => 'tigopesa',
            'mixx' => 'tigopesa',
            'mixx by yas' => 'tigopesa',
            'vodacom' => 'mpesa',
            'm-pesa' => 'mpesa',
            'airtel money' => 'airtel',
            'halo' => 'halopesa',
            'halo pesa' => 'halopesa',
        ];
        $code = $aliases[$code] ?? $code;

        return in_array($code, $this->operatorCodes(), true) ? $code : null;
    }

    /**
     * PayIn replays the first collection when the same idempotency key is reused.
     * Retries ("didn't get USSD") must use a fresh key.
     */
    public function freshIdempotencyKey(string $reference): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]/', '', $reference) ?: 'pay';
        $base = Str::limit($base, 80, '');

        return $base.'-'.Str::lower((string) Str::ulid());
    }

    /**
     * @return array{ok: bool, request_ref: ?string, status: ?string, operator: ?string, message: string, raw: array<string, mixed>, idempotency_key: string}
     */
    public function collect(string $phone, float $amount, string $reference, ?string $description = null, ?string $operator = null): array
    {
        if (app()->environment('staging') && ! app()->isProduction()) {
            $env = $this->settings()['environment'] ?? 'sandbox';
            if ($env === 'production') {
                throw ValidationException::withMessages([
                    'payment_method' => [__('borrower.payments.aggregator_required')],
                ]);
            }
        }
        app(\App\Services\Marketing\DemoGuard::class)->assertCanMoveMoney('collect via PayIn');
        $this->assertReady();

        $phone = $this->normalizePhone($phone);
        $operator = $this->normalizeOperator($operator);
        $idempotencyKey = $this->freshIdempotencyKey($reference);
        $payload = array_filter([
            'phone' => $phone,
            'amount' => (int) round($amount),
            'reference' => Str::limit($reference, 100, ''),
            'description' => $this->sanitizeDescription($description),
            'operator' => $operator,
            'currency' => 'TZS',
            'callback_url' => $this->callbackUrl(),
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = $this->http()
                ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
                ->post($this->baseUrl().'/collection', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $body = $e->response?->json() ?? [];
            $message = $this->formatErrorMessage($body, $e->getMessage());
            \Illuminate\Support\Facades\Log::warning('PayIn collection failed', [
                'status' => $e->response?->status(),
                'phone' => $phone,
                'reference' => $reference,
                'message' => $message,
                'body' => $body,
            ]);

            throw ValidationException::withMessages([
                'payment_phone' => [$message],
            ]);
        }

        $ok = (bool) ($response['success'] ?? false);
        $requestRef = $response['request_ref'] ?? null;
        if (! $ok || blank($requestRef)) {
            $message = $this->formatErrorMessage(
                is_array($response) ? $response : [],
                'PayIn did not accept this collection request.'
            );
            \Illuminate\Support\Facades\Log::warning('PayIn collection rejected', [
                'phone' => $phone,
                'reference' => $reference,
                'message' => $message,
                'body' => is_array($response) ? $response : [],
            ]);

            throw ValidationException::withMessages([
                'payment_phone' => [$message],
            ]);
        }

        return [
            'ok' => true,
            'request_ref' => $requestRef,
            'status' => $response['status'] ?? null,
            'operator' => $response['operator'] ?? null,
            'message' => (string) ($response['message'] ?? 'Collection request sent.'),
            'raw' => is_array($response) ? $response : [],
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * @return array{ok: bool, request_ref: ?string, status: ?string, message: string, raw: array<string, mixed>}
     */
    public function disburse(string $phone, float $amount, string $reference, ?string $description = null, ?string $operator = null): array
    {
        app(\App\Services\Marketing\DemoGuard::class)->assertCanMoveMoney('disburse via PayIn');
        $this->assertReady();

        $payload = array_filter([
            'phone' => $this->normalizePhone($phone),
            'amount' => (int) round($amount),
            'reference' => Str::limit($reference, 100, ''),
            'description' => $this->sanitizeDescription($description),
            'operator' => $operator,
            'currency' => 'TZS',
            'callback_url' => $this->callbackUrl(),
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = $this->http()
                ->withHeaders(['X-Idempotency-Key' => $reference])
                ->post($this->baseUrl().'/disbursement', $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $body = $e->response?->json() ?? [];

            return [
                'ok' => false,
                'request_ref' => null,
                'status' => null,
                'message' => (string) ($body['message'] ?? $e->getMessage()),
                'raw' => is_array($body) ? $body : [],
            ];
        }

        return [
            'ok' => (bool) ($response['success'] ?? false),
            'request_ref' => $response['request_ref'] ?? null,
            'status' => $response['status'] ?? null,
            'message' => (string) ($response['message'] ?? 'Disbursement submitted.'),
            'raw' => is_array($response) ? $response : [],
        ];
    }

    /**
     * Poll PayIn for a collection/disbursement status (docs: GET /v1/status/{request_ref}).
     * Prefer webhooks; poll no more than once every 5 seconds from the client.
     *
     * @return array{ok: bool, request_ref: ?string, status: ?string, message: string, raw: array<string, mixed>}
     */
    public function status(string $requestRef): array
    {
        $this->assertReady();

        try {
            $response = $this->http()
                ->get($this->baseUrl().'/status/'.rawurlencode($requestRef))
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $body = $e->response?->json() ?? [];

            return [
                'ok' => false,
                'request_ref' => $requestRef,
                'status' => null,
                'message' => (string) ($body['message'] ?? $e->getMessage()),
                'raw' => is_array($body) ? $body : [],
            ];
        }

        return [
            'ok' => true,
            'request_ref' => $response['request_ref'] ?? $requestRef,
            'status' => isset($response['status']) ? (string) $response['status'] : null,
            'message' => (string) ($response['message'] ?? 'Status retrieved.'),
            'raw' => is_array($response) ? $response : [],
        ];
    }

    /** @return array{ok: bool, message: string, balance: ?array} */
    public function healthCheck(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'PayIn is disabled or API keys are missing.',
                'balance' => null,
            ];
        }

        try {
            $balance = $this->http()->get($this->baseUrl().'/balance')->throw()->json();

            return [
                'ok' => true,
                'message' => 'Connected to PayIn ('.$this->settings()['environment'].').',
                'balance' => is_array($balance) ? $balance : null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'PayIn connection failed: '.$e->getMessage(),
                'balance' => null,
            ];
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature, ?string $timestamp): bool
    {
        $secret = $this->settings()['webhook_secret'];
        if ($secret === '' || ! filled($signature) || ! filled($timestamp)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '255'.substr($digits, 1);
        }

        return $digits;
    }

    /** PayIn rejects underscores and most punctuation in description. */
    public function sanitizeDescription(?string $description): ?string
    {
        if ($description === null || trim($description) === '') {
            return null;
        }

        $clean = preg_replace('/[^A-Za-z0-9\s\-]/', ' ', $description) ?? '';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');

        return $clean !== '' ? Str::limit($clean, 255, '') : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function formatErrorMessage(array $body, string $fallback): string
    {
        $fieldErrors = collect($body['errors'] ?? [])
            ->flatten()
            ->filter(fn ($v) => filled($v))
            ->unique()
            ->values();

        if ($fieldErrors->isNotEmpty()) {
            return (string) $fieldErrors->first();
        }

        $message = trim((string) ($body['message'] ?? ''));

        return $message !== '' ? $message : ($fallback ?: 'PayIn collection failed.');
    }

    private function assertReady(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'payment_method' => ['PayIn is not configured. Add API keys under Settings → Integrations → PayIn.'],
            ]);
        }
    }

    private function http()
    {
        $s = $this->settings();

        return Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->withHeaders([
                'X-API-Key' => $s['api_key'],
                'X-API-Secret' => $s['api_secret'],
            ]);
    }
}
