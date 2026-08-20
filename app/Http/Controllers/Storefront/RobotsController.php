<?php

/**
 * Serves /robots.txt with an absolute sitemap URL — a plain static file
 * can't reference this deployment's own domain, and this app is deployed
 * per-business behind different domains (see StoreSetting's branding
 * fields for the same "one codebase, many deployments" reasoning).
 */

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /account',
            'Disallow: /wishlist',
            'Disallow: /login',
            'Disallow: /admin',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines))->header('Content-Type', 'text/plain');
    }
}
