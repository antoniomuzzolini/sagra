<script setup lang="ts">
import ModeratedSignupList, { type ModeratedSignup } from '@/components/ModeratedSignupList.vue';
import Pill from '@/components/Pill.vue';
import ShiftFields from '@/components/ShiftFields.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { formatDayLong, formatDayShort, formatTime } from '@/lib/event-helpers';
import { type MagicLinkFlash, type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, CopyPlus, Pencil, Plus, Share2, Trash2, UserPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export interface ManagerShift {
    id: number;
    areaId: number;
    area: string;
    starts_at: string;
    ends_at: string;
    needed_people: number;
    assigned_count: number;
    notes: string | null;
    signups: ModeratedSignup[];
}

const props = defineProps<{
    areas: { id: number; name: string }[];
    people: { id: number; name: string }[];
    inviteUrl: string;
    shifts: ManagerShift[];
}>();

const shortDayLabel = formatDayShort;

// One tab per managed area (D19/D20: scoped to the current event upstream).
const activeArea = ref<number | null>(props.areas[0]?.id ?? null);
watch(
    () => props.areas,
    (areas) => {
        if (!areas.some((a) => a.id === activeArea.value)) activeArea.value = areas[0]?.id ?? null;
    },
);

function areaShifts(areaId: number): ManagerShift[] {
    return props.shifts.filter((s) => s.areaId === areaId);
}

// Shifts of a day belong together: grouped view with per-day actions
// (replicate the whole day, delete it).
function areaDays(areaId: number): [string, ManagerShift[]][] {
    const groups = new Map<string, ManagerShift[]>();
    for (const shift of areaShifts(areaId)) {
        const day = shift.starts_at.slice(0, 10);
        groups.set(day, [...(groups.get(day) ?? []), shift]);
    }
    return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b));
}

// Replicate a whole day onto another date (times, headcount, notes travel;
// signups don't).
const replicating = ref<{ areaId: number; sourceDate: string } | null>(null);
const replicateForm = useForm({ source_date: '', target_date: '' });

function openReplicateDay(areaId: number, day: string) {
    replicating.value = { areaId, sourceDate: day };
    replicateForm.source_date = day;
    // Prefill with the source day so the date picker opens on it; the
    // backend rejects replicating onto the same day.
    replicateForm.target_date = day;
    replicateForm.clearErrors();
}

function submitReplicateDay() {
    if (replicating.value) {
        replicateForm.post(route('shifts.replicate-day', replicating.value.areaId), {
            preserveScroll: true,
            onSuccess: () => (replicating.value = null),
        });
    }
}

function destroyDay(areaId: number, day: string) {
    if (confirm(`Eliminare tutti i turni di ${formatDayLong(day)}? Le iscrizioni andranno perse.`)) {
        router.delete(route('shifts.destroy-day', [areaId, day]), { preserveScroll: true });
    }
}

// Create a shift
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

function destroyShift(shift: ManagerShift) {
    if (confirm(`Eliminare il turno di ${shortDayLabel(shift.starts_at)} (${formatTime(shift.starts_at)}–${formatTime(shift.ends_at)})?`)) {
        router.delete(route('volunteer.shifts.destroy', shift.id), { preserveScroll: true });
    }
}

// Edit a shift
const editingShift = ref<ManagerShift | null>(null);
const editForm = useForm({ date: '', start_time: '', end_time: '', needed_people: 2, notes: '' });

