<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F-009 A14, A15. Limits are read from config, never hard-coded here.
 */
class RateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'throttle:seller-login'])->post('/test/masuk', fn () => 'ok');
        Route::middleware(['web', 'throttle:password-reset'])->post('/test/lupa-password', fn () => 'ok');
        Route::middleware(['web', 'throttle:public-booking'])->post('/test/pesan', fn () => 'ok');
        Route::middleware(['web', 'throttle:public-booking'])->get('/test/pesan', fn () => 'ok');
    }

    public function test_ac_a14_inertia_form_goes_back_with_bahasa_message_and_keeps_input(): void
    {
        [$max] = config('rakyt.rate_limits.seller_login.per_email_ip');

        for ($i = 0; $i < $max; $i++) {
            $this->post('/test/masuk', ['email' => 'budi@contoh.id', 'password' => 'salah'])->assertOk();
        }

        $this->from('/masuk')
            ->withHeader('X-Inertia', 'true')
            ->post('/test/masuk', ['email' => 'budi@contoh.id', 'password' => 'salah'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors(['rate_limit' => 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.'])
            ->assertSessionHasInput('email', 'budi@contoh.id')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_ac_a14_throttled_forms_never_flash_reset_tokens(): void
    {
        [$max] = config('rakyt.rate_limits.password_reset.per_email');
        $payload = ['email' => 'budi@contoh.id', 'token' => 'reset-token-123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'];

        for ($i = 0; $i < $max; $i++) {
            $this->post('/test/lupa-password', $payload);
        }

        $this->from('/atur-ulang')
            ->withHeader('X-Inertia', 'true')
            ->post('/test/lupa-password', $payload)
            ->assertRedirect('/atur-ulang')
            ->assertSessionHasInput('email', 'budi@contoh.id')
            ->assertSessionMissing('_old_input.token')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
    }

    public function test_ac_a14_json_clients_get_429_with_the_message(): void
    {
        [$max, $minutes] = config('rakyt.rate_limits.public_booking.per_phone');

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/test/pesan', ['phone' => '081234567890'])->assertOk();
        }

        $this->postJson('/test/pesan', ['phone' => '081234567890'])
            ->assertStatus(429)
            ->assertJson(['message' => "Terlalu banyak percobaan. Coba lagi dalam {$minutes} menit."]);
    }

    public function test_ac_a14_plain_requests_get_the_bahasa_429_page(): void
    {
        [$max] = config('rakyt.rate_limits.public_booking.per_phone');

        for ($i = 0; $i < $max; $i++) {
            $this->get('/test/pesan?phone=081234567890');
        }

        $this->get('/test/pesan?phone=081234567890')
            ->assertStatus(429)
            ->assertSee('Terlalu banyak percobaan');
    }

    public function test_ac_a15_phone_formats_share_one_bucket(): void
    {
        [$max] = config('rakyt.rate_limits.public_booking.per_phone');
        $formats = ['081234567890', '+6281234567890', '6281234567890'];

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/test/pesan', ['phone' => $formats[$i % 3]])->assertOk();
        }

        $this->postJson('/test/pesan', ['phone' => '+62 812-3456-7890'])->assertStatus(429);
        $this->postJson('/test/pesan', ['phone' => '081299990000'])->assertOk();
    }

    /**
     * F-009 R1 (F-005 AC-39): an empty or unparseable phone adds no phone
     * bucket, so customers without one never share a bucket; the IP limit still applies.
     */
    public function test_r1_empty_or_unparseable_phones_do_not_share_a_phone_bucket(): void
    {
        [$max] = config('rakyt.rate_limits.public_booking.per_phone');
        [$ipMax] = config('rakyt.rate_limits.public_booking.per_ip');

        for ($i = 0; $i <= $max; $i++) {
            $this->postJson('/test/pesan', ['phone' => ['', 'abc', '0812', null][$i % 4]])->assertOk();
        }

        for ($i = $max + 1; $i < $ipMax; $i++) {
            $this->postJson('/test/pesan', ['phone' => ''])->assertOk();
        }

        $this->postJson('/test/pesan', ['phone' => ''])->assertStatus(429);
    }

    public function test_r1_evidence_upload_without_a_token_parameter_fails_loudly(): void
    {
        $this->withoutExceptionHandling();
        Route::middleware(['web', 'throttle:evidence-upload'])->post('/test/bukti', fn () => 'ok');

        $this->expectException(\LogicException::class);

        $this->post('/test/bukti');
    }

    public function test_ac_a15_seller_email_is_trimmed_and_lowercased_for_the_bucket(): void
    {
        [$max] = config('rakyt.rate_limits.password_reset.per_email');
        $formats = ['Budi@Mail.com', ' budi@mail.com', 'BUDI@MAIL.COM '];

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/test/lupa-password', ['email' => $formats[$i % 3]])->assertOk();
        }

        $this->postJson('/test/lupa-password', ['email' => 'budi@mail.com'])->assertStatus(429);
        $this->postJson('/test/lupa-password', ['email' => 'siti@mail.com'])->assertOk();
    }

    public function test_ac_a15_per_ip_login_limit_applies_across_emails(): void
    {
        [$max] = config('rakyt.rate_limits.seller_login.per_ip');

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/test/masuk', ['email' => "orang{$i}@mail.com"])->assertOk();
        }

        $this->postJson('/test/masuk', ['email' => 'baru@mail.com'])->assertStatus(429);
    }
}
