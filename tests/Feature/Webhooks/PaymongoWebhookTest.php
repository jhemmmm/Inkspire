<?php

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Testing\TestResponse;

function paymongoWebhookPayload(string $type, string $paymentIntentId): array
{
    return [
        'data' => [
            'attributes' => [
                'type' => $type,
                'data' => [
                    'attributes' => [
                        'payment_intent_id' => $paymentIntentId,
                    ],
                ],
            ],
        ],
    ];
}

function postSignedPaymongoWebhook(array $payload, string $secret): TestResponse
{
    $rawBody = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

    return test()->call('POST', 'webhooks/paymongo', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}",
    ], content: $rawBody);
}

test('a webhook post with no signature header is rejected and does not mutate any transaction or job order', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_no_sig',
    ]);

    $response = $this->post(
        'webhooks/paymongo',
        paymongoWebhookPayload('payment.paid', 'pi_test_no_sig'),
    );

    $response->assertStatus(403);
    expect($response->getStatusCode())->not->toBe(419);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::PendingConfirmation);
    expect($jobOrder->fresh()->payment_status)->not->toBe(PaymentStatus::Paid);
});

test('a webhook post with no CSRF token or session present is never rejected with a 419', function () {
    // No actingAs(), no session, no CSRF token — simulates PayMongo's
    // servers, which never have either (Pitfall 1). The CSRF exclusion in
    // bootstrap/app.php must let this reach the signature middleware
    // instead of Laravel's default CSRF check.
    $response = $this->post('webhooks/paymongo', []);

    expect($response->getStatusCode())->not->toBe(419);
});

test('a signature-verified webhook confirms the matching pending transaction', function () {
    config(['paymongo.webhook_signatures.payment_paid' => 'whsec_test']);

    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_valid',
    ]);

    $response = postSignedPaymongoWebhook(
        paymongoWebhookPayload('payment.paid', 'pi_test_valid'),
        'whsec_test',
    );

    $response->assertStatus(204);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a signature-verified failed-payment webhook fails the transaction and falls the job order back to unpaid', function () {
    config(['paymongo.webhook_signatures.payment_paid' => 'whsec_test']);

    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_failed',
    ]);

    $response = postSignedPaymongoWebhook(
        paymongoWebhookPayload('payment.failed', 'pi_test_failed'),
        'whsec_test',
    );

    $response->assertStatus(204);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Failed);
    // ConfirmPaymentIntent's failure branch (Plan 05-04) recomputes
    // payment_status instead of leaving the job order permanently stuck on
    // PendingConfirmation — no other completed transaction exists here, so
    // it falls back to Unpaid.
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('an unrecognized payment intent id is acknowledged without mutating any data', function () {
    config(['paymongo.webhook_signatures.payment_paid' => 'whsec_test']);

    $response = postSignedPaymongoWebhook(
        paymongoWebhookPayload('payment.paid', 'pi_unknown'),
        'whsec_test',
    );

    $response->assertStatus(204);
    expect(Transaction::count())->toBe(0);
});

test('a signature computed with the wrong secret is rejected', function () {
    config(['paymongo.webhook_signatures.payment_paid' => 'whsec_test']);

    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_tampered',
    ]);

    $response = postSignedPaymongoWebhook(
        paymongoWebhookPayload('payment.paid', 'pi_test_tampered'),
        'wrong-secret',
    );

    $response->assertStatus(403);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::PendingConfirmation);
});
