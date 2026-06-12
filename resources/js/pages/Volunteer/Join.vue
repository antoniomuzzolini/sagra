<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    tenantName: string;
    token: string;
    currentName: string | null;
}>();

const form = useForm({ name: '', phone: '' });

function submit() {
    form.post(route('join.store', props.token));
}
</script>

<template>
    <Head title="Unisciti" />
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-foreground">
        <div class="w-full max-w-sm text-center">
            <h1 class="text-2xl font-semibold">{{ props.tenantName }}</h1>
            <p class="mt-1 text-muted-foreground">Dai una mano anche tu: scrivi il tuo nome e sei dentro.</p>
        </div>

        <div
            v-if="props.currentName"
            class="w-full max-w-sm rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100"
        >
            Su questo telefono sei già <strong>{{ props.currentName }}</strong> —
            <Link :href="route('volunteer.home')" class="underline">vai ai tuoi turni</Link>. Compila qui sotto solo per registrare un'altra persona:
            il telefono passerà al nuovo profilo.
        </div>

        <form class="grid w-full max-w-sm gap-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="join-name">Il tuo nome</Label>
                <Input id="join-name" v-model="form.name" required placeholder="Nome e cognome" autocomplete="name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="join-phone">Telefono <span class="font-normal text-muted-foreground">(facoltativo)</span></Label>
                <Input id="join-phone" v-model="form.phone" type="tel" placeholder="+39 333 1234567" autocomplete="tel" />
                <p class="text-xs text-muted-foreground">Serve solo per i promemoria dei turni e per recuperare l'accesso.</p>
                <InputError :message="form.errors.phone" />
            </div>
            <Button type="submit" size="lg" :disabled="form.processing">Entra</Button>
        </form>
    </div>
</template>
