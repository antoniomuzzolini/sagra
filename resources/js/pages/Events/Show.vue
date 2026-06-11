<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { areaFamilyLabels, formatDate, formatTime, phaseTypeLabels } from '@/lib/event-helpers';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface SignupRow {
    id: number;
    personName: string;
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

interface AreaRow {
    id: number;
    name: string;
    family: string | null;
    shifts: ShiftRow[];
}

interface PhaseRow {
    id: number;
    type: string;
    starts_on: string;
    ends_on: string;
}

const props = defineProps<{
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

// New area
const areaForm = useForm({ name: '', family: '' });

function submitArea() {
    areaForm.post(route('areas.store', props.event.id), {
        preserveScroll: true,
        onSuccess: () => areaForm.reset(),
    });
}

function destroyArea(area: AreaRow) {
    if (confirm(`Eliminare l'area ${area.name} e tutti i suoi turni?`)) {
        useForm({}).delete(route('areas.destroy', area.id), { preserveScroll: true });
    }
}

// New shift, one open form at a time
const shiftFormArea = ref<number | null>(null);
const shiftForm = useForm({ date: '', start_time: '', end_time: '', needed_people: 2, notes: '' });

function openShiftForm(area: AreaRow) {
    shiftFormArea.value = area.id;
    shiftForm.reset();
    shiftForm.clearErrors();
}

function submitShift(area: AreaRow) {
    shiftForm.post(route('shifts.store', area.id), {
        preserveScroll: true,
        onSuccess: () => (shiftFormArea.value = null),
    });
}

function destroyShift(shift: ShiftRow) {
    useForm({}).delete(route('shifts.destroy', shift.id), { preserveScroll: true });
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

            <!-- New area -->
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

            <p v-if="event.areas.length === 0" class="text-muted-foreground">
                Nessuna area ancora. Le aree sono i posti dove si lavora: cucina, bar, cassa, pulizie…
            </p>

            <!-- Areas with shifts -->
            <div class="grid gap-4">
                <Card v-for="area in event.areas" :key="area.id">
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
                            <Button variant="ghost" size="icon" @click="destroyShift(shift)" aria-label="Elimina turno">
                                <Trash2 class="h-4 w-4" />
                            </Button>

                            <div v-if="shift.signups.length > 0" class="grid w-full gap-1 border-t pt-2">
                                <div v-for="signup in shift.signups" :key="signup.id" class="flex items-center gap-2">
                                    <span class="min-w-0 flex-1 truncate">
                                        {{ signup.personName }}
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
            </div>
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
