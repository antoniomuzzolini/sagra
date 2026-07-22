<script setup lang="ts">
import ScheduleTimeline from '@/components/ScheduleTimeline.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendario', href: '/manage/calendar' }];
</script>

<template>
    <Head title="Calendario" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Calendario</h1>
                <p class="text-sm text-muted-foreground">I turni delle aree che gestisci, giorno per giorno.</p>
            </div>
            <ScheduleTimeline :areas="schedule.areas" :phases="schedule.phases" />
        </div>
    </AppLayout>
</template>
