<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    public static function guestPages(): array
    {
        return [
            ['/'],
            ['/login'],
            ['/login/phone'],
            ['/register'],
            ['/admin/login'],
            ['/theme.css'],
        ];
    }

    #[DataProvider('guestPages')]
    public function test_guest_page_renders_without_error(string $path): void
    {
        config(['app.debug' => true]);

        $this->get($path)->assertOk();
    }
}
