{{--
    A simple accept-and-dismiss notice, not a granular consent manager —
    this store doesn't set any analytics/marketing cookies today, only
    the session/auth cookies required for the site to function at all
    (which don't legally require consent). Revisit as a real
    category-based consent flow if/when tracking cookies are ever added.
--}}
<div
    x-data="{ show: false }"
    x-init="show = ! localStorage.getItem('cookie-consent-accepted')"
    x-show="show"
    x-cloak
    x-transition
    {{-- bottom-16 on mobile clears the fixed bottom tab bar (partials.mobile-bottom-nav) instead of covering it — sm:bottom-0 since that bar doesn't exist at sm:+. --}}
    class="fixed inset-x-0 bottom-16 z-40 border-t border-zinc-200 bg-white px-4 py-4 shadow-lg sm:bottom-0 sm:px-6 dark:border-zinc-700 dark:bg-zinc-900"
>
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-sm text-zinc-600 dark:text-zinc-300">
            {{ __('We use cookies to keep you signed in and remember your cart. By continuing to use this site, you agree to this.') }}
        </p>

        <x-button
            type="button"
            variant="primary"
            class="shrink-0"
            x-on:click="localStorage.setItem('cookie-consent-accepted', '1'); show = false"
        >
            {{ __('Accept') }}
        </x-button>
    </div>
</div>
