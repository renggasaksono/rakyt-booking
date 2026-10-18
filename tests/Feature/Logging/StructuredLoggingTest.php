<?php

namespace Tests\Feature\Logging;

use App\Logging\RedactLogs;
use App\Logging\RedactSensitiveData;
use App\Logging\UseJsonFormat;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PDOException;
use RuntimeException;
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
        $this->assertContains(RedactLogs::class, config('logging.channels.json.tap'));
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

    public function test_ac_a24_exceptions_in_context_are_scrubbed(): void
    {
        $e = new RuntimeException('Gagal kirim WA ke 081234567890');
        $record = $this->redact($e->getMessage(), ['exception' => $e]);

        $json = json_encode([$record->message, $record->context]);
        $this->assertStringNotContainsString('81234567890', $json);
        $this->assertSame(RuntimeException::class, $record->context['exception']['class']);
        $this->assertStringContainsString('+62812****890', $record->context['exception']['message']);
    }

    public function test_ac_a24_sql_errors_never_log_bound_values(): void
    {
        $e = new QueryException(
            'pgsql',
            'insert into bookings (phone, name) values (?, ?)',
            ['081234567890', 'Budi Santoso'],
            new PDOException('SQLSTATE[23505]: Unique violation'),
        );
        $record = $this->redact($e->getMessage(), ['exception' => $e]);

        $json = json_encode([$record->message, $record->context]);
        $this->assertStringNotContainsString('Budi Santoso', $json);
        $this->assertStringNotContainsString('81234567890', $json);
        $this->assertStringContainsString('insert into bookings (phone, name) values (?, ?)', $record->message);
    }

    public function test_ac_a24_formatted_phone_numbers_are_masked_and_lookalike_keys_kept(): void
    {
        $record = $this->redact('Hubungi 0812-3456-7890 atau +62 812 3456 7890', ['passengers' => 4]);

        $this->assertStringNotContainsString('3456', $record->message);
        $this->assertSame(4, $record->context['passengers']);
    }

    public function test_ac_a24_models_and_other_objects_in_context_are_scrubbed(): void
    {
        $booking = new class extends Model
        {
            protected $guarded = [];
        };
        $booking->fill(['phone' => '+6281234567890', 'password' => 'rahasia123', 'participants' => 2]);

        $plain = new \stdClass;
        $plain->phone = '081234567890';

        $record = $this->redact('m', ['booking' => $booking, 'other' => $plain]);
        $json = json_encode($record->context);

        $this->assertStringNotContainsString('rahasia123', $json);
        $this->assertStringNotContainsString('81234567890', $json);
        $this->assertSame('+62812****890', $record->context['booking']['phone']);
        $this->assertSame(2, $record->context['booking']['participants']);
        $this->assertSame('[object stdClass]', $record->context['other']);
    }

    public function test_ac_a24_stack_traces_never_carry_argument_values(): void
    {
        $check = function (string $password): never {
            throw new RuntimeException('hash check failed');
        };

        try {
            $check('rahasia-di-trace');
        } catch (RuntimeException $e) {
            $record = $this->redact($e->getMessage(), ['exception' => $e]);
        }

        $this->assertStringNotContainsString('rahasia-di-trace', json_encode($record->context));
        $this->assertIsList($record->context['exception']['trace']);
    }

    public function test_ac_a24_every_log_channel_redacts(): void
    {
        foreach (config('logging.channels') as $name => $channel) {
            // `emergency` is Laravel's fallback when a channel cannot be built; it takes no taps.
            if ($name === 'emergency' || $channel['driver'] === 'stack') {
                continue;
            }

            $this->assertContains(RedactLogs::class, $channel['tap'] ?? [], "Log channel [{$name}] must use the RedactLogs tap.");
        }
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function redact(string $message, array $context): LogRecord
    {
        return (new RedactSensitiveData)(new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Error,
            message: $message,
            context: $context,
        ));
    }
}
