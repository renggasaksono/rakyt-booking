<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * F-001 A13: local mail needs no provider, and passwords, reset tokens and
 * verification URLs never reach the application log.
 */
class AuthSecretsNotLoggedTest extends TestCase
{
    use RefreshDatabase;

    private string $appLog;

    private string $mailLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appLog = storage_path('logs/a13-app.log');
        $this->mailLog = storage_path('logs/a13-mail.log');
        File::delete([$this->appLog, $this->mailLog]);

        config([
            'mail.default' => 'log',
            'queue.default' => 'sync',
            'logging.default' => 'a13',
            'logging.channels.a13' => ['driver' => 'single', 'path' => $this->appLog, 'tap' => config('logging.channels.single.tap')],
            'logging.channels.mail.path' => $this->mailLog,
        ]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->appLog, $this->mailLog]);

        parent::tearDown();
    }

    public function test_ac_a13_local_and_testing_use_the_log_or_array_mailer(): void
    {
        $this->assertContains(config('mail.mailers.'.env('MAIL_MAILER', 'log').'.transport'), ['log', 'array']);
        $this->assertSame('mail', config('mail.mailers.log.channel'));
        $this->assertStringContainsString('MAIL_MAILER=log', file_get_contents(base_path('.env.example')));
    }

    public function test_ac_a13_sign_up_sign_in_and_reset_write_no_secrets_to_the_application_log(): void
    {
        $this->post(route('register'), ['name' => 'Agus', 'email' => 'agus@example.com', 'password' => 'rahasia-sekali', 'terms' => true]);
        auth()->logout();
        $this->post(route('login'), ['email' => 'agus@example.com', 'password' => 'salah-sekali']);
        $this->post(route('password.email'), ['email' => 'agus@example.com']);

        $user = User::query()->where('email', 'agus@example.com')->sole();
        $token = Password::broker()->createToken($user);
        $this->post(route('password.store'), ['token' => $token, 'email' => 'agus@example.com', 'password' => 'baru-rahasia']);

        $appLog = File::exists($this->appLog) ? File::get($this->appLog) : '';

        foreach (['rahasia-sekali', 'salah-sekali', 'baru-rahasia', $token, 'signature=', '/verifikasi/', 'password/baru?'] as $secret) {
            $this->assertStringNotContainsString($secret, $appLog);
        }

        // The mails went to the separate, redacted mail log.
        $mailLog = File::get($this->mailLog);
        $this->assertStringContainsString('Verifikasi email Anda', $mailLog);
        $this->assertStringNotContainsString('rahasia-sekali', $mailLog);
        $this->assertStringContainsString('Atur ulang password Rakyt', $mailLog);
        $this->assertStringContainsString('token=[redacted]', $mailLog);
        $this->assertDoesNotMatchRegularExpression('/(signature|token)=(?!\[redacted\])(3D)?[0-9a-f]{20,}/', $mailLog);
    }
}
