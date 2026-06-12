<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();
const requested = computed(() => page.props.flash.recoveryRequested === true);

const form = useForm({ contact: '' });

function submit() {
    form.post(route('volunteer.recover.store'));
}
</script>

<template>
    <Head title="Recupera l'accesso" />
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-foreground">
        <div class="max-w-sm text-center">
            <h1 class="text-2xl font-semibold">Recupera l'accesso</h1>
            <p class="mt-2 text-muted-foreground">Scrivi il telefono o l'email con cui sei registrato e ti facciamo rientrare.</p>
        </div>

        <div v-if="requested" class="w-full max-w-sm rounded-xl border p-4 text-center">
            <p class="font-medium">Richiesta ricevuta!</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Se il recapito è registrato, riceverai a breve un nuovo link: via email subito, oppure dal tuo referente.
            </p>
        </div>

        <form v-else class="grid w-full max-w-sm gap-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="recover-contact">Telefono o email</Label>
                <Input id="recover-contact" v-model="form.contact" required placeholder="+39 333 1234567 o nome@esempio.it" />
                <InputError :message="form.errors.contact" />
            </div>
            <Button type="submit" size="lg" :disabled="form.processing">Mandami un nuovo link</Button>
        </form>
    </div>
</template>
