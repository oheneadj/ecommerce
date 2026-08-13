@props(['store' => null, 'businessName' => null])
@php
    $store ??= \App\Models\StoreSetting::current();
    $businessName ??= $store->business_name ?: config('app.name', 'Laravel');
    $contactLine = collect([$store->contact_email, $store->contact_phone])->filter()->implode(' · ');
@endphp
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<strong>{{ $businessName }}</strong>
@if ($store->tagline)
<br>{{ $store->tagline }}
@endif
@if ($contactLine !== '')
<br>{{ $contactLine }}
@endif
@if ($store->contact_address)
<br>{{ $store->contact_address }}
@endif
<br><br>
&copy; {{ date('Y') }} {{ $businessName }}. {{ __('All rights reserved.') }}
</td>
</tr>
</table>
</td>
</tr>
