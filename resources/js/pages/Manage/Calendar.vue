<script setup lang="ts">
import ScheduleTimeline from '@/components/ScheduleTimeline.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

interface Shift {
    id: number;
    starts_at: string;
    ends_at: string;
    needed_people: number;
    assigned_count: number;
    notes: string | null;
}
interface Area {
    id: number;
    name: string;
    family: string | null;
    shifts: Shift[];
}
interface Phase {
    type: string;
    starts_on: string;
    ends_on: string;
}

defineProps<{ schedule: { areas: Area[]; phases: Phase[] } }>();

// Shared by the manager (/manage/calendar) and the organizer (/calendar):
// anchor the breadcrumb to whichever page we're actually on, and word the
// subtitle for the scope.
const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendario', href: page.url }];
const subtitle = page.props.auth.role === 'manager' ? 'I turni delle aree che gestisci, giorno per giorno.' : 'I turni di tutte le aree, giorno per giorno.';
</script>

<template>
    <Head title="Calendario" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Calendario</h1>
                <p class="text-sm text-muted-foreground">{{ subtitle }}</p>
            </div>
            <ScheduleTimeline :areas="schedule.areas" :phases="schedule.phases" />
        </div>
    </AppLayout>
</template>
