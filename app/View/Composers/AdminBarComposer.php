<?php

/**
 * Computes everything the WordPress-style admin bar needs to render, keeping the Blade partial pure markup.
 */

declare(strict_types=1);

namespace App\View\Composers;

use App\Actions\Health\DetermineCriticalHealthFailure;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Pages\SystemHealth;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\StaticPage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Individual bar items are additionally gated by their model's real Policy
 * (not just the bar's own Admin/Super-Admin role check), so the bar
 * automatically follows any future policy change instead of duplicating
 * authorization logic — see resources/views/partials/admin-bar.blade.php's
 * own comment for the full reasoning.
 */
class AdminBarComposer
{
    /**
     * @var array<int, array{label: string, route: string, model: class-string}>
     */
    private const NEW_ITEMS = [
        ['label' => 'Product', 'route' => 'filament.admin.resources.products.create', 'model' => Product::class],
        ['label' => 'Category', 'route' => 'filament.admin.resources.categories.create', 'model' => Category::class],
        ['label' => 'Brand', 'route' => 'filament.admin.resources.brands.create', 'model' => Brand::class],
        ['label' => 'Coupon', 'route' => 'filament.admin.resources.coupons.create', 'model' => Coupon::class],
        ['label' => 'Shipping method', 'route' => 'filament.admin.resources.shipping-methods.create', 'model' => ShippingMethod::class],
        ['label' => 'Static page', 'route' => 'filament.admin.resources.static-pages.create', 'model' => StaticPage::class],
    ];

    public function compose(View $view): void
    {
        $user = Auth::user();
        $isSuperAdminOrAdmin = $user?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;

        if (! $isSuperAdminOrAdmin) {
            $view->with('isSuperAdminOrAdmin', false);

            return;
        }

        $canViewOrders = $user->can('viewAny', Order::class);

        $view->with([
            'isSuperAdminOrAdmin' => true,
            'adminBarUser' => $user,
            'isSuperAdmin' => $user->hasRole(UserRole::SuperAdmin->value),
            'criticalHealthFailing' => DetermineCriticalHealthFailure::run(),
            'canViewOrders' => $canViewOrders,
            'canManageCache' => $isSuperAdminOrAdmin,
            'newItems' => collect(self::NEW_ITEMS)->filter(fn (array $item): bool => $user->can('create', $item['model'])),
            'pendingOrders' => $canViewOrders ? $this->pendingOrders() : collect(),
            'pendingOrdersCount' => $canViewOrders ? Order::query()->where('status', OrderStatus::Pending)->count() : 0,
            'systemHealthUrl' => SystemHealth::getUrl(),
        ]);
    }

    /**
     * @return Collection<int, Order>
     */
    private function pendingOrders(): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::Pending)
            ->latest()
            ->limit(5)
            ->get(['id', 'ulid', 'order_number', 'guest_email', 'user_id', 'grand_total']);
    }
}
