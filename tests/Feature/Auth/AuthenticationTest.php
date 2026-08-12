<?php

namespace Tests\Feature\Auth;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    /**
     * The auth layouts previously didn't include /theme.css at all (unlike
     * the storefront layout), so buttons/links using brand-primary silently
     * fell back to the default zinc color instead of the store's configured
     * brand color on every auth page.
     */
    public function test_login_screen_loads_the_stores_theme_stylesheet(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeHtml('href="'.route('theme.css').'"');
    }

    /**
     * The auth layouts previously always rendered `<x-app-logo-icon>` — a
     * generic Laravel-branded mark, never the deployment's own logo — no
     * matter what was configured in Store Settings. Falls back to the
     * business name text when no logo has been uploaded.
     */
    public function test_login_screen_shows_the_stores_business_name_when_no_logo_is_set(): void
    {
        StoreSetting::current()->update(['business_name' => 'Acme Cosmetics', 'logo_path' => null]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Acme Cosmetics')
            ->assertDontSeeText('Laravel');
    }

    public function test_login_screen_shows_the_stores_uploaded_logo_image(): void
    {
        Storage::fake('public');
        $logo = UploadedFile::fake()->image('logo.png');
        $path = (string) $logo->store('branding', 'public');
        StoreSetting::current()->update(['logo_path' => $path]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSeeHtml(Storage::disk('public')->url($path));
    }

    /**
     * Every auth layout hardcoded `class="dark"` on `<html>`, always
     * forcing dark mode on first paint regardless of the visitor's actual
     * stored/system preference — the appearance-toggle script in
     * partials/head.blade.php would then flip it back off for a light
     * preference, but the layout should carry no default of its own, same
     * as the storefront layout.
     */
    public function test_login_screen_does_not_hardcode_dark_mode(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSeeHtml('<html lang="en" class="dark">');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.show', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    /**
     * `<x-input>` derives which field an error belongs to from a
     * wire:model attribute — but the login form is a plain Fortify POST,
     * bound via `name`, not wire:model. Without a `name` fallback, the
     * derived field key stays null, and `@error(null)` matches *any*
     * error in the bag — so a wrong-password error rendered under BOTH
     * the email and the password field, not just the one it belongs to.
     */
    public function test_an_invalid_password_error_is_shown_once_not_under_every_field(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'));

        $errorMessage = session('errors')['default']['messages']['email'][0];

        $page = $this->get(route('login'));

        $page->assertSee($errorMessage, escape: false);
        $this->assertSame(
            1,
            substr_count((string) $page->getContent(), $errorMessage),
            'The login error should appear exactly once, not duplicated under every input.',
        );
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
