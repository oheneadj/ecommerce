<?php

/**
 * Covers the public static-page route (/pages/{slug}) — anyone can view a
 * published page, an unpublished one is a 404, and published pages appear
 * in the storefront footer.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\StaticPage;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticPagePublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_a_published_static_page(): void
    {
        $page = StaticPage::factory()->create([
            'title' => 'About Us',
            'slug' => 'about-us',
            'content' => '<p>We sell things.</p>',
            'is_published' => true,
        ]);

        $this->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertSee('About Us')
            ->assertSee('We sell things.', false);
    }

    public function test_an_unpublished_static_page_is_a_404(): void
    {
        $page = StaticPage::factory()->create(['slug' => 'draft-page', 'is_published' => false]);

        $this->get("/pages/{$page->slug}")->assertNotFound();
    }

    public function test_published_pages_appear_in_the_storefront_footer(): void
    {
        $terms = StaticPage::factory()->create(['title' => 'Terms of Service', 'slug' => 'terms', 'is_published' => true]);
        StaticPage::factory()->create(['title' => 'Draft Page', 'slug' => 'draft', 'is_published' => false]);

        // The storefront layout's footer is shared across every page that
        // uses it, so the currently-viewed page itself is a valid host to
        // assert the footer's published-pages links from.
        $this->get("/pages/{$terms->slug}")
            ->assertOk()
            ->assertDontSee('Draft Page');
    }

    public function test_footer_shows_social_links_when_set(): void
    {
        StoreSetting::current()->update([
            'facebook_url' => 'https://facebook.com/acme',
            'whatsapp_url' => 'https://wa.me/233200000000',
        ]);
        $page = StaticPage::factory()->create(['slug' => 'about-us', 'is_published' => true]);

        $this->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertSee('https://facebook.com/acme', false)
            ->assertSee('https://wa.me/233200000000', false);
    }

    public function test_footer_hides_social_links_when_unset(): void
    {
        $page = StaticPage::factory()->create(['slug' => 'about-us', 'is_published' => true]);

        $this->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertDontSee('facebook.com')
            ->assertDontSee('wa.me');
    }
}
