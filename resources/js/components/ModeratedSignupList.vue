<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import PersonContact from '@/components/PersonContact.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';

export interface ModeratedSignup {
    id: number;
    personId: number;
    personName: string;
    personPhone: string | null;
    personEmail: string | null;
    status: 'available' | 'assigned' | 'declined';
    substitutionRequested: boolean;
    overlapsWith: string | null;
}

const props = defineProps<{
    signups: ModeratedSignup[];
    shiftId: number;
    people?: { id: number; name: string }[];
}>();

function moderate(signup: ModeratedSignup, status: ModeratedSignup['status']) {
    router.put(route('volunteer.signups.moderate', signup.id), { status }, { preserveScroll: true });
}

function remove(signup: ModeratedSignup) {
    if (confirm(`Togliere ${signup.personName} da questo turno?`)) {
        router.delete(route('volunteer.signups.remove', signup.id), { preserveScroll: true });
    }
}

// Direct assignment ("ti metto io"): people not yet on the shift.
function assign(event: Event) {
    const select = event.target as HTMLSelectElement;
    if (select.value) {
        router.post(route('volunteer.signups.assign', props.shiftId), { person_id: Number(select.value) }, { preserveScroll: true });
        select.value = '';
    }
}
</script>

<template>
    <div v-if="signups.length > 0 || people" class="mt-2 grid gap-1 border-t pt-2">
        <div v-for="signup in signups" :key="signup.id" class="flex items-center gap-2 text-sm">
            <Avatar :name="signup.personName" :size="24" />
            <span class="min-w-0 flex-1 truncate">
                <PersonContact :name="signup.personName" :phone="signup.personPhone" :email="signup.personEmail" />
                <span
                    v-if="signup.substitutionRequested"
                    class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900 dark:text-amber-100"
                >
                    cerca un sostituto
                </span>
                <span
                    v-if="signup.overlapsWith"
                    class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-900 dark:text-amber-100"
                    :title="`Si sovrappone con ${signup.overlapsWith}`"
                >
                    ⚠ anche su {{ signup.overlapsWith }}
                </span>
            </span>
            <template v-if="signup.status === 'available'">
                <Button size="sm" @click="moderate(signup, 'assigned')">Conferma</Button>
                <Button size="sm" variant="ghost" @click="moderate(signup, 'declined')">Rifiuta</Button>
            </template>
            <template v-else-if="signup.status === 'assigned'">
                <span class="text-xs font-medium text-green-700 dark:text-green-400">confermato</span>
                <Button size="sm" variant="ghost" @click="remove(signup)">Rimuovi</Button>
            </template>
            <span v-else class="text-xs text-muted-foreground">rifiutato</span>
        </div>
        <select
            v-if="people"
            class="h-8 w-fit rounded-md border border-input bg-transparent px-2 text-xs"
            aria-label="Metti una persona sul turno"
            @change="assign($event)"
        >
            <option value="">+ metti una persona…</option>
            <option v-for="person in people.filter((p) => !signups.some((s) => s.personId === p.id))" :key="person.id" :value="person.id">
                {{ person.name }}
            </option>
        </select>
    </div>
</template>
