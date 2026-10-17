<?php

namespace Tests\Feature\Logging;

use App\Logging\RedactSensitiveData;
use App\Logging\UseJsonFormat;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-009 A24.
 */
class StructuredLoggingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_ac_a24_json_channel_is_the_default_and_formats_json_with_redaction(): void
    {
        $this->assertContains('json', config('logging.channels.stack.channels'));

        $logger = Log::channel('json');
        $handler = $logger->getLogger()->getHandlers()[0];

        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
        $this->assertNotEmpty(array_filter(
            $logger->getLogger()->getProcessors(),
            fn ($processor) => $processor instanceof RedactSensitiveData,
        ));
        $this->assertContains(UseJsonFormat::class, config('logging.channels.json.tap'));
    }

    public function test_ac_a24_secrets_are_redacted_and_phone_numbers_masked(): void
    {
        $record = (new RedactSensitiveData)(new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Pesanan dari 081234567890 lewat https://rakyt.id/pesanan?token=abc123&x=1',
            context: [
                'password' => 'rahasia123',
                'reset_token' => 'tok',
                'booking' => ['access_token' => 'xyz', 'phone' => '+6281234567890'],
                'url' => 'https://rakyt.id/storage/org/a.jpg?expires=1&signature=deadbeef',
                'session_id' => 'sid-4f2a9c',
                'amount' => 1250000,
            ],
        ));

        $json = json_encode([$record->message, $record->context]);

        foreach (['rahasia123', '"tok"', 'xyz', 'abc123', 'deadbeef', 'sid-4f2a9c', '81234567890'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "[{$secret}] leaked into the log.");
        }
        $this->assertStringContainsString('+62812****890', $record->message);
        $this->assertSame('+62812****890', $record->context['booking']['phone']);
        $this->assertSame(1250000, $record->context['amount']);
    }

    public function test_ac_a24_request_logs_carry_request_user_and_organization_ids(): void
    {
        [$a] = $this->createTenants();
        $handler = new TestHandler;
        Log::channel('json')->getLogger()->pushHandler($handler);

        Route::middleware(['web', 'auth'])->get('/test/log', function () {
            Log::channel('json')->info('halo');

            return Context::all();
        });

        $context = $this->actingAsTenant($a)->getJson('/test/log')->assertOk()->json();

        $this->assertNotEmpty($context['request_id']);
        $this->assertSame($a->owner->id, $context['user_id']);
        $this->assertSame($a->id(), $context['organization_id']);

        $extra = $handler->getRecords()[0]->extra;
        $this->assertSame($context['request_id'], $extra['request_id']);
        $this->assertSame($a->id(), $extra['organization_id']);
    }

    public function test_guest_requests_have_a_request_id_but_no_user(): void
    {
        Route::middleware('web')->get('/test/log-guest', fn () => Context::all());

        $context = $this->getJson('/test/log-guest')->json();

        $this->assertNotEmpty($context['request_id']);
        $this->assertArrayNotHasKey('user_id', $context);
        $this->assertInstanceOf(User::class, User::factory()->make());
    }
}
