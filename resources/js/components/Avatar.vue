<script setup lang="ts">
import { avatarColor, initials } from '@/lib/colors';

// A name badge: initials on a deterministic color. `highlight` rings the
// current person ("you"); the default ring is just the background so stacked
// avatars read as separate discs.
const props = withDefaults(defineProps<{ name: string; size?: number; highlight?: boolean }>(), {
    size: 30,
    highlight: false,
});
</script>

<template>
    <div
        class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white ring-2"
        :class="highlight ? 'ring-foreground' : 'ring-background'"
        :style="{
            width: props.size + 'px',
            height: props.size + 'px',
            background: avatarColor(name),
            fontSize: Math.round(props.size * 0.36) + 'px',
        }"
        :title="name"
    >
        {{ initials(name) }}
    </div>
</template>
