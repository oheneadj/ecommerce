<?php

/**
 * Runs cache-management Artisan commands from the admin bar's "Cache"
 * action — Admin/Super Admin only (Store Keeper and customers never see
 * or can hit this route), since these affect the whole application's
 * runtime state, not just the acting user's own data.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class SystemCacheController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const COMMANDS = [
        'config' => 'config:clear',
        'route' => 'route:clear',
        'view' => 'view:clear',
        'event' => 'event:clear',
        'all' => 'optimize:clear',
        'optimize' => 'optimize',
    ];

    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'config' => 'Config cache cleared',
        'route' => 'Route cache cleared',
        'view' => 'View cache cleared',
        'event' => 'Event cache cleared',
        'all' => 'All caches cleared',
        'optimize' => 'Application optimized — caches rebuilt',
    ];

    public function run(string $action): RedirectResponse
    {
        abort_unless(Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]), 403);
        abort_unless(array_key_exists($action, self::COMMANDS), 404);

        Artisan::call(self::COMMANDS[$action]);

        return back()->with('cache_status', self::LABELS[$action]);
    }
}
