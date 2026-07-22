<script setup lang="ts">
import PeopleRoster, { type PersonRosterRow } from '@/components/PeopleRoster.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{ people: PersonRosterRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Persone', href: '/manage/people' }];
</script>

<template>
    <Head title="Persone" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Volontari</h1>
                <p class="text-sm text-muted-foreground">
                    Chi lavora nelle aree che gestisci. Per creare turni e assegnare, usa "I miei turni".
                </p>
            </div>
            <PeopleRoster v-if="people.length" :people="people" />
            <p v-else class="text-sm text-muted-foreground">Ancora nessuno nelle tue aree.</p>
        </div>
    </AppLayout>
</template>
