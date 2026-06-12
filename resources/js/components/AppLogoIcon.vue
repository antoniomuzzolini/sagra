<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- Festival marquee tent silhouette -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 42" :class="className" v-bind="$attrs">
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M20 2c.6 0 1.2.3 1.6.8l16 22.5c.5.7.5 1.6.1 2.3L30.4 39c-.4.6-1 1-1.7 1H11.3c-.7 0-1.3-.4-1.7-1L2.3 27.6c-.4-.7-.4-1.6.1-2.3l16-22.5c.4-.5 1-.8 1.6-.8Zm0 5.4L7.6 24.9 12.4 36h4.1l2.6-9.3c.3-.9 1.5-.9 1.8 0l2.6 9.3h4.1l4.8-11.1L20 7.4Z"
        />
    </svg>
</template>
