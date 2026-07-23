@props(['name', 'length' => 6, 'model' => null, 'wireModel' => null])

<div
    x-data="{
        length: {{ $length }},
        digits: Array({{ $length }}).fill(''),
        get value() { return this.digits.join('') },
        onInput(i, e) {
            const v = e.target.value.replace(/\D/g, '').slice(-1);
            this.digits[i] = v;
            if (v && i < this.length - 1) this.$refs['digit' + (i + 1)].focus();
        },
        onKeydown(i, e) {
            if (e.key === 'Backspace' && !this.digits[i] && i > 0) {
                this.$refs['digit' + (i - 1)].focus();
            }
        },
        onPaste(e) {
            const chars = (e.clipboardData.getData('text') || '').replace(/\D/g, '').split('').slice(0, this.length);
            chars.forEach((c, i) => this.digits[i] = c);
            this.$nextTick(() => this.$refs['digit' + Math.min(chars.length, this.length - 1)]?.focus());
        },
    }"
    x-init="$watch('value', (v) => { $refs.hidden.value = v; $refs.hidden.dispatchEvent(new Event('input')) })"
    class="flex justify-center gap-2"
>
    <template x-for="(digit, i) in digits" :key="i">
        <input
            type="text"
            inputmode="numeric"
            maxlength="1"
            x-bind:ref="'digit' + i"
            x-bind:value="digit"
            x-on:input="onInput(i, $event)"
            x-on:keydown="onKeydown(i, $event)"
            x-on:paste.prevent="onPaste($event)"
            class="size-11 rounded-lg border border-zinc-300 text-center text-lg font-medium focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
        />
    </template>

    <input
        type="hidden"
        name="{{ $name }}"
        x-ref="hidden"
        @if($model) x-model="{{ $model }}" @endif
        @if($wireModel) wire:model="{{ $wireModel }}" @endif
    />
</div>
