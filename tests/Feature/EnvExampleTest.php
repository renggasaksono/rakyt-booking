<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * F-009 A23: every env variable the config reads is listed in .env.example
 * (placeholder values only), and .env stays out of git.
 */
class EnvExampleTest extends TestCase
{
    public function test_ac_a23_env_example_lists_every_env_key_read_by_config(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));
        preg_match_all('/^#?\s*([A-Z0-9_]+)=/m', $example, $matches);
        $listed = array_unique($matches[1]);

        $missing = [];
        foreach (Finder::create()->files()->in(config_path())->name('*.php') as $file) {
            preg_match_all("/env\(\s*'([A-Z0-9_]+)'/", $file->getContents(), $keys);
            foreach ($keys[1] as $key) {
                if (! in_array($key, $listed, true)) {
                    $missing[] = "{$key} ({$file->getFilename()})";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Add these keys to .env.example.');
    }

    public function test_ac_a23_env_example_has_no_real_secrets_and_env_is_ignored(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $example);
        $this->assertDoesNotMatchRegularExpression('/^(AWS_SECRET_ACCESS_KEY|DB_PASSWORD|MAIL_PASSWORD|SENTRY_LARAVEL_DSN)=(?!$|null$).+/m', $example);
        $this->assertMatchesRegularExpression('/^\.env$/m', (string) file_get_contents(base_path('.gitignore')));
    }
}
