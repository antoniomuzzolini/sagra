<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <main
        :class="
            cn(
                // min-w-0: as a flex child, without it the main area keeps its
                // content's min-content width and overflows the viewport on
                // mobile (truncate/wrap inside never gets a chance to shrink).
                'relative flex min-h-svh min-w-0 flex-1 flex-col bg-background',
                'peer-data-[variant=inset]:min-h-[calc(100svh-theme(spacing.4))] md:peer-data-[variant=inset]:m-2 md:peer-data-[state=collapsed]:peer-data-[variant=inset]:ml-2 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-xl md:peer-data-[variant=inset]:shadow',
                props.class,
            )
        "
    >
        <slot />
    </main>
</template>
