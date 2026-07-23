<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_api_docs_ui_renders_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->get('/docs/api')->assertOk();
    }
}
