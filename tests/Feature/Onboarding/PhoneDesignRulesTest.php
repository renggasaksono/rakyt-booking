<?php

namespace Tests\Feature\Onboarding;

use Tests\TestCase;

/**
 * F-001 P2, P3: one gold button per screen; the right keyboards and autofill.
 * (P1, the 360 × 640 layout, is checked on a device.)
 */
class PhoneDesignRulesTest extends TestCase
{
    private const PAGES = [
        'Auth/Register', 'Auth/Login', 'Auth/ForgotPassword', 'Auth/ResetPassword', 'Auth/VerifyEmail',
        'Onboarding/Organization', 'Onboarding/Trip', 'Onboarding/Schedule', 'Onboarding/Payment', 'Onboarding/Done',
        'Settings/Index', 'Settings/Account', 'Settings/Profile', 'Legal', 'Error',
    ];

    public function test_ac_p2_at_most_one_gold_button_per_screen(): void
    {
        foreach (self::PAGES as $page) {
            $vue = file_get_contents(resource_path("js/pages/{$page}.vue"));

            $this->assertLessThanOrEqual(1, substr_count($vue, 'bg-prada '), "{$page} has more than one gold button.");
        }
    }

    public function test_ac_p2_helper_text_and_kickers_respect_the_type_floor(): void
    {
        foreach (self::PAGES as $page) {
            $vue = file_get_contents(resource_path("js/pages/{$page}.vue"));

            $this->assertDoesNotMatchRegularExpression('/text-\[(?:[0-9]|10)px\]/', $vue, "{$page} uses text below 11 px.");
        }
    }

    public function test_ac_p3_keyboards_and_autofill(): void
    {
        $read = fn (string $path) => file_get_contents(resource_path("js/{$path}.vue"));

        foreach (['pages/Auth/Register', 'pages/Auth/Login', 'pages/Auth/ForgotPassword', 'pages/Settings/Account'] as $page) {
            $this->assertStringContainsString('type="email"', $read($page), $page);
        }

        $this->assertStringContainsString('autocomplete="new-password"', $read('pages/Auth/Register'));
        $this->assertStringContainsString('autocomplete="new-password"', $read('pages/Auth/ResetPassword'));
        $this->assertStringContainsString('autocomplete="current-password"', $read('pages/Auth/Login'));

        // Phone, price, seats and account number get the numeric keyboard.
        $this->assertStringContainsString('inputmode="numeric"', $read('pages/Onboarding/Organization'));
        $this->assertStringContainsString('inputmode="numeric"', $read('pages/Onboarding/Payment'));
        $this->assertStringContainsString('inputmode="numeric"', $read('components/MoneyInput'));
        $this->assertStringContainsString('inputmode="numeric"', $read('components/Stepper'));
    }
}
