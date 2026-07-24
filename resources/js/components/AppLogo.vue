<script setup lang="ts">
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <img src="/logo-mark.png" alt="Fieste" class="size-8 shrink-0 rounded-md object-cover" />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">{{ $page.props.name }}</span>
    </div>
</template>
