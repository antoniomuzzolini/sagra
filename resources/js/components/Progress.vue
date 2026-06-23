<script setup lang="ts">
// Coverage bar. Green once full, amber while there are gaps.
const props = withDefaults(defineProps<{ value: number; full?: boolean }>(), { full: false });
</script>

<template>
    <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
        <div
            class="h-full rounded-full transition-all"
            :class="props.full ? 'bg-green-500' : 'bg-amber-500'"
            :style="{ width: Math.min(100, Math.max(0, props.value)) + '%' }"
        />
    </div>
</template>
