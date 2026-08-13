{{--
    The shared wrapper every App\Notifications\*::toMail() renders through
    (Laravel's Markdown mail message system) — branding lives here once,
    rather than duplicated per notification class. Business details are
    read live from StoreSetting, the same "this deployment's current
    letterhead, not a fact frozen at send time" reasoning the PDF invoice
    already follows.
--}}
@php
    $mailStore = \App\Models\StoreSetting::current();
    $mailBusinessName = $mailStore->business_name ?: config('app.name', 'Laravel');
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" :store="$mailStore" :business-name="$mailBusinessName" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer :store="$mailStore" :business-name="$mailBusinessName" />
</x-slot:footer>
</x-mail::layout>
