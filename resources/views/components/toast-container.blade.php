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
            x-transition
            class="rounded-lg px-4 py-3 text-sm shadow-lg"
            x-bind:class="toast.variant === 'success' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-800 text-white'"
            x-text="toast.text"
        ></div>
    </template>
</div>
