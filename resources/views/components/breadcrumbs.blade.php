@props(['items'])

{{--
    $items: array of ['label' => string, 'url' => string|null].
    A null/omitted `url` renders as plain text (the current page) rather
    than a link — always true for the last item, but callers decide.
--}}
<nav aria-label="{{ __('Breadcrumb') }}" class="mb-4 text-sm text-zinc-500">
    <ol class="flex flex-wrap items-center gap-1.5">
        @foreach ($items as $index => $item)
            <li class="flex items-center gap-1.5">
                @if ($index > 0)
                    <span aria-hidden="true">/</span>
                @endif

                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" wire:navigate class="hover:text-brand-primary hover:underline">{{ $item['label'] }}</a>
                @else
                    <span class="text-zinc-700" @if($index === count($items) - 1) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
