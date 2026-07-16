<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

// Native date/time fields only open their picker from the tiny icon; on a
// phone that's a fiddly target. Open it from anywhere in the field instead.
// showPicker needs a user gesture and may be unsupported — best effort.
function openPicker(event: MouseEvent) {
    const el = event.currentTarget as HTMLInputElement;
    if (['date', 'time', 'datetime-local', 'month', 'week'].includes(el.type)) {
        try {
            el.showPicker?.();
        } catch {
            // The native icon still works.
        }
    }
}
</script>

<template>
    <input
        v-model="modelValue"
        @click="openPicker"
        :class="
            cn(
                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                props.class,
            )
        "
    />
</template>
