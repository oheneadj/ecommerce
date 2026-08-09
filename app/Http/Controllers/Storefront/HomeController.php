<?php

/**
 * The public storefront homepage — featured categories and new arrivals.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\VariantStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function show(): View
    {
        $categories = Category::query()->whereNull('parent_id')->orderBy('name')->limit(6)->get();

        $newProducts = Product::query()
            ->active()
            ->whereHas('variants', fn ($query) => $query->where('status', VariantStatus::Active)->where('stock', '>', 0))
            ->with(['images', 'variants' => fn ($query) => $query->where('status', VariantStatus::Active)->orderBy('price')])
            ->latest()
            ->limit(8)
            ->get();

        return view('home', ['categories' => $categories, 'newProducts' => $newProducts]);
    }
}
