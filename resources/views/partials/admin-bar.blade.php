{{--
    A WordPress-style admin bar for Super Admin/Admin, shown above both the
    Filament panel and the public storefront layout. Unlike the panel's own
    sidebar (which only lists pages), this surfaces one-click *actions* —
    jump straight into a "create" form, or glance at what's pending —
    without navigating into the panel first. Styled with inline CSS rather
    than Tailwind utility classes since it's shared between two separate
    CSS pipelines (Filament's own compiled assets vs. the storefront's Vite
    build) that don't purge for each other's Blade files, and uses
    CSS-only `:hover` dropdowns so it needs no JS/Livewire dependency in
    either context.
--}}
@php
    $adminBarUser = auth()->user();
    // The bar itself is Admin/Super Admin only — Store Keeper and
    // customers never see it. Individual items below are additionally
    // gated by their model's real Policy (not just this role check), so
    // the bar automatically follows any future policy change instead of
    // duplicating authorization logic.
    $isSuperAdminOrAdmin = $adminBarUser?->hasAnyRole([\App\Enums\UserRole::SuperAdmin->value, \App\Enums\UserRole::Admin->value]) ?? false;

    if ($isSuperAdminOrAdmin) {
        $canViewOrders = $adminBarUser->can('viewAny', \App\Models\Order::class);
        $canManageCache = $adminBarUser->hasAnyRole([\App\Enums\UserRole::SuperAdmin->value, \App\Enums\UserRole::Admin->value]);

        $newItems = collect([
            ['label' => 'Product', 'route' => 'filament.admin.resources.products.create', 'model' => \App\Models\Product::class],
            ['label' => 'Category', 'route' => 'filament.admin.resources.categories.create', 'model' => \App\Models\Category::class],
            ['label' => 'Brand', 'route' => 'filament.admin.resources.brands.create', 'model' => \App\Models\Brand::class],
            ['label' => 'Coupon', 'route' => 'filament.admin.resources.coupons.create', 'model' => \App\Models\Coupon::class],
            ['label' => 'Shipping method', 'route' => 'filament.admin.resources.shipping-methods.create', 'model' => \App\Models\ShippingMethod::class],
            ['label' => 'Static page', 'route' => 'filament.admin.resources.static-pages.create', 'model' => \App\Models\StaticPage::class],
        ])->filter(fn (array $item) => $adminBarUser->can('create', $item['model']));

        if ($canViewOrders) {
            $pendingOrders = \App\Models\Order::query()
                ->where('status', \App\Enums\OrderStatus::Pending)
                ->latest()
                ->limit(5)
                ->get(['id', 'ulid', 'order_number', 'guest_email', 'user_id', 'grand_total']);
            $pendingOrdersCount = \App\Models\Order::query()->where('status', \App\Enums\OrderStatus::Pending)->count();
        }
    }
