<?php

/**
 * Covers GenerateMailThemeCss — the branded email theme CSS generator, and
 * StoreSetting's hook that keeps it in sync automatically.
 */

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Actions\Mail\GenerateMailThemeCss;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateMailThemeCssTest extends TestCase
{
    use RefreshDatabase;

    private function outputPath(): string
    {
        return resource_path('views/vendor/mail/html/themes/default.css');
    }

    public function test_it_substitutes_the_stores_primary_color_into_the_theme(): void
    {
        StoreSetting::current()->update(['primary_color' => '#ff0000']);

        GenerateMailThemeCss::run();

        $css = File::get($this->outputPath());
        $this->assertStringContainsString('#ff0000', $css);
        $this->assertStringNotContainsString('{{BRAND_PRIMARY}}', $css);
    }

    /**
     * Regenerating always reads from the untouched source template, never
     * from the previously-generated output — otherwise the second run
     * would find no {{BRAND_PRIMARY}} token left to replace (already
     * substituted by the first run) and silently keep serving the old
     * color forever.
     */
    public function test_regenerating_after_a_color_change_replaces_the_previous_color(): void
    {
        StoreSetting::current()->update(['primary_color' => '#111111']);
        GenerateMailThemeCss::run();
        $this->assertStringContainsString('#111111', File::get($this->outputPath()));

        StoreSetting::current()->update(['primary_color' => '#222222']);
        GenerateMailThemeCss::run();

        $css = File::get($this->outputPath());
        $this->assertStringContainsString('#222222', $css);
        $this->assertStringNotContainsString('#111111', $css);
    }

    public function test_falls_back_to_a_default_color_when_none_is_configured(): void
    {
        StoreSetting::current()->update(['primary_color' => null]);

        GenerateMailThemeCss::run();

        $css = File::get($this->outputPath());
        $this->assertStringContainsString('#18181b', $css);
    }

    public function test_saving_store_settings_automatically_regenerates_the_theme(): void
    {
        StoreSetting::current()->update(['primary_color' => '#abcdef']);

        $this->assertStringContainsString('#abcdef', File::get($this->outputPath()));
    }
}
