<script setup lang="ts">
// Small status chip. `good` = covered/full, `warn` = needs attention,
// `neutral` = informational.
withDefaults(defineProps<{ variant?: 'good' | 'warn' | 'neutral' }>(), { variant: 'neutral' });
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="{
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100': variant === 'good',
            'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100': variant === 'warn',
            'bg-muted text-muted-foreground': variant === 'neutral',
        }"
    >
        <slot />
    </span>
</template>
