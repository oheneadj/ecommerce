@php
    // Self-contained rather than relying on the including layout to have
    // already defined $store (this partial is shared by the storefront
    // layout and the auth/settings layouts alike) — same pattern
    // layouts/storefront.blade.php uses.
    $headStore ??= \App\Models\StoreSetting::current();
    $headBusinessName = $headStore->business_name ?: config('app.name', 'Laravel');
    $headTitle = filled($title ?? null) ? $title.' - '.$headBusinessName : $headBusinessName;

    // Open Graph/Twitter Card tags were entirely absent before — with no
    // og:image anywhere, a link-preview crawler (WhatsApp, Facebook,
    // iMessage, etc.) falls back to whatever image it can find on the
    // page, which in practice meant the header logo (the first <img> in
    // the DOM on every page) instead of the actual thing being shared.
    // $ogDescription/$ogImage are optional per-page overrides (see
    // products/show.blade.php and pages/static-page-show.blade.php for
    // real examples) — every page still gets a sane default so sharing
    // the homepage or a generic page never has a blank preview either.
    $headDescription = filled($ogDescription ?? null) ? $ogDescription : $headStore->tagline;
    $headImageUrl = filled($ogImage ?? null)
        ? $ogImage
        : ($headStore->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($headStore->logo_path) : null);
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ $headTitle }}
</title>

@if (filled($headDescription))
    <meta name="description" content="{{ $headDescription }}">
@endif

<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="{{ $headBusinessName }}">
<meta property="og:title" content="{{ $headTitle }}">
<meta property="og:url" content="{{ url()->current() }}">
@if (filled($headDescription))
    <meta property="og:description" content="{{ $headDescription }}">
@endif
@if (filled($headImageUrl))
    <meta property="og:image" content="{{ $headImageUrl }}">
@endif

<meta name="twitter:card" content="{{ filled($headImageUrl) ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $headTitle }}">
@if (filled($headDescription))
    <meta name="twitter:description" content="{{ $headDescription }}">
@endif
@if (filled($headImageUrl))
    <meta name="twitter:image" content="{{ $headImageUrl }}">
@endif

@if ($headStore->logo_path)
    <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($headStore->logo_path) }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="{{ route('theme.css') }}">
