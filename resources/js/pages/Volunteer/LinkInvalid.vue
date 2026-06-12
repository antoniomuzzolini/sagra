<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{ reason?: 'unknown' | 'used' }>();
</script>

<template>
    <Head title="Link non valido" />
    <div class="flex min-h-screen flex-col items-center justify-center gap-2 bg-background p-6 text-center">
        <h1 class="text-2xl font-semibold text-foreground">
            {{ props.reason === 'used' ? 'Questo link è già stato usato' : 'Questo link non funziona più' }}
        </h1>
        <p class="mt-2 text-muted-foreground">
            {{
                props.reason === 'used'
                    ? 'Ogni link funziona una volta sola, per sicurezza. Chiedi al tuo referente di mandartene uno nuovo: ti basterà toccarlo per entrare.'
                    : 'Chiedi al tuo referente di mandartene uno nuovo: ti basterà toccarlo per entrare.'
            }}
        </p>
        <Button class="mt-4" variant="outline" as-child>
            <Link :href="route('volunteer.recover')">Recupera l'accesso da solo</Link>
        </Button>
    </div>
</template>
