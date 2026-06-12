<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';

export interface ModeratedSignup {
    id: number;
    personName: string;
    status: 'available' | 'assigned' | 'declined';
    substitutionRequested: boolean;
}

defineProps<{ signups: ModeratedSignup[] }>();

function moderate(signup: ModeratedSignup, status: ModeratedSignup['status']) {
    router.put(route('volunteer.signups.moderate', signup.id), { status }, { preserveScroll: true });
}

function remove(signup: ModeratedSignup) {
    if (confirm(`Togliere ${signup.personName} da questo turno?`)) {
        router.delete(route('volunteer.signups.remove', signup.id), { preserveScroll: true });
    }
}
</script>

<template>
    <div v-if="signups.length > 0" class="mt-2 grid gap-1 border-t pt-2">
        <div v-for="signup in signups" :key="signup.id" class="flex items-center gap-2 text-sm">
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
                <Button size="sm" @click="moderate(signup, 'assigned')">Conferma</Button>
                <Button size="sm" variant="ghost" @click="moderate(signup, 'declined')">Rifiuta</Button>
            </template>
            <template v-else-if="signup.status === 'assigned'">
                <span class="text-xs font-medium text-green-700 dark:text-green-400">confermato</span>
                <Button size="sm" variant="ghost" @click="remove(signup)">Rimuovi</Button>
            </template>
            <span v-else class="text-xs text-muted-foreground">rifiutato</span>
        </div>
    </div>
</template>
