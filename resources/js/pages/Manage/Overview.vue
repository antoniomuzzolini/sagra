<script setup lang="ts">
import OverviewDashboard, { type OverviewArea } from '@/components/OverviewDashboard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

defineProps<{ areas: OverviewArea[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Panoramica', href: '/manage/overview' }];
</script>

<template>
    <Head title="Panoramica" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Panoramica</h1>
                <p class="text-sm text-muted-foreground">La copertura delle aree che gestisci.</p>
            </div>
            <OverviewDashboard :areas="areas" @select="router.visit('/manage/calendar')" />
        </div>
    </AppLayout>
</template>
