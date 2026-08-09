<div
    x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, text: detail.message ?? detail.text ?? '', variant: detail.variant ?? 'default' });
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 4000);
        },
    }"
    x-on:toast.window="add($event.detail)"
    class="fixed bottom-4 end-4 z-[100] flex flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="rounded-lg px-4 py-3 text-sm shadow-lg"
            x-bind:class="toast.variant === 'success' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-800 text-white'"
            x-text="toast.text"
        ></div>
    </template>
</div>
