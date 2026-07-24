<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { enablePush, pushSupported } from '@/lib/push';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bell, Check, Send } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    organization: { name: string; notifySeatFreed: boolean };
    channels: { push: boolean; email: boolean };
    vapidConfigured: boolean;
    vapidPublicKey: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Impostazioni', href: '/organization' }];

const form = useForm({
    name: props.organization.name,
    notify_seat_freed: props.organization.notifySeatFreed,
});

const saved = ref(false);

function submit() {
    form.put(route('organization.update'), {
        preserveScroll: true,
        onSuccess: () => {
            saved.value = true;
            setTimeout(() => (saved.value = false), 2000);
        },
    });
}

// Notification self-test: subscribe this device, then send yourself one.
const pushBusy = ref(false);
const pushOn = ref(props.channels.push);
const testSent = ref(false);

async function activatePush() {
    pushBusy.value = true;
    pushOn.value = await enablePush(props.vapidPublicKey ?? '');
    pushBusy.value = false;
}

function sendTest() {
    router.post(
        route('organization.test-notification'),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                testSent.value = true;
                setTimeout(() => (testSent.value = false), 4000);
            },
        },
    );
}
</script>

<template>
    <Head title="Impostazioni" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Impostazioni</h1>
                <p class="text-sm text-muted-foreground">Valgono per tutta l'organizzazione, su ogni evento.</p>
            </div>

            <form class="grid max-w-lg gap-6" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="org-name">Nome dell'organizzazione</Label>
                    <Input id="org-name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-3 rounded-xl border p-4">
                    <p class="text-sm font-medium">Notifiche</p>
                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox v-model:checked="form.notify_seat_freed" class="mt-0.5" />
                        <span>
                            Avvisa i volontari quando si libera un posto
                            <span class="block text-sm text-muted-foreground">
                                Se qualcuno rinuncia a un turno futuro, chi ha già lavorato o si è offerto in quell'area riceve una notifica
                                (push o email) per potersi prenotare.
                            </span>
                        </span>
                    </Label>
                    <InputError :message="form.errors.notify_seat_freed" />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">Salva</Button>
                    <span v-if="saved" class="flex items-center gap-1 text-sm text-green-600"><Check class="h-4 w-4" /> Salvato</span>
                </div>
            </form>

            <!-- Notification self-test -->
            <div class="grid max-w-lg gap-3 rounded-xl border p-4">
                <p class="text-sm font-medium">Prova le notifiche</p>

                <p v-if="!vapidConfigured" class="rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    Le notifiche push non sono configurate sul server (mancano le chiavi VAPID nel file <code>.env</code>).
                </p>

                <template v-else>
                    <ul class="grid gap-1 text-sm text-muted-foreground">
                        <li>Push su questo dispositivo: {{ pushOn ? 'attive ✅' : 'non attive' }}</li>
                        <li>Email: {{ channels.email ? 'presente ✅' : 'nessuna email sul tuo profilo' }}</li>
                    </ul>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="!pushOn && pushSupported()" variant="outline" :disabled="pushBusy" @click="activatePush">
                            <Bell class="h-4 w-4" /> Attiva le push qui
                        </Button>
                        <Button variant="outline" :disabled="!pushOn && !channels.email" @click="sendTest">
                            <Send class="h-4 w-4" /> Invia notifica di prova
                        </Button>
                        <span v-if="testSent" class="flex items-center gap-1 self-center text-sm text-green-600">
                            <Check class="h-4 w-4" /> Inviata: arriva a momenti
                        </span>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
