<script setup lang="ts">
import ModeratedSignupList, { type ModeratedSignup } from '@/components/ModeratedSignupList.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { formatTime } from '@/lib/event-helpers';
import { enablePush, pushDenied, pushSupported } from '@/lib/push';
import { type MagicLinkFlash, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Bell, CalendarCheck, Check, Copy, Hand, Pencil, Plus, Trash2, Undo2, UserPlus, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface VolunteerShift {
    id: number;
    event: string;
    area: string;
    areaId: number;
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
    person: { name: string; phone: string | null; email: string | null; needsContact: boolean; hasPush: boolean };
    tenant: { name: string };
    manager: {
        areas: { id: number; name: string }[];
        people: { id: number; name: string }[];
        inviteUrl: string;
    } | null;
    vapidPublicKey: string | null;
    shifts: VolunteerShift[];
}>();

// Managers get one tab per area plus their personal one; everyone
// else just sees the personal view, no tab bar at all.
const activeTab = ref<number | 'me'>(props.manager ? props.manager.areas[0].id : 'me');

// Profile: contacts are editable any time — they unlock reminders
// and self-service recovery (D16).
const contactFormOpen = ref(false);
const contactForm = useForm({ phone: props.person.phone ?? '', email: props.person.email ?? '' });

function saveContact() {
    contactForm.put(route('volunteer.contact'), {
        preserveScroll: true,
        onSuccess: () => (contactFormOpen.value = false),
    });
}

// Manager toolkit (D18): every shift of the area, organizer-created
// ones included.
function areaShifts(areaId: number): VolunteerShift[] {
    return props.shifts.filter((s) => s.areaId === areaId);
}

const shiftFormOpen = ref(false);
const shiftForm = useForm({ date: '', start_time: '', end_time: '', needed_people: 2, notes: '' });

function toggleShiftForm() {
    shiftFormOpen.value = !shiftFormOpen.value;
    volunteerFormOpen.value = false;
    shiftForm.reset();
    shiftForm.clearErrors();
}

function submitShift(areaId: number) {
    shiftForm.post(route('volunteer.shifts.store', areaId), {
        preserveScroll: true,
        onSuccess: () => (shiftFormOpen.value = false),
    });
}

function destroyShift(shift: VolunteerShift) {
    if (confirm(`Eliminare il turno di ${shortDayLabel(shift.starts_at)} (${formatTime(shift.starts_at)}–${formatTime(shift.ends_at)})?`)) {
        router.delete(route('volunteer.shifts.destroy', shift.id), { preserveScroll: true });
    }
}

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

// New volunteer, recruited in person: created here, link in hand.
const volunteerFormOpen = ref(false);
const volunteerForm = useForm({ name: '', phone: '' });

function toggleVolunteerForm() {
    volunteerFormOpen.value = !volunteerFormOpen.value;
    shiftFormOpen.value = false;
    volunteerForm.reset();
    volunteerForm.clearErrors();
}

function submitVolunteer() {
    volunteerForm.post(route('volunteer.people.store'), {
        preserveScroll: true,
        onSuccess: () => (volunteerFormOpen.value = false),
    });
}

// The freshly created volunteer's magic link, flashed once.
const page = usePage<SharedData>();
const magicLink = ref<MagicLinkFlash | null>(null);
const linkCopied = ref(false);

watch(
    () => page.props.flash.magicLink,
    (value) => {
        if (value) {
            magicLink.value = value;
            linkCopied.value = false;
        }
    },
    { immediate: true },
);

async function copyMagicLink() {
    if (magicLink.value) {
        await navigator.clipboard.writeText(magicLink.value.url);
        linkCopied.value = true;
    }
}

const magicLinkWhatsappUrl = computed(() => {
    if (!magicLink.value) return '#';
    const text = encodeURIComponent(`Ciao ${magicLink.value.personName}! Ecco il tuo link personale per i turni: ${magicLink.value.url}`);
    const phone = magicLink.value.personPhone?.replace(/[^0-9]/g, '');
    return phone ? `https://wa.me/${phone}?text=${text}` : `https://wa.me/?text=${text}`;
});

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

// The personal view: my commitments anywhere, plus open shifts of the
// areas I do not manage (managed ones live in their own tab).
const mine = computed(() => props.shifts.filter((s) => s.myStatus === 'assigned'));
const pending = computed(() => props.shifts.filter((s) => s.myStatus === 'available'));
const open = computed(() => props.shifts.filter((s) => s.myStatus !== 'assigned' && s.myStatus !== 'available' && !s.canModerate));

// Push: one tap on the bell next to the name (D10). Once enabled —
// or denied — the bell disappears.
const pushBusy = ref(false);
const pushFailed = ref(false);
const showPushBell = computed(() => pushSupported() && !pushDenied() && !props.person.hasPush && !pushFailed.value);

async function activatePush() {
    pushBusy.value = true;
    const enabled = await enablePush(props.vapidPublicKey ?? '');
    pushBusy.value = false;
    pushFailed.value = !enabled;
}

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

function shortDayLabel(datetime: string): string {
    return new Date(datetime).toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric', month: 'numeric' });
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

    <div class="mx-auto flex min-h-screen max-w-lg flex-col gap-4 bg-background p-4 pb-16">
        <header class="flex items-start gap-2 pt-2">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-semibold text-foreground">Ciao, {{ person.name }}!</h1>
                <p class="text-muted-foreground">{{ tenant.name }}</p>
            </div>
            <Button v-if="showPushBell" variant="ghost" size="icon" aria-label="Attiva le notifiche" :disabled="pushBusy" @click="activatePush">
                <Bell class="h-5 w-5" />
            </Button>
            <Button variant="ghost" size="icon" class="relative" aria-label="I tuoi dati" @click="contactFormOpen = true">
                <UserRound class="h-5 w-5" />
                <span v-if="person.needsContact" class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-amber-500" aria-hidden="true"></span>
            </Button>
        </header>

        <!-- Tab bar, managers only -->
        <nav v-if="manager" class="flex flex-wrap gap-1 border-b pb-2">
            <Button
                v-for="area in manager.areas"
                :key="area.id"
                :variant="activeTab === area.id ? 'secondary' : 'ghost'"
                size="sm"
                @click="activeTab = area.id"
            >
                {{ area.name }}
            </Button>
            <Button :variant="activeTab === 'me' ? 'secondary' : 'ghost'" size="sm" @click="activeTab = 'me'">I miei turni</Button>
        </nav>

        <!-- Area management tabs (D18) -->
        <template v-for="area in manager?.areas ?? []" :key="area.id">
            <div v-if="activeTab === area.id" class="grid gap-4">
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="toggleShiftForm"><Plus class="h-4 w-4" /> Turno</Button>
                    <Button variant="outline" size="sm" @click="toggleVolunteerForm"><UserPlus class="h-4 w-4" /> Volontario</Button>
                    <span class="flex-1"></span>
                    <Button variant="ghost" size="sm" @click="copyInvite">
                        <Check v-if="inviteCopied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        Invito
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <a :href="inviteWhatsappUrl" target="_blank" rel="noopener">Invito su WhatsApp</a>
                    </Button>
                </div>

                <form v-if="shiftFormOpen" class="grid gap-2 rounded-xl border border-dashed p-3" @submit.prevent="submitShift(area.id)">
                    <p class="text-sm font-medium">Nuovo turno in {{ area.name }}</p>
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

                <form v-if="volunteerFormOpen" class="grid gap-2 rounded-xl border border-dashed p-3" @submit.prevent="submitVolunteer">
                    <p class="text-sm font-medium">Nuovo volontario</p>
                    <Input v-model="volunteerForm.name" required placeholder="Nome e cognome" aria-label="Nome" />
                    <p v-if="volunteerForm.errors.name" class="text-sm text-red-600">{{ volunteerForm.errors.name }}</p>
                    <Input v-model="volunteerForm.phone" type="tel" placeholder="Telefono (facoltativo)" aria-label="Telefono" />
                    <p v-if="volunteerForm.errors.phone" class="text-sm text-red-600">{{ volunteerForm.errors.phone }}</p>
                    <Button type="submit" size="sm" :disabled="volunteerForm.processing">Aggiungi e crea il link</Button>
                </form>

                <p v-if="areaShifts(area.id).length === 0" class="text-sm text-muted-foreground">Nessun turno in programma. Creane uno!</p>

                <div v-for="shift in areaShifts(area.id)" :key="shift.id" class="rounded-xl border p-3">
                    <div class="flex items-start gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-foreground first-letter:uppercase">
                                {{ shortDayLabel(shift.starts_at) }} · {{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }}
                            </p>
                            <p
                                class="text-sm"
                                :class="
                                    shift.assigned_count >= shift.needed_people
                                        ? 'text-green-700 dark:text-green-400'
                                        : 'text-amber-700 dark:text-amber-400'
                                "
                            >
                                {{ shift.assigned_count }}/{{ shift.needed_people }} coperti
                            </p>
                            <p v-if="shift.notes" class="text-sm text-muted-foreground">{{ shift.notes }}</p>
                        </div>
                        <Button variant="ghost" size="icon" aria-label="Modifica turno" @click="openEditShift(shift)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" aria-label="Elimina turno" @click="destroyShift(shift)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                    <ModeratedSignupList :signups="shift.signups" :shift-id="shift.id" :people="manager?.people" />
                </div>
            </div>
        </template>

        <!-- Personal view -->
        <div v-if="activeTab === 'me'" class="grid gap-4">
            <!-- Confirmed shifts -->
            <section v-if="mine.length > 0" class="grid gap-2">
                <h2 class="flex items-center gap-2 font-medium text-foreground"><CalendarCheck class="h-4 w-4 text-green-600" /> I tuoi turni</h2>
                <div
                    v-for="shift in mine"
                    :key="shift.id"
                    class="rounded-xl border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950"
                >
                    <p class="font-medium text-foreground">{{ shift.area }} · {{ dayLabel(shift.starts_at) }}</p>
                    <p class="text-sm text-muted-foreground">{{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }} · {{ shift.event }}</p>
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
                </div>
            </section>

            <!-- Waiting for confirmation -->
            <section v-if="pending.length > 0" class="grid gap-2">
                <h2 class="flex items-center gap-2 font-medium text-foreground"><Hand class="h-4 w-4 text-amber-600" /> In attesa di conferma</h2>
                <div v-for="shift in pending" :key="shift.id" class="flex items-center gap-2 rounded-xl border p-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-foreground">{{ shift.area }} · {{ dayLabel(shift.starts_at) }}</p>
                        <p class="text-sm text-muted-foreground">{{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }}</p>
                        <p v-if="shift.myOverlap" class="text-sm text-amber-700 dark:text-amber-400">⚠ Si sovrappone con {{ shift.myOverlap }}</p>
                    </div>
                    <Button variant="ghost" size="sm" @click="withdraw(shift)"><Undo2 class="h-4 w-4" /> Ritira</Button>
                </div>
            </section>

            <!-- Open shifts (areas I don't manage) -->
            <section class="grid gap-3">
                <h2 class="font-medium text-foreground">Turni aperti</h2>
                <p v-if="open.length === 0" class="text-sm text-muted-foreground">
                    {{ manager ? 'Niente turni aperti negli altri reparti.' : 'Niente turni aperti al momento. Torna a trovarci!' }}
                </p>
                <div v-for="[day, dayShifts] in groupByDay(mainOpen)" :key="day" class="grid gap-2">
                    <h3 class="text-sm font-medium text-muted-foreground first-letter:uppercase">{{ day }}</h3>
                    <div v-for="shift in dayShifts" :key="shift.id" class="flex items-center gap-2 rounded-xl border p-3">
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

                <!-- Other areas stay reachable: uncovered shifts are everyone's problem -->
                <template v-if="splitByArea">
                    <Button variant="outline" size="sm" class="justify-self-start" @click="showOthers = !showOthers">
                        {{ showOthers ? 'Nascondi gli altri reparti' : `Altri reparti (${openOthers.length} turni)` }}
                    </Button>
                    <div v-for="[day, dayShifts] in showOthers ? groupByDay(openOthers) : []" :key="`other-${day}`" class="grid gap-2">
                        <h3 class="text-sm font-medium text-muted-foreground first-letter:uppercase">{{ day }}</h3>
                        <div v-for="shift in dayShifts" :key="shift.id" class="flex items-center gap-2 rounded-xl border p-3">
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
                </template>
            </section>
        </div>

        <!-- Profile / contacts dialog -->
        <Dialog v-model:open="contactFormOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>I tuoi dati</DialogTitle>
                    <DialogDescription> Un recapito serve per i promemoria e per recuperare l'accesso da solo se cambi telefono. </DialogDescription>
                </DialogHeader>
                <form class="grid gap-2" @submit.prevent="saveContact">
                    <Input v-model="contactForm.phone" type="tel" placeholder="Telefono: +39 333 1234567" aria-label="Telefono" />
                    <p v-if="contactForm.errors.phone" class="text-sm text-red-600">{{ contactForm.errors.phone }}</p>
                    <Input v-model="contactForm.email" type="email" placeholder="Email: nome@esempio.it" aria-label="Email" />
                    <p v-if="contactForm.errors.email" class="text-sm text-red-600">{{ contactForm.errors.email }}</p>
                    <Button type="submit" :disabled="contactForm.processing">Salva</Button>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Magic link of a freshly added volunteer -->
        <Dialog :open="magicLink !== null" @update:open="magicLink = null">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link d'accesso per {{ magicLink?.personName }}</DialogTitle>
                    <DialogDescription> Mandaglielo: toccandolo entra nei suoi turni, senza password. Funziona una volta sola. </DialogDescription>
                </DialogHeader>
                <p class="break-all rounded-md bg-muted p-3 font-mono text-sm">{{ magicLink?.url }}</p>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="copyMagicLink">
                        <Check v-if="linkCopied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        {{ linkCopied ? 'Copiato!' : 'Copia' }}
                    </Button>
                    <Button as-child>
                        <a :href="magicLinkWhatsappUrl" target="_blank" rel="noopener">Invia su WhatsApp</a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

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
