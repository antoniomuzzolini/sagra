<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { areaColors } from '@/lib/colors';
import { formatDayLong, formatTime } from '@/lib/event-helpers';
import { enablePush, pushDenied, pushSupported } from '@/lib/push';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Bell, CalendarCheck, Hand, Undo2, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface VolunteerShift {
    id: number;
    event: string;
    area: string;
    areaId: number;
    areaFamily: string | null;
    starts_at: string;
    ends_at: string;
    needed_people: number;
    assigned_count: number;
    notes: string | null;
    myStatus: 'available' | 'assigned' | 'declined' | null;
    mySubstitutionRequested: boolean;
    isMyArea: boolean;
    myOverlap: string | null;
}

const props = defineProps<{
    person: { name: string; phone: string | null; email: string | null; needsContact: boolean; hasPush: boolean };
    tenant: { name: string };
    vapidPublicKey: string | null;
    shifts: VolunteerShift[];
}>();

const page = usePage<SharedData>();

// Account holders (organizer, area manager) get the shell — same sidebar as
// the rest of the site (D19). Plain volunteers keep the focused standalone
// page: a sidebar with a single entry would be noise for them.
const isAccountHolder = computed(() => page.props.auth.role === 'organizer' || page.props.auth.role === 'manager');
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Prenotazione turni', href: '/me' }];

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

// My commitments anywhere, plus open shifts I can still sign up for
// (those I manage are handled in "Gestione turni", not here).
const mine = computed(() => props.shifts.filter((s) => s.myStatus === 'assigned'));
const pending = computed(() => props.shifts.filter((s) => s.myStatus === 'available'));
// Every shift I'm not already on, mine to book (D20: Prenotazione is
// universal — a manager can work a shift in their own area too).
const open = computed(() => props.shifts.filter((s) => s.myStatus !== 'assigned' && s.myStatus !== 'available'));

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

const dayLabel = formatDayLong;

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
    <Head title="Prenotazione turni" />

    <component
        :is="isAccountHolder ? AppLayout : 'div'"
        :breadcrumbs="isAccountHolder ? breadcrumbs : undefined"
        :class="isAccountHolder ? undefined : 'mx-auto flex min-h-screen max-w-lg flex-col gap-4 bg-background p-4 pb-16'"
    >
        <div :class="isAccountHolder ? 'mx-auto flex w-full max-w-2xl flex-col gap-4 p-4 pb-16' : 'contents'">
            <!-- Account holders get the standard page header, like every other
                 page in the shell; the greeting + profile button belong to the
                 volunteer's standalone page (their only page). -->
            <header v-if="isAccountHolder" class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-semibold">Prenotazione turni</h1>
                    <p class="text-sm text-muted-foreground">I tuoi impegni e i turni aperti: tocca "Ci sono!" per prenotarti.</p>
                </div>
                <Button v-if="showPushBell" variant="ghost" size="icon" aria-label="Attiva le notifiche" :disabled="pushBusy" @click="activatePush">
                    <Bell class="h-5 w-5" />
                </Button>
            </header>
            <header v-else class="flex items-start gap-2 pt-2">
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

        <!-- Prenotazione turni (D20): the participative view, identical for
             every role. Managing shifts lives in "Gestione turni". -->
        <div class="grid gap-4">
            <!-- Confirmed shifts -->
            <section v-if="mine.length > 0" class="grid gap-2">
                <h2 class="flex items-center gap-2 font-medium text-foreground"><CalendarCheck class="h-4 w-4 text-green-600" /> I tuoi turni</h2>
                <div
                    v-for="shift in mine"
                    :key="shift.id"
                    class="rounded-xl border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950"
                >
                    <p class="flex items-center gap-1.5 font-medium text-foreground">
                        <span class="h-2 w-2 shrink-0 rounded-full" :style="{ background: areaColors(shift.areaFamily, shift.area).solid }"></span>
                        {{ shift.area }} · {{ dayLabel(shift.starts_at) }}
                    </p>
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
                        <p class="flex items-center gap-1.5 font-medium text-foreground">
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :style="{ background: areaColors(shift.areaFamily, shift.area).solid }"
                            ></span>
                            {{ shift.area }} · {{ dayLabel(shift.starts_at) }}
                        </p>
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
                            <p class="flex items-center gap-1.5 font-medium text-foreground">
                                <span
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :style="{ background: areaColors(shift.areaFamily, shift.area).solid }"
                                ></span>
                                {{ shift.area }}
                            </p>
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
                                <p class="flex items-center gap-1.5 font-medium text-foreground">
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :style="{ background: areaColors(shift.areaFamily, shift.area).solid }"
                                    ></span>
                                    {{ shift.area }}
                                </p>
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

        </div>
    </component>
</template>
