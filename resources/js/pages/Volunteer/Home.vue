<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    person: { name: string };
    tenant: { name: string };
}>();
</script>

<template>
    <Head title="I miei turni" />
    <div class="flex min-h-screen flex-col items-center justify-center gap-2 bg-background p-6 text-center">
        <h1 class="text-2xl font-semibold text-foreground">Ciao, {{ person.name }}!</h1>
        <p class="text-muted-foreground">{{ tenant.name }}</p>
        <p class="mt-6 text-foreground">Qui troverai i tuoi turni e potrai dare la tua disponibilità.</p>
        <p class="text-sm text-muted-foreground">Stiamo ancora preparando questa pagina.</p>
    </div>
</template>
