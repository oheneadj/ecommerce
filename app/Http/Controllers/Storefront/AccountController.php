<?php

/**
 * The customer's account dashboard — a themed hub, not the profile-editing
 * form itself (that stays at /settings/profile).
 */

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function show(): View
    {
        $orders = Auth::user()->orders()->latest()->limit(5)->get();

        return view('account.show', ['orders' => $orders]);
    }
}
