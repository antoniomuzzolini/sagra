<script setup lang="ts">
import OverviewDashboard, { type OverviewArea } from '@/components/OverviewDashboard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarPlus } from 'lucide-vue-next';

defineProps<{
    nextStep: 'event' | 'areas' | 'shifts' | null;
    event: { id: number; name: string } | null;
    areas: OverviewArea[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Panoramica', href: '/dashboard' }];

const nextStepCopy = {
    event: {
        title: 'Nessun evento in programma',
        text: "Parti da qui: crea l'evento con le sue date, poi le aree e i turni.",
        cta: 'Crea un evento',
    },
    areas: {
        title: 'Definisci le aree',
        text: 'Le aree sono i posti dove si lavora: cucina, bar, cassa, pulizie…',
        cta: 'Aggiungi le aree',
    },
    shifts: {
        title: 'Crea i turni',
        text: 'Le aree ci sono: ora servono i turni con il fabbisogno di persone.',
        cta: 'Crea i turni',
    },
} as const;
</script>

<template>
    <Head title="Panoramica" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Next setup step -->
            <section v-if="nextStep" class="grid gap-2 rounded-xl border border-dashed p-6 text-center">
                <h2 class="text-lg font-semibold">{{ nextStepCopy[nextStep].title }}</h2>
                <p class="text-sm text-muted-foreground">{{ nextStepCopy[nextStep].text }}</p>
                <div>
                    <Button as-child>
                        <Link :href="nextStep === 'event' ? route('events.index') : route('events.show', event!.id)">
                            <CalendarPlus class="h-4 w-4" /> {{ nextStepCopy[nextStep].cta }}
                        </Link>
                    </Button>
                </div>
            </section>

            <!-- Coverage overview (same as the area manager's) -->
            <template v-else>
                <div>
                    <h1 class="text-xl font-semibold">Panoramica</h1>
                    <p class="text-sm text-muted-foreground">La copertura delle aree dell'evento.</p>
                </div>
                <OverviewDashboard :areas="areas" @select="router.visit('/manage/shifts')" @uncovered="router.visit('/manage/shifts')" />
            </template>
        </div>
    </AppLayout>
</template>
