<?php

/**
 * test-plan-ecommerce.md §11.1 — the Scramble OpenAPI document must
 * regenerate cleanly from current routes/Form Requests. There's no
 * dedicated `routes/api.php` in this app yet, so a document with no `paths`
 * key is the correct result today; what this actually guards against is the
 * generator itself throwing (e.g. a Form Request/route change it can't
 * introspect) — a failure of that kind would otherwise only surface the
 * next time someone happened to open `/docs/api` by hand.
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use Dedoc\Scramble\Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_openapi_document_generates_without_error(): void
    {
        $document = app(Generator::class)();

        $this->assertIsArray($document);
        $this->assertArrayHasKey('openapi', $document);
        $this->assertArrayHasKey('info', $document);
    }
}
