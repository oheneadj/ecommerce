<?php

/**
 * The customer's account dashboard — a themed hub, not the profile-editing
 * form itself (that stays at /settings/profile).
 */

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class AccountController extends Controller
{
    public function show(): View
    {
        return view('account.show');
    }
}
