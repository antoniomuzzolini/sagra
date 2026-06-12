<script setup lang="ts">
import ModeratedSignupList, { type ModeratedSignup } from '@/components/ModeratedSignupList.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatTime } from '@/lib/event-helpers';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarCheck, Check, Copy, Hand, Pencil, Trash2, Undo2, Wrench } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface VolunteerShift {
    id: number;
    event: string;
    area: string;
    starts_at: string;
    ends_at: string;
    needed_people: number;
    assigned_count: number;
    notes: string | null;
    myStatus: 'available' | 'assigned' | 'declined' | null;
    mySubstitutionRequested: boolean;
    isMyArea: boolean;
    myOverlap: string | null;
    canModerate: boolean;
    signups: ModeratedSignup[];
}

const props = defineProps<{
    person: { name: string; needsContact: boolean };
    tenant: { name: string };
    manager: {
        areas: { id: number; name: string }[];
        people: { id: number; name: string }[];
        inviteUrl: string;
    } | null;
    shifts: VolunteerShift[];
}>();

// "Complete the registration": one contact unlocks reminders and recovery.
const contactFormOpen = ref(false);
const contactForm = useForm({ phone: '', email: '' });

function saveContact() {
    contactForm.put(route('volunteer.contact'), { preserveScroll: true });
}

// Manager toolkit (D18): new shifts, invite sharing, shift removal.
const shiftFormArea = ref<number | null>(null);
const shiftForm = useForm({ date: '', start_time: '', end_time: '', needed_people: 2, notes: '' });

function toggleShiftForm(areaId: number) {
    shiftFormArea.value = shiftFormArea.value === areaId ? null : areaId;
    shiftForm.reset();
    shiftForm.clearErrors();
}

function submitShift(areaId: number) {
    shiftForm.post(route('volunteer.shifts.store', areaId), {
        preserveScroll: true,
        onSuccess: () => (shiftFormArea.value = null),
    });
}

function destroyShift(shift: VolunteerShift) {
    if (confirm(`Eliminare il turno in ${shift.area} (${formatTime(shift.starts_at)}–${formatTime(shift.ends_at)})?`)) {
        router.delete(route('volunteer.shifts.destroy', shift.id), { preserveScroll: true });
    }
}

// Editing works on any shift of a managed area, organizer-created too.
const editingShift = ref<VolunteerShift | null>(null);
const editForm = useForm({ date: '', start_time: '', end_time: '', needed_people: 2, notes: '' });

function openEditShift(shift: VolunteerShift) {
    editingShift.value = shift;
    editForm.date = shift.starts_at.slice(0, 10);
    editForm.start_time = shift.starts_at.slice(11, 16);
    editForm.end_time = shift.ends_at.slice(11, 16);
    editForm.needed_people = shift.needed_people;
    editForm.notes = shift.notes ?? '';
    editForm.clearErrors();
}

function submitEditShift() {
    if (editingShift.value) {
        editForm.put(route('volunteer.shifts.update', editingShift.value.id), {
            preserveScroll: true,
            onSuccess: () => (editingShift.value = null),
        });
    }
}

const inviteCopied = ref(false);

async function copyInvite() {
    if (props.manager) {
        await navigator.clipboard.writeText(props.manager.inviteUrl);
        inviteCopied.value = true;
        setTimeout(() => (inviteCopied.value = false), 2000);
    }
}

const inviteWhatsappUrl = computed(() => {
    if (!props.manager) return '#';
    return 'https://wa.me/?text=' + encodeURIComponent(`Dai una mano anche tu a ${props.tenant.name}! Registrati qui: ${props.manager.inviteUrl}`);
});

const mine = computed(() => props.shifts.filter((s) => s.myStatus === 'assigned'));
const pending = computed(() => props.shifts.filter((s) => s.myStatus === 'available'));
const open = computed(() => props.shifts.filter((s) => s.myStatus !== 'assigned' && s.myStatus !== 'available'));

// Soft area membership (D18): your areas first, the rest stays visible
// below — uncovered shifts elsewhere are everyone's problem.
const openPreferred = computed(() => open.value.filter((s) => s.isMyArea));
const openOthers = computed(() => open.value.filter((s) => !s.isMyArea));
const splitByArea = computed(() => openPreferred.value.length > 0 && openOthers.value.length > 0);
const mainOpen = computed(() => (splitByArea.value ? openPreferred.value : open.value));
const showOthers = ref(false);

