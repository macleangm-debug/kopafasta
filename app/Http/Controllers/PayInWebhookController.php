<?php

namespace App\Http\Controllers;

use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use App\Services\PayInService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayInWebhookController extends Controller
{
    public function __invoke(Request $request, PayInService $payIn, CustomerPaymentService $payments): Response
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Payin-Signature');
        $timestamp = $request->header('X-Payin-Timestamp');

        if (! $payIn->verifyWebhookSignature($raw, $signature, $timestamp)) {
            Log::warning('PayIn webhook signature rejected', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? '');
        $requestRef = (string) ($payload['request_ref'] ?? '');
        $status = (string) ($payload['status'] ?? '');

        if ($requestRef === '') {
            return response('Missing request_ref', 422);
        }

        $payment = CustomerPayment::query()
            ->where('provider', 'payin')
            ->where('provider_ref', $requestRef)
            ->first();

        if (! $payment) {
            // Fallback: merchant reference as external_ref / our payment reference
            $external = (string) ($payload['external_ref'] ?? '');
            if ($external !== '') {
                $payment = CustomerPayment::query()
                    ->where('provider', 'payin')
                    ->where('reference', $external)
                    ->first();
            }
        }

        if (! $payment) {
            Log::info('PayIn webhook for unknown payment', [
                'request_ref' => $requestRef,
                'event' => $event,
            ]);

            return response('OK', 200);
        }

        $meta = array_merge((array) ($payment->provider_meta ?? []), [
            'last_event' => $event,
            'last_payload' => $payload,
            'updated_at' => now()->toIso8601String(),
        ]);

        if (in_array($event, ['payin.completed', 'payout.completed'], true) || $status === 'completed') {
            if ($payment->isPending() || $payment->status === 'processing') {
                $payment->update(['provider_meta' => $meta]);
                $payments->verify($payment->fresh(), null, 'PayIn webhook: '.$event);

                $fresh = $payment->fresh();
                if ($fresh
                    && $fresh->payment_type === 'application_fee'
                    && $fresh->source_type === \App\Models\LoanApplication::class
                    && $fresh->source_id
                ) {
                    $application = \App\Models\LoanApplication::query()->find($fresh->source_id);
                    if ($application) {
                        $secure = app(\App\Services\CollateralSecureService::class);
                        $state = $secure->state($application);
                        if (($state['status'] ?? '') === \App\Services\CollateralSecureService::STATUS_AWAITING_FEE) {
                            $secure->markFeePaid($application);
                        }
                    }
                }
            } else {
                $payment->update(['provider_meta' => $meta]);
            }
        } elseif (in_array($status, ['failed', 'cancelled', 'expired'], true)) {
            $payment->update([
                'status' => 'rejected',
                'provider_meta' => $meta,
                'verification_notes' => trim(($payment->verification_notes ?? '')."\nPayIn {$status}: {$event}"),
            ]);
        } else {
            $payment->update(['provider_meta' => $meta]);
        }

        return response('OK', 200);
    }
}
