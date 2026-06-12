<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    currentName: string;
    newName: string;
    token: string;
}>();

function switchIdentity() {
    router.post(route('magic-link.switch', props.token));
}
</script>

<template>
    <Head title="Chi sei?" />
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-center text-foreground">
        <div class="max-w-sm">
            <h1 class="text-2xl font-semibold">Un attimo: chi sei?</h1>
            <p class="mt-2 text-muted-foreground">
                Su questo telefono sei <strong>{{ props.currentName }}</strong
                >, ma il link che hai aperto è di <strong>{{ props.newName }}</strong
                >.
            </p>
        </div>
        <div class="grid w-full max-w-sm gap-2">
            <Button size="lg" as-child>
                <Link :href="route('volunteer.home')">Continua come {{ props.currentName }}</Link>
            </Button>
            <Button size="lg" variant="outline" @click="switchIdentity">Entra come {{ props.newName }}</Button>
        </div>
        <p class="max-w-sm text-xs text-muted-foreground">Cambiando, questo telefono vedrà i turni di {{ props.newName }}.</p>
    </div>
</template>
