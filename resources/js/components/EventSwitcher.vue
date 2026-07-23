<script setup lang="ts">
import { type EventContext, type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// The current-event selector (D20). Passive when there's a single edition —
// nothing to switch, so it stays hidden.
const page = usePage<SharedData>();
const ctx = computed<EventContext | null>(() => page.props.eventContext);

function onChange(event: Event) {
    const id = Number((event.target as HTMLSelectElement).value);
    router.post('/current-event', { event_id: id }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <select
        v-if="ctx && ctx.options.length > 1"
        class="h-9 max-w-[12rem] truncate rounded-md border border-input bg-transparent px-3 text-sm"
        :value="ctx.current?.id"
        aria-label="Evento corrente"
        @change="onChange"
    >
        <option v-for="option in ctx.options" :key="option.id" :value="option.id">{{ option.name }}</option>
    </select>
</template>
