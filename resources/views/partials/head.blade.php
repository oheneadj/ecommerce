@php
    // Self-contained rather than relying on the including layout to have
    // already defined $store (this partial is shared by the storefront
    // layout and the auth/settings layouts alike) — same pattern
    // layouts/storefront.blade.php uses.
    $headStore ??= \App\Models\StoreSetting::current();
    $headBusinessName = $headStore->business_name ?: config('app.name', 'Laravel');
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.$headBusinessName : $headBusinessName }}
</title>

@if ($headStore->logo_path)
    <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($headStore->logo_path) }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif

@fonts

<script>
    (function () {
        const stored = localStorage.getItem('appearance') ?? 'system';
        const isDark = stored === 'dark' || (stored === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', isDark);
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="{{ route('theme.css') }}">
