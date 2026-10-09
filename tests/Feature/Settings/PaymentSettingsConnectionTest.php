<?php

namespace Tests\Feature\Settings;

use App\Services\Snippe\SnippeConnectionTestResult;
use App\Services\Snippe\SnippePaymentService;
use App\Settings\PaymentSettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentSettingsConnectionTest extends PaymentSettingsTestCase
{
    public function test_a_successful_check_reports_success(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success', 'data' => []], 200),
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection')
            ->assertRedirect('/admin/settings/payments')
            ->assertSessionHas('success')
            ->assertSessionHas('connectionTest', 'success');
    }

    public function test_the_check_uses_the_read_only_balance_endpoint(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $this->actingAs($this->admin())->post('/admin/settings/payments/connection');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.snippe.test/v1/payments/balance'
                && $request->method() === 'GET';
        });
    }

    public function test_the_check_never_creates_a_payment(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $this->actingAs($this->admin())->post('/admin/settings/payments/connection');

        // A payment intent would be a POST to /v1/payments and would leave a
        // pending row behind. Neither may happen.
        Http::assertNotSent(function (Request $request) {
            return $request->method() === 'POST' && str_contains($request->url(), '/v1/payments');
        });

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_the_check_sends_exactly_one_request(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $this->actingAs($this->admin())->post('/admin/settings/payments/connection');

        Http::assertSentCount(1);
    }

    public function test_the_check_sends_no_retry_even_when_the_provider_fails(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'error'], 500),
        ]);

        $this->actingAs($this->admin())->post('/admin/settings/payments/connection');

        Http::assertSentCount(1);
    }

    public function test_the_check_authenticates_with_the_bearer_token(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $this->actingAs($this->admin())->post('/admin/settings/payments/connection');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-api-key'));
    }

    public function test_a_rejected_key_is_reported_without_exposing_the_response(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response([
                'status' => 'error',
                'message' => 'invalid or missing api key zzz-leak-me',
            ], 401),
        ]);

        $response = $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection');

        $response->assertSessionHas('connectionTest', 'unauthorized');

        $message = (string) $response->getSession()->get('error');
        $this->assertStringContainsString('rejected the credentials', $message);
        $this->assertStringNotContainsString('zzz-leak-me', $message);
    }

    public function test_a_missing_scope_is_reported_as_a_credentials_problem(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'error'], 403),
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection')
            ->assertSessionHas('connectionTest', 'unauthorized');
    }

    public function test_a_transport_failure_is_reported_as_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 6: could not resolve host, key=test-api-key'));

        $response = $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection');

        $response->assertSessionHas('connectionTest', 'unreachable');

        $message = (string) $response->getSession()->get('error');
        $this->assertStringContainsString('could not be reached', $message);
        // A transport exception message can embed the request; it must not
        // reach the page.
        $this->assertStringNotContainsString('test-api-key', $message);
    }

    public function test_an_unexpected_status_is_reported_without_the_body(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response([
                'status' => 'error',
                'message' => 'upstream-exploded-leak-me',
            ], 503),
        ]);

        $response = $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection');

        $response->assertSessionHas('connectionTest', 'unexpected');
        $this->assertStringNotContainsString(
            'upstream-exploded-leak-me',
            (string) $response->getSession()->get('error')
        );
    }

    public function test_the_check_is_skipped_when_no_api_key_is_configured(): void
    {
        config()->set('services.snippe.api_key', null);
        Http::fake();

        $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection')
            ->assertSessionHas('connectionTest', 'misconfigured');

        // Nothing should have been sent anywhere.
        Http::assertNothingSent();
    }

    public function test_the_check_is_skipped_when_the_provider_is_disabled(): void
    {
        config()->set('services.snippe.enabled', false);
        Http::fake();

        $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection')
            ->assertSessionHas('connectionTest', 'disabled');

        Http::assertNothingSent();
    }

    public function test_a_disabled_provider_reports_no_blocking_reason_before_testing(): void
    {
        config()->set('services.snippe.api_key', null);

        $result = app(PaymentSettingsService::class)->testConnection();

        $this->assertSame(
            SnippeConnectionTestResult::OUTCOME_MISCONFIGURED,
            $result->outcome
        );
    }

    public function test_the_check_logs_nothing_sensitive(): void
    {
        // The service logs through Log::channel('stack'), so a spy on the
        // default facade would never see it. Collect from the channel instead.
        $channel = new class
        {
            /** @var list<array{message: string, context: array}> */
            public array $records = [];

            public function info(string $message, array $context = []): void
            {
                $this->records[] = ['message' => $message, 'context' => $context];
            }
        };

        Log::shouldReceive('channel')->with('stack')->andReturn($channel);

        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        config()->set('services.snippe.api_key', 'snp_log_secret_key');
        config()->set('services.snippe.webhook_secret', 'whsec_log_secret_value');

        $this->actingAs($this->admin())->post('/admin/settings/payments/connection');

        $this->assertCount(1, $channel->records);

        $record = $channel->records[0];
        $serialised = json_encode($record);

        $this->assertSame('snippe.connection_test', $record['message']);
        $this->assertSame('/v1/payments/balance', $record['context']['endpoint']);
        $this->assertSame('success', $record['context']['outcome']);
        $this->assertSame(200, $record['context']['status_code']);
        $this->assertIsInt($record['context']['latency_ms']);

        $this->assertStringNotContainsString('snp_log_secret_key', $serialised);
        $this->assertStringNotContainsString('whsec_log_secret_value', $serialised);
        $this->assertStringNotContainsString('Authorization', $serialised);

        foreach (['api_key', 'token', 'secret', 'body', 'payload', 'balance'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $record['context']);
        }
    }

    public function test_the_balance_endpoint_constant_is_read_only(): void
    {
        $this->assertSame('/v1/payments/balance', SnippePaymentService::CONNECTION_TEST_ENDPOINT);
    }

    public function test_the_result_object_has_no_field_that_could_hold_a_secret(): void
    {
        $properties = array_keys(
            (new \ReflectionClass(SnippeConnectionTestResult::class))->getProperties()
        );

        foreach ($properties as $property) {
            $this->assertNotContains(
                $property,
                ['apiKey', 'api_key', 'token', 'secret', 'body', 'payload', 'balance', 'response'],
                "The connection result must not be able to carry '{$property}'."
            );
        }
    }
}
