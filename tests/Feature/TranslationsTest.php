<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ADR-0007: user-facing strings in Bahasa Indonesia via lang/id.
 */
class TranslationsTest extends TestCase
{
    public function test_locale_defaults_to_indonesian(): void
    {
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('id', config('app.fallback_locale'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function frameworkFiles(): array
    {
        return ['validation' => ['validation'], 'auth' => ['auth'], 'passwords' => ['passwords'], 'pagination' => ['pagination']];
    }

    #[DataProvider('frameworkFiles')]
    public function test_every_framework_message_has_an_indonesian_translation(string $file): void
    {
        $english = Arr::dot(require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php"));
        $indonesian = Arr::dot(require lang_path("id/{$file}.php"));

        $missing = array_diff(
            array_keys(Arr::except($english, ['custom.attribute-name.rule-name', 'attributes'])),
            array_keys($indonesian),
        );

        $this->assertSame([], array_values($missing), "Add these keys to lang/id/{$file}.php.");
    }

    public function test_validation_errors_are_shown_in_bahasa(): void
    {
        $errors = Validator::make(['email' => 'bukan-email'], ['email' => 'email', 'name' => 'required'])->errors();

        $this->assertSame('Email harus berupa alamat email yang valid.', $errors->first('email'));
        $this->assertSame('Nama wajib diisi.', $errors->first('name'));
    }

    public function test_home_page_copy_comes_from_lang_files(): void
    {
        // The landing page (F-017) takes all its copy from lang/id/landing.php.
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Home')
            ->where('copy', __('landing')));
    }
}
