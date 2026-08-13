@props(['store'])

@if ($store->showsWhatsappChatBubble())
    {{--
        start-4 (left), not end-4 (right) — the toast container already
        occupies bottom end-4 (see components/toast-container.blade.php),
        and this bubble is persistent rather than transient, so the two
        must never stack on the same corner. Same bottom-20/sm:bottom-4
        offset as the toast container to clear the fixed mobile tab bar.
    --}}
    <a
        href="{{ $store->whatsapp_url }}"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="{{ __('Chat with us on WhatsApp') }}"
        class="fixed bottom-20 start-4 z-40 flex size-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg transition-transform duration-150 ease-out hover:-translate-y-0.5 sm:bottom-4"
    >
        <x-app-icon name="whatsapp" class="size-7" />
    </a>
@endif
