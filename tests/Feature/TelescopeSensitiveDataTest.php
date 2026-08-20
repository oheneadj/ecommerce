<?php

/**
 * Regression: Telescope's Request Watcher records a Livewire AJAX
 * request's full JSON body verbatim — including a customer's OTP code or
 * a password field — unless the parameter is explicitly hidden. Since
 * this app's auth flows are almost entirely Livewire components (not
 * classic form posts), the stock Telescope stub's default redaction list
 * wasn't enough on its own.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Laravel\Telescope\Telescope;
use Tests\TestCase;

class TelescopeSensitiveDataTest extends TestCase
{
    public function test_sensitive_request_parameters_are_hidden_from_telescope(): void
    {
        foreach (['password', 'password_confirmation', 'current_password', '_token'] as $parameter) {
            $this->assertContains($parameter, Telescope::$hiddenRequestParameters);
        }
    }

    /**
     * Livewire's request body nests a component's public property state
     * (e.g. an OTP `$code`, a `$password`) inside a JSON-encoded
     * `components[].snapshot` string — Telescope's own redaction can only
     * reach literal top-level keys, never into that encoded string, so
     * naming individual property names would silently redact nothing.
     * The entire `components` payload must be hidden instead.
     */
    public function test_the_entire_livewire_payload_is_hidden_from_telescope(): void
    {
        $this->assertContains('components', Telescope::$hiddenRequestParameters);
    }
}
