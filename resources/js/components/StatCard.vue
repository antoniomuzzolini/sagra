<script setup lang="ts">
// A single headline figure with its label, for the overview strip.
defineProps<{ value: string | number; label: string; accent?: boolean }>();
</script>

<template>
    <div class="rounded-xl border bg-card p-4">
        <div class="text-2xl font-semibold" :class="accent ? 'text-amber-600 dark:text-amber-500' : ''">{{ value }}</div>
        <div class="mt-1 text-sm text-muted-foreground">{{ label }}</div>
    </div>
</template>
