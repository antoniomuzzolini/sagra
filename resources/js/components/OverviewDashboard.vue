<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import Pill from '@/components/Pill.vue';
import Progress from '@/components/Progress.vue';
import StatCard from '@/components/StatCard.vue';
import { areaColors } from '@/lib/colors';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

// Coverage at a glance: a stats strip plus one card per area. The same
// dashboard the organizer and the area manager see, each fed its own
// (scoped) set of areas (D18: same app, scoped). Purely presentational —
// every figure is derived from the areas passed in.
export interface OverviewArea {
    id: number;
    name: string;
    family: string | null;
    needed: number;
    filled: number;
    people: string[];
    managers: string[];
}

const props = withDefaults(defineProps<{ areas: OverviewArea[]; canCreate?: boolean }>(), { canCreate: false });
const emit = defineEmits<{ select: [id: number]; create: []; uncovered: [] }>();

const totals = computed(() => {
    const needed = props.areas.reduce((s, a) => s + a.needed, 0);
    const filled = props.areas.reduce((s, a) => s + a.filled, 0);
    return {
        needed,
        filled,
        pct: needed ? Math.round((filled / needed) * 100) : 0,
        needHelp: props.areas.filter((a) => a.needed > 0 && a.filled < a.needed).length,
    };
});

// Most problematic first: areas sorted by coverage ascending. Areas with
// no shifts yet aren't "uncovered", so they sink to the bottom.
const sortedAreas = computed(() =>
    [...props.areas].sort((a, b) => {
        const ra = a.needed ? a.filled / a.needed : Infinity;
        const rb = b.needed ? b.filled / b.needed : Infinity;
        return ra - rb;
    }),
);

const managersLabel = (a: OverviewArea): string => (a.managers.length ? 'Resp. · ' + a.managers.join(', ') : 'Nessun responsabile');
const coverage = (a: OverviewArea): number => (a.needed ? Math.round((a.filled / a.needed) * 100) : 0);
const isFull = (a: OverviewArea): boolean => a.needed > 0 && a.filled >= a.needed;
</script>

<template>
    <div class="grid gap-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard :value="`${totals.filled} / ${totals.needed}`" label="Posti coperti" />
            <StatCard :value="`${totals.pct}%`" label="Copertura turni" />
            <StatCard :value="areas.length" label="Aree" />
            <StatCard
                :value="totals.needHelp"
                label="Aree da coprire"
                :accent="totals.needHelp > 0"
                :interactive="totals.needHelp > 0"
                @click="totals.needHelp > 0 && emit('uncovered')"
            />
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <button
                v-for="area in sortedAreas"
                :key="area.id"
                type="button"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4 text-left transition hover:border-foreground/30 hover:shadow-sm"
                @click="emit('select', area.id)"
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
                        <div class="truncate text-xs text-muted-foreground">{{ managersLabel(area) }}</div>
                    </div>
                    <Pill v-if="area.needed === 0" variant="neutral">Nessun turno</Pill>
                    <Pill v-else-if="isFull(area)" variant="good">Completo</Pill>
                    <Pill v-else variant="warn">Servono {{ area.needed - area.filled }}</Pill>
                </div>
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Coperti</span>
                        <span
                            ><b>{{ area.filled }}</b> <span class="text-muted-foreground">di {{ area.needed }}</span></span
                        >
                    </div>
                    <Progress class="mt-1.5" :value="coverage(area)" :full="isFull(area)" />
                </div>
                <div class="flex min-h-7 items-center">
                    <div v-if="area.people.length" class="flex -space-x-2">
                        <Avatar v-for="p in area.people.slice(0, 5)" :key="p" :name="p" :size="28" />
                        <div
                            v-if="area.people.length > 5"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-muted text-xs font-medium ring-2 ring-background"
                        >
                            +{{ area.people.length - 5 }}
                        </div>
                    </div>
                    <span v-else class="text-xs text-muted-foreground">Nessun assegnato</span>
                </div>
            </button>

            <button
                v-if="canCreate"
                type="button"
                class="flex items-center justify-center gap-2 rounded-xl border border-dashed p-4 text-sm text-muted-foreground transition hover:border-foreground/30 hover:text-foreground"
                @click="emit('create')"
            >
                <Plus class="h-4 w-4" /> Nuova area
            </button>
        </div>
    </div>
</template>
