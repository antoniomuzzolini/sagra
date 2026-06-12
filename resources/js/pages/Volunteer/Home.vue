<script setup lang="ts">
import ModeratedSignupList, { type ModeratedSignup } from '@/components/ModeratedSignupList.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatTime } from '@/lib/event-helpers';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarCheck, Hand, Undo2 } from 'lucide-vue-next';
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
    canModerate: boolean;
    signups: ModeratedSignup[];
}

const props = defineProps<{
    person: { name: string; needsContact: boolean };
    tenant: { name: string };
    shifts: VolunteerShift[];
}>();

// "Complete the registration": one contact unlocks reminders and recovery.
const contactFormOpen = ref(false);
const contactForm = useForm({ phone: '', email: '' });

function saveContact() {
    contactForm.put(route('volunteer.contact'), { preserveScroll: true });
}

const mine = computed(() => props.shifts.filter((s) => s.myStatus === 'assigned'));
const pending = computed(() => props.shifts.filter((s) => s.myStatus === 'available'));
const open = computed(() => props.shifts.filter((s) => s.myStatus !== 'assigned' && s.myStatus !== 'available'));

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
                <div class="mt-2">
                    <Button v-if="!shift.mySubstitutionRequested" variant="outline" size="sm" @click="requestSubstitution(shift)">
                        Non posso più: chiedi un sostituto
                    </Button>
                    <p v-else class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                        Stiamo cercando un sostituto.
                        <Button variant="ghost" size="sm" @click="cancelSubstitution(shift)">Posso di nuovo</Button>
                    </p>
                </div>
                <ModeratedSignupList v-if="shift.canModerate" :signups="shift.signups" />
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
                    </div>
                    <Button variant="ghost" size="sm" @click="withdraw(shift)"><Undo2 class="h-4 w-4" /> Ritira</Button>
                </div>
                <ModeratedSignupList v-if="shift.canModerate" :signups="shift.signups" />
            </div>
        </section>

        <!-- Open shifts -->
        <section class="grid gap-3">
            <h2 class="font-medium text-foreground">Turni aperti</h2>
            <p v-if="open.length === 0" class="text-sm text-muted-foreground">Niente turni aperti al momento. Torna a trovarci!</p>
            <div v-for="[day, dayShifts] in groupByDay(open)" :key="day" class="grid gap-2">
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
                        </div>
                        <Button size="sm" @click="signUp(shift)">Ci sono!</Button>
                    </div>
                    <ModeratedSignupList v-if="shift.canModerate" :signups="shift.signups" />
                </div>
            </div>
        </section>
    </div>
</template>
