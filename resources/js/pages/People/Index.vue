<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PeopleRoster, { type PersonRosterRow } from '@/components/PeopleRoster.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type MagicLinkFlash, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, Link2, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ people: PersonRosterRow[]; inviteUrl: string }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Volontari', href: '/people' }];

const page = usePage<SharedData>();

const noShiftsCount = computed(() => props.people.filter((p) => p.shiftsCount === 0).length);

// Create / edit dialog
const editing = ref<PersonRosterRow | null>(null);
const formOpen = ref(false);
const form = useForm({ name: '', phone: '', email: '' });

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function openEdit(person: PersonRosterRow) {
    editing.value = person;
    form.name = person.name;
    form.phone = person.phone ?? '';
    form.email = person.email ?? '';
    form.clearErrors();
    formOpen.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (formOpen.value = false) };
    if (editing.value) {
        form.put(route('people.update', editing.value.id), options);
    } else {
        form.post(route('people.store'), options);
    }
}

function destroy(person: PersonRosterRow) {
    if (confirm(`Eliminare ${person.name}? Il suo link d'accesso smetterà di funzionare.`)) {
        useForm({}).delete(route('people.destroy', person.id), { preserveScroll: true });
    }
}

// Magic link dialog
const magicLink = ref<MagicLinkFlash | null>(null);
const copied = ref(false);

watch(
    () => page.props.flash.magicLink,
    (value) => {
        if (value) {
            magicLink.value = value;
            copied.value = false;
        }
    },
    { immediate: true },
);

function requestLink(person: PersonRosterRow) {
    useForm({}).post(route('people.magic-link', person.id), { preserveScroll: true });
}

async function copyLink() {
    if (magicLink.value) {
        await navigator.clipboard.writeText(magicLink.value.url);
        copied.value = true;
    }
}

// Tenant invite link (self-registration)
const inviteCopied = ref(false);

async function copyInvite() {
    await navigator.clipboard.writeText(props.inviteUrl);
    inviteCopied.value = true;
    setTimeout(() => (inviteCopied.value = false), 2000);
}

function regenerateInvite() {
    if (confirm("Rigenerare il link d'invito? Quello attuale smetterà di funzionare. Chi è già registrato non perde nulla.")) {
        useForm({}).post(route('people.invite.regenerate'), { preserveScroll: true });
    }
}

const whatsappUrl = computed(() => {
    if (!magicLink.value) return '#';
    const text = encodeURIComponent(`Ciao ${magicLink.value.personName}! Ecco il tuo link personale per i turni: ${magicLink.value.url}`);
    const phone = magicLink.value.personPhone?.replace(/[^0-9]/g, '');
    return phone ? `https://wa.me/${phone}?text=${text}` : `https://wa.me/?text=${text}`;
});
</script>

<template>
    <Head title="Volontari" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl font-semibold">Volontari</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ props.people.length }} {{ props.people.length === 1 ? 'persona' : 'persone' }}
                        <template v-if="noShiftsCount > 0"> · {{ noShiftsCount }} senza turni</template>
                    </p>
                </div>
                <Button @click="openCreate">Nuovo volontario</Button>
            </div>

            <div class="grid gap-2 rounded-xl border bg-muted/30 p-3">
                <p class="text-sm font-medium">Link d'invito</p>
                <p class="text-sm text-muted-foreground">
                    Condividilo una volta (es. nel gruppo WhatsApp): chi lo apre si registra da solo e arriva subito ai turni.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="min-w-0 flex-1 truncate rounded-md bg-muted p-2 font-mono text-xs">{{ props.inviteUrl }}</p>
                    <Button variant="outline" size="sm" @click="copyInvite">
                        <Check v-if="inviteCopied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        {{ inviteCopied ? 'Copiato!' : 'Copia' }}
                    </Button>
                    <Button variant="ghost" size="sm" @click="regenerateInvite">Rigenera</Button>
                </div>
            </div>

            <p v-if="props.people.length === 0" class="text-muted-foreground">
                Nessun volontario ancora. Aggiungi il primo a mano o condividi il link d'invito.
            </p>

            <PeopleRoster v-else :people="props.people">
                <template #actions="{ person }">
                    <Button variant="outline" size="sm" @click="requestLink(person)">
                        <Link2 class="h-4 w-4" />
                        <span class="sm:sr-only">{{ person.hasLink ? 'Nuovo link' : 'Crea link' }}</span>
                    </Button>
                    <Button variant="ghost" size="icon" @click="openEdit(person)" aria-label="Modifica">
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" @click="destroy(person)" aria-label="Elimina">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </template>
            </PeopleRoster>
        </div>

        <Dialog v-model:open="formOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Modifica volontario' : 'Nuovo volontario' }}</DialogTitle>
                    <DialogDescription>Serve almeno un contatto: telefono o email.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="person-name">Nome</Label>
                        <Input id="person-name" v-model="form.name" required placeholder="Nome e cognome" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="person-phone">Telefono</Label>
                        <Input id="person-phone" v-model="form.phone" type="tel" placeholder="+39 333 1234567" />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="person-email">Email</Label>
                        <Input id="person-email" v-model="form.email" type="email" placeholder="nome@esempio.it" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">Salva</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="magicLink !== null" @update:open="magicLink = null">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link d'accesso per {{ magicLink?.personName }}</DialogTitle>
                    <DialogDescription>
                        Invia questo link al volontario: toccandolo entra nei suoi turni, senza password. Generarne uno nuovo disattiva il precedente.
                    </DialogDescription>
                </DialogHeader>
                <p class="break-all rounded-md bg-muted p-3 font-mono text-sm">{{ magicLink?.url }}</p>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="copyLink">
                        <Check v-if="copied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        {{ copied ? 'Copiato!' : 'Copia' }}
                    </Button>
                    <Button as-child>
                        <a :href="whatsappUrl" target="_blank" rel="noopener">Invia su WhatsApp</a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