function openEditShift(shift: ManagerShift) {
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

// Recruit a volunteer in person: created here, magic link in hand.
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

// Invite link (self-registration)
const inviteDialogOpen = ref(false);
const inviteCopied = ref(false);

async function copyInvite() {
    await navigator.clipboard.writeText(props.inviteUrl);
    inviteCopied.value = true;
    setTimeout(() => (inviteCopied.value = false), 2000);
}

const inviteWhatsappUrl = computed(() => 'https://wa.me/?text=' + encodeURIComponent(`Dai una mano anche tu! Registrati qui: ${props.inviteUrl}`));
</script>

<template>
    <div v-if="areas.length === 0" class="text-sm text-muted-foreground">
        Nessuna area da gestire in questo evento. Le aree si definiscono in "Eventi".
    </div>
    <div v-else class="grid gap-4">
        <!-- Area tabs -->
        <nav class="flex flex-wrap gap-1 border-b pb-2">
            <Button
                v-for="area in areas"
                :key="area.id"
                :variant="activeArea === area.id ? 'secondary' : 'ghost'"
                size="sm"
                @click="activeArea = area.id"
            >
                {{ area.name }}
            </Button>
        </nav>

        <template v-for="area in areas" :key="area.id">
            <div v-if="activeArea === area.id" class="grid gap-4">
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="toggleShiftForm"><Plus class="h-4 w-4" /> Turno</Button>
                    <Button variant="outline" size="sm" @click="toggleVolunteerForm"><UserPlus class="h-4 w-4" /> Volontario</Button>
                    <span class="flex-1"></span>
                    <Button variant="outline" size="sm" @click="inviteDialogOpen = true"><Share2 class="h-4 w-4" /> Invita volontari</Button>
                </div>

                <form v-if="shiftFormOpen" class="grid gap-3 rounded-xl border border-dashed p-3" @submit.prevent="submitShift(area.id)">
                    <p class="text-sm font-medium">Nuovo turno in {{ area.name }}</p>
                    <ShiftFields :form="shiftForm" />
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

                <!-- One section per day, with whole-day actions -->
                <section v-for="[day, dayShifts] in areaDays(area.id)" :key="day" class="grid gap-2">
                    <div class="flex items-center justify-between gap-2 border-b pb-1">
                        <h3 class="text-sm font-semibold text-foreground first-letter:uppercase">{{ formatDayLong(day) }}</h3>
                        <div class="flex gap-1">
                            <Button variant="ghost" size="sm" @click="openReplicateDay(area.id, day)">
                                <CopyPlus class="h-4 w-4" /> Replica
                            </Button>
                            <Button variant="ghost" size="icon" aria-label="Elimina giornata" @click="destroyDay(area.id, day)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div v-for="shift in dayShifts" :key="shift.id" class="rounded-xl border p-3">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-foreground">{{ formatTime(shift.starts_at) }}–{{ formatTime(shift.ends_at) }}</p>
                                <div class="mt-0.5 flex items-center gap-2 text-sm">
                                    <Pill :variant="shift.assigned_count >= shift.needed_people ? 'good' : 'warn'">
                                        {{ shift.assigned_count >= shift.needed_people ? 'Completo' : `Servono ${shift.needed_people - shift.assigned_count}` }}
                                    </Pill>
                                    <span class="text-muted-foreground">{{ shift.assigned_count }}/{{ shift.needed_people }}</span>
                                </div>
                                <p v-if="shift.notes" class="text-sm text-muted-foreground">{{ shift.notes }}</p>
                            </div>
                            <Button variant="ghost" size="icon" aria-label="Modifica turno" @click="openEditShift(shift)">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" aria-label="Elimina turno" @click="destroyShift(shift)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <ModeratedSignupList :signups="shift.signups" :shift-id="shift.id" :people="people" />
                    </div>
                </section>
            </div>
        </template>

        <!-- Edit shift dialog -->
        <Dialog :open="editingShift !== null" @update:open="editingShift = null">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifica turno</DialogTitle>
                    <DialogDescription>Cambia orario, fabbisogno o note del turno.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitEditShift">
                    <ShiftFields :form="editForm" />
                    <Button type="submit" :disabled="editForm.processing">Salva modifiche</Button>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Replicate a whole day onto another date -->
        <Dialog :open="replicating !== null" @update:open="(open: boolean) => !open && (replicating = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Replica giornata</DialogTitle>
                    <DialogDescription>
                        Copia tutti i turni di
                        <b class="first-letter:uppercase">{{ replicating ? formatDayLong(replicating.sourceDate) : '' }}</b> su un altro giorno:
                        orari, persone richieste e note. Le iscrizioni non vengono copiate.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitReplicateDay">
                    <div class="grid gap-2">
                        <Label for="asb-replicate-target">Copia su</Label>
                        <Input id="asb-replicate-target" v-model="replicateForm.target_date" type="date" required />
                        <InputError :message="replicateForm.errors.target_date" />
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="replicateForm.processing">Replica</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Invite link dialog -->
        <Dialog v-model:open="inviteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invita volontari</DialogTitle>
                    <DialogDescription>
                        Condividi questo link, ad esempio nel gruppo WhatsApp: chi lo apre si registra da solo con il suo nome e arriva subito ai turni.
                    </DialogDescription>
                </DialogHeader>
                <p class="break-all rounded-md bg-muted p-3 font-mono text-sm">{{ inviteUrl }}</p>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="copyInvite">
                        <Check v-if="inviteCopied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        {{ inviteCopied ? 'Copiato!' : 'Copia' }}
                    </Button>
                    <Button as-child>
                        <a :href="inviteWhatsappUrl" target="_blank" rel="noopener">Condividi su WhatsApp</a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Magic link dialog (freshly created volunteer) -->
        <Dialog :open="magicLink !== null" @update:open="magicLink = null">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link d'accesso per {{ magicLink?.personName }}</DialogTitle>
                    <DialogDescription>Invia questo link al volontario: toccandolo entra nei suoi turni, senza password.</DialogDescription>
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
    </div>
</template>
