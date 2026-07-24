<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{ notifications: { seat_freed: boolean; new_shifts: boolean } }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifiche', href: '/settings/notifications' }];

// Same toggles as the volunteer's "I tuoi dati" dialog, saved on the spot.
const form = useForm({
    seat_freed: props.notifications.seat_freed,
    new_shifts: props.notifications.new_shifts,
});

function save() {
    form.put(route('volunteer.notifications'), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notifiche" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Notifiche" description="Scegli quali avvisi vuoi ricevere quando lavori ai turni" />

                <div class="grid gap-3">
                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox
                            :checked="form.new_shifts"
                            class="mt-0.5"
                            @update:checked="(v: boolean) => { form.new_shifts = v; save(); }"
                        />
                        <span>
                            Quando escono nuovi turni
                            <span class="block text-sm text-muted-foreground">Ti avvisiamo quando si apre il tabellone in un'area.</span>
                        </span>
                    </Label>
                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox
                            :checked="form.seat_freed"
                            class="mt-0.5"
                            @update:checked="(v: boolean) => { form.seat_freed = v; save(); }"
                        />
                        <span>
                            Quando si libera un posto
                            <span class="block text-sm text-muted-foreground">Ti avvisiamo se un turno pieno torna scoperto.</span>
                        </span>
                    </Label>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
