<?php

/**
 * Publicly renders an admin-authored content page (About, Contact, Terms, etc.).
 */

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use Illuminate\Contracts\View\View;

class StaticPageController extends Controller
{
    public function show(StaticPage $staticPage): View
    {
        abort_unless($staticPage->is_published, 404);

        return view('pages.static-page-show', ['page' => $staticPage]);
    }
}
