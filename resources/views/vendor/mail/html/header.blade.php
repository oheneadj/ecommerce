@props(['url', 'store' => null, 'businessName' => null])
@php
    $store ??= \App\Models\StoreSetting::current();
    $businessName ??= $store->business_name ?: config('app.name', 'Laravel');
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($store->logo_path)
<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($store->logo_path) }}" class="logo" alt="{{ $businessName }}">
@else
<span class="logo-text">{{ $businessName }}</span>
@endif
</a>
</td>
</tr>
