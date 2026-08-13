<?php

/**
 * The public storefront homepage — featured brands, categories, and new
 * arrivals.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function show(): View
    {
        // Only brands that actually have a logo and at least one
        // purchasable product — an unbranded/logo-less entry would just
        // be an empty tile in a section whose whole point is showing
        // logos.
        $brands = Brand::query()
            ->whereNotNull('logo_path')
            ->whereHas('products', fn ($query) => $query->where('status', ProductStatus::Active))
            ->orderBy('name')
            ->limit(8)
            ->get();

        $categories = Category::query()->whereNull('parent_id')->orderBy('name')->limit(6)->get();

        $newProducts = Product::query()
            ->active()
            ->whereHas('variants', fn ($query) => $query->where('status', VariantStatus::Active)->where('stock', '>', 0))
            ->with([
                'images',
                'variants' => fn ($query) => $query->where('status', VariantStatus::Active)->orderBy('price'),
                'variants.images',
            ])
            ->latest()
            ->limit(8)
            ->get();

        return view('home', [
            'brands' => $brands,
            'categories' => $categories,
            'newProducts' => $newProducts,
        ]);
    }
}
