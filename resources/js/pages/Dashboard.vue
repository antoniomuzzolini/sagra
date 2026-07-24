<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTime } from '@/lib/event-helpers';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarPlus, Hand, KeyRound, Repeat2, Users } from 'lucide-vue-next';

interface UncoveredShift {
    id: number;
    eventId: number;
    area: string;
    starts_at: string;
    ends_at: string;
    missing: number;
}

defineProps<{
    nextStep: 'event' | 'areas' | 'shifts' | null;
    event: { id: number; name: string } | null;
    uncoveredCount: number;
    uncoveredShifts: UncoveredShift[];
    pendingCount: number;
    substitutionCount: number;
    linkRequestsCount: number;
    volunteersCount: number;
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

function dayLabel(datetime: string): string {
    return new Date(datetime).toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric', month: 'numeric' });
}
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

            <!-- Work queues -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link :href="route('people.index')" class="rounded-xl border p-4 hover:bg-muted/50">
                    <p class="flex items-center gap-2 text-sm text-muted-foreground"><Users class="h-4 w-4" /> Volontari</p>
                    <p class="mt-1 text-2xl font-semibold">{{ volunteersCount }}</p>
                </Link>
                <Link :href="route('manage.shifts')" class="rounded-xl border p-4 hover:bg-muted/50">
                    <p class="flex items-center gap-2 text-sm text-muted-foreground"><Hand class="h-4 w-4" /> Da confermare</p>
                    <p class="mt-1 text-2xl font-semibold" :class="pendingCount > 0 ? 'text-amber-600 dark:text-amber-400' : ''">
                        {{ pendingCount }}
                    </p>
                </Link>
                <Link :href="route('manage.shifts')" class="rounded-xl border p-4 hover:bg-muted/50">
                    <p class="flex items-center gap-2 text-sm text-muted-foreground"><Repeat2 class="h-4 w-4" /> Cercano un sostituto</p>
                    <p class="mt-1 text-2xl font-semibold" :class="substitutionCount > 0 ? 'text-amber-600 dark:text-amber-400' : ''">
                        {{ substitutionCount }}
                    </p>
                </Link>
                <Link :href="route('people.index')" class="rounded-xl border p-4 hover:bg-muted/50">
                    <p class="flex items-center gap-2 text-sm text-muted-foreground"><KeyRound class="h-4 w-4" /> Nuovo link richiesto</p>
                    <p class="mt-1 text-2xl font-semibold" :class="linkRequestsCount > 0 ? 'text-amber-600 dark:text-amber-400' : ''">
                        {{ linkRequestsCount }}
                    </p>
                </Link>
            </div>

            <!-- Uncovered shifts -->
            <section v-if="nextStep === null" class="grid gap-2">
                <h2 class="font-medium">
                    Turni scoperti
                    <span v-if="uncoveredCount > 0" class="text-muted-foreground">({{ uncoveredCount }})</span>
                </h2>
                <p v-if="uncoveredCount === 0" class="rounded-xl border p-4 text-sm text-muted-foreground">
                    Tutti i turni in programma sono coperti. Ottimo lavoro!
                </p>
                <ul v-else class="divide-y rounded-xl border">
                    <li v-for="shift in uncoveredShifts" :key="shift.id">
                        <Link :href="route('manage.shifts')" class="flex items-center gap-3 p-3 hover:bg-muted/50">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ shift.area }}</p>
                                <p class="text-sm text-muted-foreground first-letter:uppercase">
                                    {{ dayLabel(shift.starts_at) }} · {{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-100"
                            >
                                {{ shift.missing === 1 ? 'manca 1 persona' : `mancano ${shift.missing} persone` }}
                            </span>
                            <ArrowRight class="h-4 w-4 text-muted-foreground" />
                        </Link>
                    </li>
                </ul>
                <p v-if="uncoveredCount > uncoveredShifts.length" class="text-sm text-muted-foreground">
                    …e altri {{ uncoveredCount - uncoveredShifts.length }} turni scoperti più avanti.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
