<script setup lang="ts">
import { areaColors } from '@/lib/colors';
import { phaseTypeLabels } from '@/lib/event-helpers';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Shift {
    id: number;
    starts_at: string;
    ends_at: string;
    needed_people: number;
    assigned_count: number;
    notes: string | null;
}
interface Area {
    id: number;
    name: string;
    family: string | null;
    shifts: Shift[];
}
interface Phase {
    type: string;
    starts_on: string;
    ends_on: string;
}

const props = defineProps<{ areas: Area[]; phases: Phase[] }>();

interface Entry {
    area: Area;
    shift: Shift;
    startH: number;
    endH: number;
}

const dayKeyOf = (iso: string): string => iso.slice(0, 10);

// Hours since the shift's own midnight. A shift that runs past midnight keeps
// its start day and simply reads beyond 24 — so the axis can stretch to it.
function hoursFromMidnight(iso: string, dayKey: string): number {
    return (new Date(iso).getTime() - new Date(dayKey + 'T00:00:00').getTime()) / 3_600_000;
}

// One mini-timeline per day that has shifts, each with its own hour axis sized
// to that day's earliest start and latest end (D15: days, not a fixed grid).
const days = computed(() => {
    const byDay = new Map<string, Entry[]>();
    for (const area of props.areas) {
        for (const shift of area.shifts) {
            const key = dayKeyOf(shift.starts_at);
            if (!byDay.has(key)) byDay.set(key, []);
            byDay.get(key)!.push({
                area,
                shift,
                startH: hoursFromMidnight(shift.starts_at, key),
                endH: hoursFromMidnight(shift.ends_at, key),
            });
        }
    }

    return Array.from(byDay.keys())
        .sort()
        .map((key) => {
            const entries = byDay.get(key)!;
            const minH = Math.floor(Math.min(...entries.map((e) => e.startH)) / 2) * 2;
            const maxH = Math.ceil(Math.max(...entries.map((e) => e.endH)) / 2) * 2;
            const span = Math.max(2, maxH - minH);
            const ticks: number[] = [];
            for (let h = minH; h <= maxH; h += 2) ticks.push(h);
            const rows = props.areas.map((area) => ({ area, items: entries.filter((e) => e.area.id === area.id) })).filter((r) => r.items.length > 0);
            const phase = props.phases.find((p) => key >= p.starts_on && key <= p.ends_on);
            return { key, minH, span, ticks, rows, phaseLabel: phase ? (phaseTypeLabels[phase.type] ?? phase.type) : null };
        });
});

const pct = (value: number, minH: number, span: number): number => ((value - minH) / span) * 100;
const tickLabel = (h: number): string => String(((h % 24) + 24) % 24).padStart(2, '0') + ':00';
const fmtDay = (key: string): string =>
    new Date(key + 'T00:00:00').toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric', month: 'numeric' });
const fmtTime = (iso: string): string => new Date(iso).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });

// Day picker ("calendarietto"): jump to a day, and follow the scroll so the
// active chip tracks what's on screen.
const activeDay = ref<string | null>(null);
const sections = new Map<string, HTMLElement>();
function setSection(key: string, el: unknown) {
    if (el instanceof HTMLElement) {
        sections.set(key, el);
        observer?.observe(el);
    } else {
        sections.delete(key);
    }
}
function jumpTo(key: string) {
    activeDay.value = key;
    sections.get(key)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

let observer: IntersectionObserver | null = null;
onMounted(() => {
    activeDay.value = days.value[0]?.key ?? null;
    observer = new IntersectionObserver(
        (entries) => {
            const top = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
            const key = top && (top.target as HTMLElement).dataset.day;
            if (key) activeDay.value = key;
        },
        { rootMargin: '-12% 0px -80% 0px' },
    );
    sections.forEach((el) => observer!.observe(el));
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div v-if="days.length === 0" class="text-sm text-muted-foreground">
        Nessun turno ancora. Aggiungi turni dalle aree per vederli sul calendario.
    </div>
    <div v-else class="grid gap-4">
        <!-- Day picker -->
        <div class="sticky top-0 z-10 -mx-1 flex gap-1 overflow-x-auto bg-background/95 px-1 py-2 backdrop-blur">
            <button
                v-for="day in days"
                :key="day.key"
                type="button"
                class="shrink-0 rounded-full border px-3 py-1 text-xs font-medium capitalize transition"
                :class="activeDay === day.key ? 'border-foreground bg-foreground text-background' : 'hover:border-foreground/30'"
                @click="jumpTo(day.key)"
            >
                {{ fmtDay(day.key) }}
            </button>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-4 text-xs text-muted-foreground">
            <div class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm bg-green-500"></span>Completo</div>
            <div class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm bg-amber-500"></span>Servono volontari</div>
        </div>

        <!-- One section per day -->
        <section v-for="day in days" :key="day.key" :ref="(el) => setSection(day.key, el)" :data-day="day.key" class="grid scroll-mt-16 gap-2">
            <div class="flex items-baseline gap-2">
                <h3 class="font-semibold capitalize">{{ fmtDay(day.key) }}</h3>
                <span v-if="day.phaseLabel" class="text-xs text-muted-foreground">{{ day.phaseLabel }}</span>
            </div>

            <div class="rounded-xl border p-3">
                <!-- Hour axis -->
                <div class="flex items-end gap-2">
                    <div class="w-[5.5rem] shrink-0"></div>
                    <div class="relative h-4 flex-1">
                        <div
                            v-for="h in day.ticks"
                            :key="h"
                            class="absolute -translate-x-1/2 text-[10px] text-muted-foreground"
                            :style="{ left: pct(h, day.minH, day.span) + '%' }"
                        >
                            {{ tickLabel(h) }}
                        </div>
                    </div>
                </div>

                <!-- Area lanes -->
                <div v-for="row in day.rows" :key="row.area.id" class="flex items-center gap-2 py-1">
                    <div class="flex w-[5.5rem] shrink-0 items-center gap-1.5 overflow-hidden">
                        <span class="h-2 w-2 shrink-0 rounded-full" :style="{ background: areaColors(row.area.family, row.area.name).solid }"></span>
                        <span class="truncate text-sm">{{ row.area.name }}</span>
                    </div>
                    <div class="relative h-8 flex-1 rounded bg-muted/40">
                        <div
                            v-for="h in day.ticks"
                            :key="h"
                            class="absolute top-0 h-full w-px bg-border"
                            :style="{ left: pct(h, day.minH, day.span) + '%' }"
                        ></div>
                        <div
                            v-for="e in row.items"
                            :key="e.shift.id"
                            class="absolute top-1 flex h-6 items-center justify-center overflow-hidden rounded px-1 text-[11px] font-medium text-white"
                            :class="e.shift.assigned_count >= e.shift.needed_people ? 'bg-green-500' : 'bg-amber-500'"
                            :style="{
                                left: pct(e.startH, day.minH, day.span) + '%',
                                width: Math.max(4, pct(e.endH, day.minH, day.span) - pct(e.startH, day.minH, day.span)) + '%',
                            }"
                            :title="`${row.area.name} · ${fmtTime(e.shift.starts_at)}–${fmtTime(e.shift.ends_at)} · ${e.shift.assigned_count}/${e.shift.needed_people}${e.shift.notes ? ' · ' + e.shift.notes : ''}`"
                        >
                            {{ e.shift.assigned_count }}/{{ e.shift.needed_people }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
