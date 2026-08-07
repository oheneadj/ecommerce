<?php

/**
 * Covers that the session cookie's `secure` flag has an explicit default —
 * previously `env('SESSION_SECURE_COOKIE')` had none, meaning a deploy
 * that forgot to set it would silently send the session cookie over
 * plain HTTP.
 */

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class SessionConfigTest extends TestCase
{
    public function test_the_secure_cookie_flag_is_not_null_by_default_outside_an_explicit_env_setting(): void
    {
        $this->assertNotNull(config('session.secure'));
    }

    public function test_the_secure_cookie_flag_defaults_based_on_the_environment_not_a_bare_env_call(): void
    {
        $source = file_get_contents(config_path('session.php'));

        $this->assertStringContainsString(
            "env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')",
            $source,
            "config/session.php's 'secure' key must default based on APP_ENV, not fall back to null.",
        );
    }
}
