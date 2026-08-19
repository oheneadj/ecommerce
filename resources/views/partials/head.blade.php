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

    // Canonical always points at the current path with no query string —
    // the product listing page alone has 5 #[Url]-bound filter/sort
    // params, so every filtered/sorted combination is otherwise a
    // separate crawlable URL for what's substantively the same page.
    // url()->current() already excludes the query string, so this is the
    // right canonical target with no extra stripping needed.
    $headCanonicalUrl = url()->current();

    // LocalBusiness schema is site-wide (every page is "this business's"
    // page), unlike the per-page $jsonLd override below (Product/
    // BreadcrumbList schema on the product page) — both can render
    // together since they're separate <script> blocks.
    $headLocalBusinessSchema = $headStore->hasStructuredAddress() ? [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $headBusinessName,
        'image' => $headImageUrl,
        'telephone' => $headStore->contact_phone,
        'email' => $headStore->contact_email,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $headStore->address_street,
            'addressLocality' => $headStore->address_city,
            'addressRegion' => $headStore->address_region,
            'postalCode' => $headStore->address_postal_code,
            'addressCountry' => $headStore->address_country,
        ],
        'geo' => ($headStore->latitude !== null && $headStore->longitude !== null) ? [
            '@type' => 'GeoCoordinates',
            'latitude' => $headStore->latitude,
            'longitude' => $headStore->longitude,
        ] : null,
    ] : null;
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ $headTitle }}
</title>

@if (filled($headDescription))
    <meta name="description" content="{{ $headDescription }}">
@endif

{{-- $noindex is an opt-in per-page prop (see cart/checkout/account views) — pages with zero search value are kept out of the index rather than merely hinted away via robots.txt. --}}
@if ($noindex ?? false)
    <meta name="robots" content="noindex, nofollow">
@endif

<link rel="canonical" href="{{ $headCanonicalUrl }}">

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

@if ($headLocalBusinessSchema !== null)
    <script type="application/ld+json">{!! json_encode(array_filter($headLocalBusinessSchema)) !!}</script>
@endif

{{-- Per-page structured data (Product/BreadcrumbList schema etc.) — see products/show.blade.php for a real example. --}}
@if (filled($jsonLd ?? null))
    @foreach ((array) $jsonLd as $jsonLdBlock)
        <script type="application/ld+json">{!! json_encode($jsonLdBlock) !!}</script>
    @endforeach
@endif

@if ($headStore->hasGoogleAnalytics())
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $headStore->ga_measurement_id }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $headStore->ga_measurement_id }}');
    </script>
@endif

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="{{ route('theme.css') }}">
