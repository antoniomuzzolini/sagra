<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import InputError from '@/components/InputError.vue';
import PersonContact from '@/components/PersonContact.vue';
import Pill from '@/components/Pill.vue';
import Progress from '@/components/Progress.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { areaColors } from '@/lib/colors';
import { areaFamilyLabels, formatDate, formatTime, phaseTypeLabels } from '@/lib/event-helpers';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SignupRow {
    id: number;
    personName: string;
    personPhone: string | null;
    personEmail: string | null;
    status: 'available' | 'assigned' | 'declined';
    substitutionRequested: boolean;
}

interface ShiftRow {
    id: number;
    starts_at: string;
    ends_at: string;
    needed_people: number;
    assigned_count: number;
    notes: string | null;
    signups: SignupRow[];
}

interface ManagerRow {
    id: number;
    personId: number;
    name: string;
    phone: string | null;
    email: string | null;
}

interface AreaRow {
    id: number;
    name: string;
    family: string | null;
    managers: ManagerRow[];
    shifts: ShiftRow[];
}

interface PhaseRow {
    id: number;
    type: string;
    starts_on: string;
    ends_on: string;
}

const props = defineProps<{
    people: { id: number; name: string }[];
    event: {
        id: number;
        name: string;
        phases: PhaseRow[];
        areas: AreaRow[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Eventi', href: '/events' },
    { title: props.event.name, href: route('events.show', props.event.id) },
];

// Event name + phases editing
const editOpen = ref(false);
const eventForm = useForm({
    name: props.event.name,
    phases: props.event.phases.map(({ type, starts_on, ends_on }) => ({ type, starts_on, ends_on })),
});

function submitEvent() {
    eventForm.put(route('events.update', props.event.id), {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

// Top-level navigation: the overview dashboard, one tab per area, plus the
// "new area" one. Land on the overview, falling back to "new area" only when
// there's nothing to show yet.
const activeTab = ref<'overview' | number | 'new'>(props.event.areas.length ? 'overview' : 'new');
const currentArea = computed(() => (typeof activeTab.value !== 'number' ? null : (props.event.areas.find((a) => a.id === activeTab.value) ?? null)));

// Coverage derived per area (and totalled) from shift needs vs. assignments —
// the same figures shown on each area's detail, aggregated for the dashboard.
const areaStatsById = computed(() => {
    const map: Record<
        number,
        { needed: number; filled: number; pct: number; full: boolean; empty: boolean; managersLabel: string; people: string[] }
    > = {};
    for (const area of props.event.areas) {
        const needed = area.shifts.reduce((s, sh) => s + sh.needed_people, 0);
        const filled = area.shifts.reduce((s, sh) => s + sh.assigned_count, 0);
        // Distinct people actually assigned somewhere in the area, for the avatar stack.
        const people = Array.from(new Set(area.shifts.flatMap((sh) => sh.signups.filter((x) => x.status === 'assigned').map((x) => x.personName))));
        map[area.id] = {
            needed,
            filled,
            pct: needed ? Math.round((filled / needed) * 100) : 0,
            full: needed > 0 && filled >= needed,
            empty: needed === 0,
            managersLabel: area.managers.length ? 'Resp. · ' + area.managers.map((m) => m.name).join(', ') : 'Nessun responsabile',
            people,
        };
    }
    return map;
});

const overview = computed(() => {
    const stats = props.event.areas.map((a) => areaStatsById.value[a.id]);
    const needed = stats.reduce((s, x) => s + x.needed, 0);
    const filled = stats.reduce((s, x) => s + x.filled, 0);
    return {
        needed,
        filled,
        pct: needed ? Math.round((filled / needed) * 100) : 0,
        needHelp: stats.filter((x) => !x.empty && x.filled < x.needed).length,
    };
});

// New area
const areaForm = useForm({ name: '', family: '' });

function submitArea() {
    const createdName = areaForm.name;
    areaForm.post(route('areas.store', props.event.id), {
        preserveScroll: true,
        onSuccess: () => {
            areaForm.reset();
            const created = props.event.areas.find((a) => a.name === createdName);
            if (created) {
                activeTab.value = created.id;
            }
        },
    });
}

function destroyArea(area: AreaRow) {
    if (confirm(`Eliminare l'area ${area.name} e tutti i suoi turni?`)) {
        useForm({}).delete(route('areas.destroy', area.id), {
            preserveScroll: true,
            onSuccess: () => (activeTab.value = 'overview'),
        });
    }
}

// New or edited shift, one open form at a time
const shiftFormArea = ref<number | null>(null);
const editingShiftId = ref<number | null>(null);
const shiftForm = useForm({ date: '', start_time: '', end_time: '', needed_people: 2, notes: '' });

function openShiftForm(area: AreaRow) {
    shiftFormArea.value = area.id;
    editingShiftId.value = null;
    shiftForm.reset();
    shiftForm.clearErrors();
}

function openEditShift(shift: ShiftRow) {
    shiftFormArea.value = null;
    editingShiftId.value = shift.id;
    shiftForm.date = shift.starts_at.slice(0, 10);
    shiftForm.start_time = shift.starts_at.slice(11, 16);
    shiftForm.end_time = shift.ends_at.slice(11, 16);
    shiftForm.needed_people = shift.needed_people;
    shiftForm.notes = shift.notes ?? '';
    shiftForm.clearErrors();
}

function submitShift(area: AreaRow) {
    shiftForm.post(route('shifts.store', area.id), {
        preserveScroll: true,
        onSuccess: () => (shiftFormArea.value = null),
    });
}

function submitShiftEdit() {
    shiftForm.put(route('shifts.update', editingShiftId.value!), {
        preserveScroll: true,
        onSuccess: () => (editingShiftId.value = null),
    });
}

function destroyShift(shift: ShiftRow) {
    useForm({}).delete(route('shifts.destroy', shift.id), { preserveScroll: true });
}

// Area managers
function addManager(area: AreaRow, event_: Event) {
    const select = event_.target as HTMLSelectElement;
    if (select.value) {
        router.post(route('areas.managers.store', area.id), { person_id: Number(select.value) }, { preserveScroll: true });
        select.value = '';
    }
}

function removeManager(manager: ManagerRow) {
    router.delete(route('person-roles.destroy', manager.id), { preserveScroll: true });
}

// Signup moderation
function setSignupStatus(signup: SignupRow, status: SignupRow['status']) {
    router.put(route('signups.update', signup.id), { status }, { preserveScroll: true });
}

function removeSignup(signup: SignupRow) {
    if (confirm(`Togliere ${signup.personName} da questo turno?`)) {
        router.delete(route('signups.destroy', signup.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="event.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-xl font-semibold">{{ event.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        <span v-for="(phase, i) in event.phases" :key="phase.id">
                            <template v-if="i > 0"> · </template>
                            {{ phaseTypeLabels[phase.type] }} {{ formatDate(phase.starts_on) }}–{{ formatDate(phase.ends_on) }}
                        </span>
                    </p>
                </div>
                <Button variant="outline" @click="editOpen = true">Modifica</Button>
            </div>

            <!-- Top navigation: overview + one tab per area -->
            <nav class="flex flex-wrap gap-1 border-b pb-2">
                <Button :variant="activeTab === 'overview' ? 'secondary' : 'ghost'" size="sm" @click="activeTab = 'overview'"> Panoramica </Button>
                <Button
                    v-for="area in event.areas"
                    :key="area.id"
                    :variant="currentArea?.id === area.id ? 'secondary' : 'ghost'"
                    size="sm"
                    @click="activeTab = area.id"
                >
                    {{ area.name }}
                </Button>
                <Button :variant="activeTab === 'new' ? 'secondary' : 'ghost'" size="sm" @click="activeTab = 'new'">
                    <Plus class="h-4 w-4" /> Area
                </Button>
            </nav>

            <!-- Overview dashboard -->
            <div v-if="activeTab === 'overview'" class="grid gap-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard :value="`${overview.filled} / ${overview.needed}`" label="Posti coperti" />
                    <StatCard :value="`${overview.pct}%`" label="Copertura turni" />
                    <StatCard :value="event.areas.length" label="Aree" />
                    <StatCard :value="overview.needHelp" label="Da coprire" :accent="overview.needHelp > 0" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="area in event.areas"
                        :key="area.id"
                        type="button"
                        class="flex flex-col gap-3 rounded-xl border bg-card p-4 text-left transition hover:border-foreground/30 hover:shadow-sm"
                        @click="activeTab = area.id"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-sm font-semibold"
                                :style="{ background: areaColors(area.family, area.name).soft, color: areaColors(area.family, area.name).solid }"
                            >
                                {{ area.name[0]?.toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ area.name }}</div>
                                <div class="truncate text-xs text-muted-foreground">{{ areaStatsById[area.id].managersLabel }}</div>
                            </div>
                            <Pill v-if="areaStatsById[area.id].empty" variant="neutral">Nessun turno</Pill>
                            <Pill v-else-if="areaStatsById[area.id].full" variant="good">Completo</Pill>
                            <Pill v-else variant="warn">Servono {{ areaStatsById[area.id].needed - areaStatsById[area.id].filled }}</Pill>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted-foreground">Coperti</span>
                                <span
                                    ><b>{{ areaStatsById[area.id].filled }}</b>
                                    <span class="text-muted-foreground">di {{ areaStatsById[area.id].needed }}</span></span
                                >
                            </div>
                            <Progress class="mt-1.5" :value="areaStatsById[area.id].pct" :full="areaStatsById[area.id].full" />
                        </div>
                        <div class="flex min-h-7 items-center">
                            <div v-if="areaStatsById[area.id].people.length" class="flex -space-x-2">
                                <Avatar v-for="p in areaStatsById[area.id].people.slice(0, 5)" :key="p" :name="p" :size="28" />
                                <div
                                    v-if="areaStatsById[area.id].people.length > 5"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-muted text-xs font-medium ring-2 ring-background"
                                >
                                    +{{ areaStatsById[area.id].people.length - 5 }}
                                </div>
                            </div>
                            <span v-else class="text-xs text-muted-foreground">Nessun assegnato</span>
                        </div>
                    </button>

                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-xl border border-dashed p-4 text-sm text-muted-foreground transition hover:border-foreground/30 hover:text-foreground"
                        @click="activeTab = 'new'"
                    >
                        <Plus class="h-4 w-4" /> Nuova area
                    </button>
                </div>
            </div>

            <!-- New area -->
            <div v-else-if="activeTab === 'new' || !currentArea" class="grid gap-3">
                <p v-if="event.areas.length === 0" class="text-muted-foreground">
                    Nessuna area ancora. Le aree sono i posti dove si lavora: cucina, bar, cassa, pulizie…
                </p>
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitArea">
                    <div class="grid gap-1">
                        <Label for="area-name" class="text-xs">Nuova area</Label>
                        <Input id="area-name" v-model="areaForm.name" required placeholder="Cucina, bar, parcheggi…" />
                    </div>
                    <select v-model="areaForm.family" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="">Famiglia (opzionale)</option>
                        <option v-for="(label, value) in areaFamilyLabels" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <Button type="submit" :disabled="areaForm.processing"><Plus class="h-4 w-4" /> Aggiungi</Button>
                    <InputError :message="areaForm.errors.name" class="w-full" />
                </form>
            </div>

            <!-- Active area -->
            <template v-else>
                <Card v-for="area in [currentArea]" :key="area.id">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0">
                        <CardTitle class="flex items-baseline gap-2">
                            {{ area.name }}
                            <span v-if="area.family" class="text-xs font-normal text-muted-foreground">
                                {{ areaFamilyLabels[area.family] }}
                            </span>
                        </CardTitle>
                        <div class="flex gap-1">
                            <Button variant="outline" size="sm" @click="openShiftForm(area)"> <Plus class="h-4 w-4" /> Turno </Button>
                            <Button variant="ghost" size="icon" @click="destroyArea(area)" aria-label="Elimina area">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-2">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-muted-foreground">Responsabili:</span>
                            <span
                                v-for="manager in area.managers"
                                :key="manager.id"
                                class="flex items-center gap-1 rounded-full bg-muted px-2 py-0.5"
                            >
                                <PersonContact :name="manager.name" :phone="manager.phone" :email="manager.email" />
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground"
                                    @click="removeManager(manager)"
                                    aria-label="Rimuovi responsabile"
                                >
                                    ×
                                </button>
                            </span>
                            <select class="h-7 rounded-md border border-input bg-transparent px-2 text-xs" @change="addManager(area, $event)">
                                <option value="">+ aggiungi…</option>
                                <option
                                    v-for="person in props.people.filter((p) => !area.managers.some((m) => m.personId === p.id))"
                                    :key="person.id"
                                    :value="person.id"
                                >
                                    {{ person.name }}
                                </option>
                            </select>
                        </div>

                        <p v-if="area.shifts.length === 0 && shiftFormArea !== area.id" class="text-sm text-muted-foreground">
                            Nessun turno in quest'area.
                        </p>

                        <div v-for="shift in area.shifts" :key="shift.id" class="flex flex-wrap items-center gap-2 rounded-md border p-2 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">
                                    {{ formatDate(shift.starts_at) }} · {{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }}
                                </p>
                                <p v-if="shift.notes" class="text-muted-foreground">{{ shift.notes }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    shift.assigned_count >= shift.needed_people
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100'
                                "
                            >
                                {{ shift.assigned_count }}/{{ shift.needed_people }}
                            </span>
                            <Button variant="ghost" size="icon" @click="openEditShift(shift)" aria-label="Modifica turno">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" @click="destroyShift(shift)" aria-label="Elimina turno">
                                <Trash2 class="h-4 w-4" />
                            </Button>

                            <form v-if="editingShiftId === shift.id" class="grid w-full gap-2 border-t pt-2" @submit.prevent="submitShiftEdit">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Input type="date" v-model="shiftForm.date" required class="w-auto" />
                                    <Input type="time" v-model="shiftForm.start_time" required class="w-auto" />
                                    <span class="text-muted-foreground">→</span>
                                    <Input type="time" v-model="shiftForm.end_time" required class="w-auto" />
                                    <div class="flex items-center gap-1">
                                        <Input type="number" v-model.number="shiftForm.needed_people" min="1" required class="w-20" />
                                        <span class="text-sm text-muted-foreground">persone</span>
                                    </div>
                                </div>
                                <Input v-model="shiftForm.notes" placeholder="Note (opzionale)" />
                                <InputError :message="shiftForm.errors.date" />
                                <InputError :message="shiftForm.errors.start_time" />
                                <InputError :message="shiftForm.errors.end_time" />
                                <InputError :message="shiftForm.errors.needed_people" />
                                <div class="flex gap-2">
                                    <Button type="submit" size="sm" :disabled="shiftForm.processing">Salva modifiche</Button>
                                    <Button type="button" size="sm" variant="ghost" @click="editingShiftId = null">Annulla</Button>
                                </div>
                            </form>

                            <div v-if="shift.signups.length > 0" class="grid w-full gap-1 border-t pt-2">
                                <div v-for="signup in shift.signups" :key="signup.id" class="flex items-center gap-2">
                                    <span class="min-w-0 flex-1 truncate">
                                        <PersonContact :name="signup.personName" :phone="signup.personPhone" :email="signup.personEmail" />
                                        <span
                                            v-if="signup.substitutionRequested"
                                            class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900 dark:text-amber-100"
                                        >
                                            cerca un sostituto
                                        </span>
                                    </span>
                                    <template v-if="signup.status === 'available'">
                                        <Button size="sm" @click="setSignupStatus(signup, 'assigned')">Conferma</Button>
                                        <Button size="sm" variant="ghost" @click="setSignupStatus(signup, 'declined')"> Rifiuta </Button>
                                    </template>
                                    <template v-else-if="signup.status === 'assigned'">
                                        <span class="text-xs font-medium text-green-700 dark:text-green-400">confermato</span>
                                        <Button size="sm" variant="ghost" @click="removeSignup(signup)">Rimuovi</Button>
                                    </template>
                                    <span v-else class="text-xs text-muted-foreground">rifiutato</span>
                                </div>
                            </div>
                        </div>

                        <form
                            v-if="shiftFormArea === area.id"
                            class="grid gap-2 rounded-md border border-dashed p-3"
                            @submit.prevent="submitShift(area)"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <Input type="date" v-model="shiftForm.date" required class="w-auto" />
                                <Input type="time" v-model="shiftForm.start_time" required class="w-auto" />
                                <span class="text-muted-foreground">→</span>
                                <Input type="time" v-model="shiftForm.end_time" required class="w-auto" />
                                <div class="flex items-center gap-1">
                                    <Input type="number" v-model.number="shiftForm.needed_people" min="1" required class="w-20" />
                                    <span class="text-sm text-muted-foreground">persone</span>
                                </div>
                            </div>
                            <Input v-model="shiftForm.notes" placeholder="Note (opzionale)" />
                            <InputError :message="shiftForm.errors.date" />
                            <InputError :message="shiftForm.errors.start_time" />
                            <InputError :message="shiftForm.errors.end_time" />
                            <InputError :message="shiftForm.errors.needed_people" />
                            <div class="flex gap-2">
                                <Button type="submit" size="sm" :disabled="shiftForm.processing">Salva turno</Button>
                                <Button type="button" size="sm" variant="ghost" @click="shiftFormArea = null"> Annulla </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </template>
        </div>

        <!-- Edit event dialog -->
        <Dialog v-model:open="editOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Modifica evento</DialogTitle>
                    <DialogDescription>Nome e fasi dell'evento.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitEvent">
                    <div class="grid gap-2">
                        <Label for="edit-event-name">Nome</Label>
                        <Input id="edit-event-name" v-model="eventForm.name" required />
                        <InputError :message="eventForm.errors.name" />
                    </div>
                    <div class="grid gap-3">
                        <Label>Fasi</Label>
                        <div v-for="(phase, i) in eventForm.phases" :key="i" class="grid grid-cols-[1fr_auto] gap-2 rounded-md border p-2">
                            <div class="grid gap-2">
                                <select v-model="phase.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                    <option v-for="(label, value) in phaseTypeLabels" :key="value" :value="value">
                                        {{ label }}
                                    </option>
                                </select>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Input type="date" v-model="phase.starts_on" required class="w-auto" />
                                    <span class="text-muted-foreground">→</span>
                                    <Input type="date" v-model="phase.ends_on" required class="w-auto" />
                                </div>
                            </div>
                            <Button
                                v-if="eventForm.phases.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                @click="eventForm.phases.splice(i, 1)"
                                aria-label="Rimuovi fase"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <InputError :message="eventForm.errors.phases" />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="eventForm.phases.push({ type: 'service', starts_on: '', ends_on: '' })"
                        >
                            <Plus class="h-4 w-4" /> Aggiungi fase
                        </Button>
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="eventForm.processing">Salva</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