@endphp
@if ($isSuperAdminOrAdmin)
    <div class="wp-admin-bar">
        <style>
            .wp-admin-bar {
                position: relative;
                /* Filament's own topbar/sidebar use z-30 and its
                   notification toasts use z-50 — 40 sits above the panel's
                   nav chrome but stays below notifications, so a toast is
                   never covered by this bar. */
                z-index: 40;
                display: flex;
                align-items: center;
                justify-content: space-between;
                height: 32px;
                padding: 0 12px;
                background: #1d2327;
                color: #eee;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 13px;
                line-height: 32px;
            }
            .wp-admin-bar a {
                color: #eee;
                text-decoration: none;
                padding: 0 8px;
                display: inline-flex;
                align-items: center;
                height: 32px;
                white-space: nowrap;
            }
            .wp-admin-bar a:hover {
                background: #2c3338;
                color: #72aee6;
            }
            .wp-admin-bar-group {
                display: flex;
                align-items: center;
                height: 32px;
            }
            .wp-admin-bar form {
                display: inline;
                margin: 0;
            }
            .wp-admin-bar button {
                background: none;
                border: none;
                color: #eee;
                font: inherit;
                cursor: pointer;
                padding: 0 8px;
                height: 32px;
            }
            .wp-admin-bar button:hover {
                background: #2c3338;
                color: #72aee6;
            }
            .wp-admin-bar-sep {
                width: 1px;
                height: 16px;
                background: #444;
                margin: 0 4px;
                flex-shrink: 0;
            }
            .wp-admin-bar-role {
                color: #999;
                padding: 0 8px;
                white-space: nowrap;
            }
            .wp-admin-bar-item {
                position: relative;
                height: 32px;
            }
            .wp-admin-bar-item > a,
            .wp-admin-bar-item > .wp-admin-bar-trigger {
                cursor: default;
            }
            .wp-admin-bar-dropdown {
                display: none;
                position: absolute;
                top: 32px;
                left: 0;
                min-width: 220px;
                background: #2c3338;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
                flex-direction: column;
                padding: 4px 0;
            }
            .wp-admin-bar-item:hover .wp-admin-bar-dropdown {
                display: flex;
            }
            .wp-admin-bar-dropdown a {
                height: auto;
                padding: 6px 12px;
                display: flex;
                justify-content: space-between;
                gap: 12px;
            }
            .wp-admin-bar-dropdown form {
                display: block;
            }
            .wp-admin-bar-dropdown form button {
                width: 100%;
                height: auto;
                padding: 6px 12px;
                text-align: left;
                justify-content: flex-start;
            }
            .wp-admin-bar-dropdown-empty {
                padding: 6px 12px;
                color: #999;
            }
            .wp-admin-bar-dropdown-sep {
                height: 1px;
                background: #444;
                margin: 4px 0;
            }
            .wp-admin-bar-flash {
                background: #2c3338;
                color: #72aee6;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 13px;
                padding: 6px 12px;
            }
            .wp-admin-bar-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 16px;
                height: 16px;
                padding: 0 4px;
                margin-left: 4px;
                border-radius: 8px;
                background: #d63638;
                color: #fff;
                font-size: 11px;
                line-height: 16px;
            }
        </style>

        <div class="wp-admin-bar-group">
            @if (request()->is('admin*'))
                <a href="{{ route('home') }}" style="display: inline-flex; align-items: center; gap: 6px;">
                    <x-app-icon name="globe" style="width: 14px; height: 14px; flex-shrink: 0;" />
                    View Site
                </a>
            @else
                <a href="{{ route('filament.admin.pages.dashboard') }}" style="display: inline-flex; align-items: center; gap: 6px;">
                    <x-app-icon name="cog" style="width: 14px; height: 14px; flex-shrink: 0;" />
                    Admin Dashboard
                </a>
            @endif

            @if ($newItems->isNotEmpty())
                <span class="wp-admin-bar-sep"></span>

                <div class="wp-admin-bar-item">
                    <span class="wp-admin-bar-trigger" style="padding: 0 8px; display: inline-flex; align-items: center; gap: 6px; height: 32px;">
                        <x-app-icon name="plus" style="width: 14px; height: 14px; flex-shrink: 0;" />
                        New
                    </span>
                    <div class="wp-admin-bar-dropdown">
                        @foreach ($newItems as $item)
                            <a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($canViewOrders)
                <span class="wp-admin-bar-sep"></span>

                <div class="wp-admin-bar-item">
                    <span class="wp-admin-bar-trigger" style="padding: 0 8px; display: inline-flex; align-items: center; gap: 6px; height: 32px;">
                        <x-app-icon name="clock" style="width: 14px; height: 14px; flex-shrink: 0;" />
                        Pending orders
                        @if ($pendingOrdersCount > 0)
                            <span class="wp-admin-bar-badge">{{ $pendingOrdersCount }}</span>
                        @endif
                    </span>
                    <div class="wp-admin-bar-dropdown">
                        @forelse ($pendingOrders as $order)
                            <a href="{{ route('filament.admin.resources.orders.view', ['record' => $order]) }}">
                                <span>{{ $order->order_number }}</span>
                                <span>{{ $order->grand_total_formatted }}</span>
                            </a>
                        @empty
                            <span class="wp-admin-bar-dropdown-empty">No pending orders</span>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($canManageCache)
                <span class="wp-admin-bar-sep"></span>

                <div class="wp-admin-bar-item">
                    <span class="wp-admin-bar-trigger" style="padding: 0 8px; display: inline-flex; align-items: center; gap: 6px; height: 32px;">
                        <x-app-icon name="arrow-path" style="width: 14px; height: 14px; flex-shrink: 0;" />
                        Cache
                    </span>
                    <div class="wp-admin-bar-dropdown">
                        <form method="POST" action="{{ route('system.cache.run', ['action' => 'config']) }}">
                            @csrf
                            <button type="submit">Clear config cache</button>
                        </form>
                        <form method="POST" action="{{ route('system.cache.run', ['action' => 'route']) }}">
                            @csrf
                            <button type="submit">Clear route cache</button>
                        </form>
                        <form method="POST" action="{{ route('system.cache.run', ['action' => 'view']) }}">
                            @csrf
                            <button type="submit">Clear view cache</button>
                        </form>
                        <form method="POST" action="{{ route('system.cache.run', ['action' => 'event']) }}">
                            @csrf
                            <button type="submit">Clear event cache</button>
                        </form>
                        <div class="wp-admin-bar-dropdown-sep"></div>
                        <form method="POST" action="{{ route('system.cache.run', ['action' => 'all']) }}">
                            @csrf
                            <button type="submit">Clear all caches</button>
                        </form>
                        <form method="POST" action="{{ route('system.cache.run', ['action' => 'optimize']) }}">
                            @csrf
                            <button type="submit">Rerun cache &amp; optimize</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="wp-admin-bar-group">
            <span class="wp-admin-bar-role">{{ $adminBarUser->name }}</span>
            <form method="POST" action="{{ request()->is('admin*') ? route('filament.admin.auth.logout') : route('logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </div>
    </div>

    @if (session('cache_status'))
        <div class="wp-admin-bar-flash" style="display: flex; align-items: center; gap: 6px;">
            <x-app-icon name="check" style="width: 14px; height: 14px; flex-shrink: 0;" />
            {{ session('cache_status') }}
        </div>
    @endif
@endif
