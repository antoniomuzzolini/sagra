<script setup lang="ts">
// A single headline figure with its label, for the overview strip. When
// `interactive` it renders as a button and reacts to hover, so a figure can
// double as a shortcut (e.g. "Da coprire" → Gestione turni).
defineProps<{ value: string | number; label: string; accent?: boolean; interactive?: boolean }>();
</script>

<template>
    <component
        :is="interactive ? 'button' : 'div'"
        :type="interactive ? 'button' : undefined"
        class="rounded-xl border bg-card p-4 text-left"
        :class="interactive ? 'transition hover:border-foreground/30 hover:shadow-sm' : ''"
    >
        <div class="text-2xl font-semibold" :class="accent ? 'text-amber-600 dark:text-amber-500' : ''">{{ value }}</div>
        <div class="mt-1 text-sm text-muted-foreground">{{ label }}</div>
    </component>
</template>
