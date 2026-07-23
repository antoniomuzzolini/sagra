<script setup lang="ts">
import AreaShiftBoard, { type ManagerShift } from '@/components/AreaShiftBoard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    areas: { id: number; name: string }[];
    people: { id: number; name: string }[];
    inviteUrl: string;
    shifts: ManagerShift[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Gestione turni', href: '/manage/shifts' }];
</script>

<template>
    <Head title="Gestione turni" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Gestione turni</h1>
                <p class="text-sm text-muted-foreground">Crea i turni, vedi chi ha dato disponibilità e assegna.</p>
            </div>
            <AreaShiftBoard :areas="areas" :people="people" :invite-url="inviteUrl" :shifts="shifts" />
        </div>
    </AppLayout>
</template>
