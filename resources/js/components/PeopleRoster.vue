<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import Pill from '@/components/Pill.vue';
import { Input } from '@/components/ui/input';
import { Search } from 'lucide-vue-next';
import { computed, ref, useSlots } from 'vue';

export type PersonRole = 'organizer' | 'manager' | 'volunteer';

export interface PersonRosterRow {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    role: PersonRole;
    areas: string[];
    shiftsCount: number;
    hasLink: boolean;
    linkLastUsedAt: string | null;
    linkRequested: boolean;
}

// The roster table, shared by the organizer's people page and the area
// manager's scoped view. Row actions (link, edit, delete) are injected via
// an #actions slot — without it the table is read-only and drops the column.
const props = defineProps<{ people: PersonRosterRow[] }>();

const slots = useSlots();
const hasActions = computed(() => !!slots.actions);

const roleLabels: Record<PersonRole, string> = {
    organizer: 'Organizzatore',
    manager: 'Responsabile',
    volunteer: 'Volontario',
};
const roleBadgeClass: Record<PersonRole, string> = {
    organizer: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100',
    manager: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100',
    volunteer: 'bg-muted text-muted-foreground',
};

// A single, honest "stato" derived from access state (D16/D17): what an
// organizer can act on. Participation lives in the "Turni" column.
function statusOf(p: PersonRosterRow): { label: string; variant: 'good' | 'warn' | 'neutral' } {
    if (p.linkRequested) return { label: 'Da ricontattare', variant: 'warn' };
    if (!p.hasLink) return { label: 'Da invitare', variant: 'neutral' };
    if (!p.linkLastUsedAt) return { label: 'Invitato', variant: 'warn' };
    return { label: 'Attivo', variant: 'good' };
}

const search = ref('');
const areaFilter = ref('');
const allAreas = computed(() => Array.from(new Set(props.people.flatMap((p) => p.areas))).sort());

const filteredPeople = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.people.filter((p) => {
        const matchesQuery = !q || [p.name, p.phone, p.email].some((v) => v?.toLowerCase().includes(q));
        const matchesArea = !areaFilter.value || p.areas.includes(areaFilter.value);
        return matchesQuery && matchesArea;
    });
});

const gridCols = computed(() =>
    hasActions.value
        ? 'sm:grid-cols-[minmax(0,2fr)_minmax(0,1.8fr)_3.5rem_7rem_7rem_auto]'
        : 'sm:grid-cols-[minmax(0,2fr)_minmax(0,1.8fr)_3.5rem_7rem_7rem]',
);
</script>

<template>
    <div class="grid gap-3">
        <!-- Toolbar: search + area filter -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-0 flex-1">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Cerca per nome, telefono o email…" class="pl-8" />
            </div>
            <select
                v-if="allAreas.length"
                v-model="areaFilter"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                aria-label="Filtra per area"
            >
                <option value="">Tutte le aree</option>
                <option v-for="area in allAreas" :key="area" :value="area">{{ area }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border">
            <div class="hidden gap-3 border-b bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground sm:grid" :class="gridCols">
                <div>Volontario</div>
                <div>Aree</div>
                <div>Turni</div>
                <div>Ruolo</div>
                <div>Stato</div>
                <div v-if="hasActions" class="text-right">Azioni</div>
            </div>

            <div class="divide-y">
                <div
                    v-for="person in filteredPeople"
                    :key="person.id"
                    class="grid grid-cols-1 gap-x-3 gap-y-2 p-3 sm:items-center sm:py-2"
                    :class="gridCols"
                >
                    <!-- Volontario -->
                    <div class="flex min-w-0 items-center gap-2">
                        <Avatar :name="person.name" :size="32" />
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ person.name }}</div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ [person.phone, person.email].filter(Boolean).join(' · ') || '—' }}
                            </div>
                        </div>
                    </div>

                    <!-- Aree -->
                    <div class="flex flex-wrap gap-1">
                        <span v-for="area in person.areas" :key="area" class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs">
                            {{ area }}
                        </span>
                        <span v-if="!person.areas.length" class="text-xs text-muted-foreground">—</span>
                    </div>

                    <!-- Turni -->
                    <div class="text-sm">
                        <span class="text-muted-foreground sm:hidden">Turni: </span>
                        <span :class="person.shiftsCount === 0 ? 'text-muted-foreground' : 'font-semibold'">{{ person.shiftsCount }}</span>
                    </div>

                    <!-- Ruolo -->
                    <div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="roleBadgeClass[person.role]">
                            {{ roleLabels[person.role] }}
                        </span>
                    </div>

                    <!-- Stato -->
                    <div>
                        <Pill :variant="statusOf(person).variant">{{ statusOf(person).label }}</Pill>
                    </div>

                    <!-- Azioni (injected) -->
                    <div v-if="hasActions" class="flex items-center gap-1 justify-self-start sm:justify-self-end">
                        <slot name="actions" :person="person" />
                    </div>
                </div>

                <p v-if="filteredPeople.length === 0" class="p-4 text-center text-sm text-muted-foreground">
                    Nessuna persona trovata con questi filtri.
                </p>
            </div>
        </div>
    </div>
</template>