function dayLabel(datetime: string): string {
    return new Date(datetime).toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long' });
}

function groupByDay(shifts: VolunteerShift[]): [string, VolunteerShift[]][] {
    const groups = new Map<string, VolunteerShift[]>();
    for (const shift of shifts) {
        const day = dayLabel(shift.starts_at);
        groups.set(day, [...(groups.get(day) ?? []), shift]);
    }
    return [...groups.entries()];
}

function signUp(shift: VolunteerShift) {
    router.post(route('volunteer.signup', shift.id), {}, { preserveScroll: true });
}

function withdraw(shift: VolunteerShift) {
    router.delete(route('volunteer.signup.withdraw', shift.id), { preserveScroll: true });
}

function requestSubstitution(shift: VolunteerShift) {
    router.post(route('volunteer.substitution', shift.id), {}, { preserveScroll: true });
}

function cancelSubstitution(shift: VolunteerShift) {
    router.delete(route('volunteer.substitution.cancel', shift.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="I miei turni" />

    <div class="mx-auto flex min-h-screen max-w-lg flex-col gap-6 bg-background p-4 pb-16">
        <header class="pt-2">
            <h1 class="text-2xl font-semibold text-foreground">Ciao, {{ person.name }}!</h1>
            <p class="text-muted-foreground">{{ tenant.name }}</p>
        </header>

        <section v-if="person.needsContact" class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950">
            <p class="text-sm text-amber-900 dark:text-amber-100">
                Lascia un recapito: ti ricordiamo i turni e, se perdi l'accesso, rientri da solo.
            </p>
            <Button v-if="!contactFormOpen" variant="outline" size="sm" class="mt-2" @click="contactFormOpen = true">Aggiungi un recapito</Button>
            <form v-else class="mt-2 grid gap-2" @submit.prevent="saveContact">
                <Input v-model="contactForm.phone" type="tel" placeholder="Telefono: +39 333 1234567" aria-label="Telefono" />
                <p v-if="contactForm.errors.phone" class="text-sm text-red-600">{{ contactForm.errors.phone }}</p>
                <Input v-model="contactForm.email" type="email" placeholder="Email: nome@esempio.it" aria-label="Email" />
                <p v-if="contactForm.errors.email" class="text-sm text-red-600">{{ contactForm.errors.email }}</p>
                <Button type="submit" size="sm" :disabled="contactForm.processing">Salva</Button>
            </form>
        </section>

        <!-- Manager toolkit -->
        <section v-if="manager" class="grid gap-2">
            <h2 class="flex items-center gap-2 font-medium text-foreground">
                <Wrench class="h-4 w-4 text-muted-foreground" /> {{ manager.areas.length === 1 ? 'Il tuo reparto' : 'I tuoi reparti' }}
            </h2>
            <div v-for="area in manager.areas" :key="area.id" class="grid gap-2 rounded-xl border p-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-medium">{{ area.name }}</p>
                    <Button variant="outline" size="sm" @click="toggleShiftForm(area.id)">Nuovo turno</Button>
                </div>
                <form v-if="shiftFormArea === area.id" class="grid gap-2" @submit.prevent="submitShift(area.id)">
                    <Input v-model="shiftForm.date" type="date" required aria-label="Data" />
                    <p v-if="shiftForm.errors.date" class="text-sm text-red-600">{{ shiftForm.errors.date }}</p>
                    <div class="flex items-center gap-2">
                        <Input v-model="shiftForm.start_time" type="time" required aria-label="Inizio" />
                        <span class="text-muted-foreground">→</span>
                        <Input v-model="shiftForm.end_time" type="time" required aria-label="Fine" />
                    </div>
                    <p v-if="shiftForm.errors.start_time || shiftForm.errors.end_time" class="text-sm text-red-600">
                        {{ shiftForm.errors.start_time || shiftForm.errors.end_time }}
                    </p>
                    <div class="flex items-center gap-2">
                        <Input v-model.number="shiftForm.needed_people" type="number" min="1" required class="w-20" aria-label="Persone" />
                        <span class="text-sm text-muted-foreground">persone</span>
                    </div>
                    <p v-if="shiftForm.errors.needed_people" class="text-sm text-red-600">{{ shiftForm.errors.needed_people }}</p>
                    <Input v-model="shiftForm.notes" placeholder="Note (facoltative)" aria-label="Note" />
                    <Button type="submit" size="sm" :disabled="shiftForm.processing">Crea turno</Button>
                </form>
            </div>
            <div class="flex flex-wrap items-center gap-2 rounded-xl border p-3">
                <p class="min-w-0 flex-1 text-sm text-muted-foreground">Serve gente? Condividi il link d'invito:</p>
                <Button variant="outline" size="sm" @click="copyInvite">
                    <Check v-if="inviteCopied" class="h-4 w-4" />
                    <Copy v-else class="h-4 w-4" />
                    {{ inviteCopied ? 'Copiato!' : 'Copia' }}
                </Button>
                <Button size="sm" as-child>
                    <a :href="inviteWhatsappUrl" target="_blank" rel="noopener">WhatsApp</a>
                </Button>
            </div>
        </section>

        <!-- Confirmed shifts -->
        <section v-if="mine.length > 0" class="grid gap-2">
            <h2 class="flex items-center gap-2 font-medium text-foreground"><CalendarCheck class="h-4 w-4 text-green-600" /> I tuoi turni</h2>
            <div
                v-for="shift in mine"
                :key="shift.id"
                class="rounded-xl border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950"
            >
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-foreground">{{ shift.area }} · {{ dayLabel(shift.starts_at) }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }} · {{ shift.event }}
                        </p>
                    </div>
                    <template v-if="shift.canModerate">
                        <Button variant="ghost" size="icon" aria-label="Modifica turno" @click="openEditShift(shift)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" aria-label="Elimina turno" @click="destroyShift(shift)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </template>
                </div>
                <p v-if="shift.notes" class="text-sm text-muted-foreground">{{ shift.notes }}</p>
                <p v-if="shift.myOverlap" class="text-sm text-amber-700 dark:text-amber-400">⚠ Si sovrappone con {{ shift.myOverlap }}</p>
                <div class="mt-2">
                    <Button v-if="!shift.mySubstitutionRequested" variant="outline" size="sm" @click="requestSubstitution(shift)">
                        Non posso più: chiedi un sostituto
                    </Button>
                    <p v-else class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                        Stiamo cercando un sostituto.
                        <Button variant="ghost" size="sm" @click="cancelSubstitution(shift)">Posso di nuovo</Button>
                    </p>
                </div>
                <ModeratedSignupList v-if="shift.canModerate" :signups="shift.signups" :shift-id="shift.id" :people="manager?.people" />
            </div>
        </section>

        <!-- Waiting for confirmation -->
        <section v-if="pending.length > 0" class="grid gap-2">
            <h2 class="flex items-center gap-2 font-medium text-foreground"><Hand class="h-4 w-4 text-amber-600" /> In attesa di conferma</h2>
            <div v-for="shift in pending" :key="shift.id" class="rounded-xl border p-3">
                <div class="flex items-center gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-foreground">{{ shift.area }} · {{ dayLabel(shift.starts_at) }}</p>
                        <p class="text-sm text-muted-foreground">{{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }}</p>
                        <p v-if="shift.myOverlap" class="text-sm text-amber-700 dark:text-amber-400">⚠ Si sovrappone con {{ shift.myOverlap }}</p>
                    </div>
                    <template v-if="shift.canModerate">
                        <Button variant="ghost" size="icon" aria-label="Modifica turno" @click="openEditShift(shift)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" aria-label="Elimina turno" @click="destroyShift(shift)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </template>
                    <Button variant="ghost" size="sm" @click="withdraw(shift)"><Undo2 class="h-4 w-4" /> Ritira</Button>
                </div>
                <ModeratedSignupList v-if="shift.canModerate" :signups="shift.signups" :shift-id="shift.id" :people="manager?.people" />
            </div>
        </section>

        <!-- Open shifts -->
        <section class="grid gap-3">
            <h2 class="font-medium text-foreground">Turni aperti</h2>
            <p v-if="open.length === 0" class="text-sm text-muted-foreground">Niente turni aperti al momento. Torna a trovarci!</p>
            <div v-for="[day, dayShifts] in groupByDay(mainOpen)" :key="day" class="grid gap-2">
                <h3 class="text-sm font-medium text-muted-foreground first-letter:uppercase">{{ day }}</h3>
                <div v-for="shift in dayShifts" :key="shift.id" class="rounded-xl border p-3">
                    <div class="flex items-center gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-foreground">{{ shift.area }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }} · {{ shift.assigned_count }}/{{
                                    shift.needed_people
                                }}
                                coperti
                            </p>
                            <p v-if="shift.notes" class="text-sm text-muted-foreground">{{ shift.notes }}</p>
                            <p v-if="shift.myOverlap" class="text-sm text-amber-700 dark:text-amber-400">
                                ⚠ Si sovrappone con {{ shift.myOverlap }}
                            </p>
                        </div>
                        <Button v-if="shift.canModerate" variant="ghost" size="icon" aria-label="Modifica turno" @click="openEditShift(shift)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button v-if="shift.canModerate" variant="ghost" size="icon" aria-label="Elimina turno" @click="destroyShift(shift)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <Button size="sm" @click="signUp(shift)">Ci sono!</Button>
                    </div>
                    <ModeratedSignupList v-if="shift.canModerate" :signups="shift.signups" :shift-id="shift.id" :people="manager?.people" />
                </div>
            </div>

            <!-- Other areas stay reachable: uncovered shifts are everyone's problem -->
            <template v-if="splitByArea">
                <Button variant="outline" size="sm" class="justify-self-start" @click="showOthers = !showOthers">
                    {{ showOthers ? 'Nascondi gli altri reparti' : `Altri reparti (${openOthers.length} turni)` }}
                </Button>
                <div v-for="[day, dayShifts] in showOthers ? groupByDay(openOthers) : []" :key="`other-${day}`" class="grid gap-2">
                    <h3 class="text-sm font-medium text-muted-foreground first-letter:uppercase">{{ day }}</h3>
                    <div v-for="shift in dayShifts" :key="shift.id" class="rounded-xl border p-3">
                        <div class="flex items-center gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-foreground">{{ shift.area }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }} · {{ shift.assigned_count }}/{{
                                        shift.needed_people
                                    }}
                                    coperti
                                </p>
                                <p v-if="shift.notes" class="text-sm text-muted-foreground">{{ shift.notes }}</p>
                                <p v-if="shift.myOverlap" class="text-sm text-amber-700 dark:text-amber-400">
                                    ⚠ Si sovrappone con {{ shift.myOverlap }}
                                </p>
                            </div>
                            <Button size="sm" @click="signUp(shift)">Ci sono!</Button>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <!-- Shift edit dialog (managers) -->
        <Dialog :open="editingShift !== null" @update:open="(open: boolean) => !open && (editingShift = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifica turno · {{ editingShift?.area }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-2" @submit.prevent="submitEditShift">
                    <Input v-model="editForm.date" type="date" required aria-label="Data" />
                    <p v-if="editForm.errors.date" class="text-sm text-red-600">{{ editForm.errors.date }}</p>
                    <div class="flex items-center gap-2">
                        <Input v-model="editForm.start_time" type="time" required aria-label="Inizio" />
                        <span class="text-muted-foreground">→</span>
                        <Input v-model="editForm.end_time" type="time" required aria-label="Fine" />
                    </div>
                    <p v-if="editForm.errors.start_time || editForm.errors.end_time" class="text-sm text-red-600">
                        {{ editForm.errors.start_time || editForm.errors.end_time }}
                    </p>
                    <div class="flex items-center gap-2">
                        <Input v-model.number="editForm.needed_people" type="number" min="1" required class="w-20" aria-label="Persone" />
                        <span class="text-sm text-muted-foreground">persone</span>
                    </div>
                    <p v-if="editForm.errors.needed_people" class="text-sm text-red-600">{{ editForm.errors.needed_people }}</p>
                    <Input v-model="editForm.notes" placeholder="Note (facoltative)" aria-label="Note" />
                    <Button type="submit" :disabled="editForm.processing">Salva modifiche</Button>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
