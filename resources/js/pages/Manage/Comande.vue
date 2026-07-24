<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Check, Undo2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface SubAreaOpt {
    id: number;
    name: string;
}
interface AreaOpt {
    id: number;
    name: string;
    subAreas: SubAreaOpt[];
}
interface ItemRow {
    id: number;
    name: string;
    quantity: number;
    status: 'pending' | 'ready' | 'served';
    orderNumber: number | null;
    orderedAt: string | null;
}

const props = defineProps<{
    event: { id: number; name: string } | null;
    areas: AreaOpt[];
    areaId: number | null;
    subAreaId: number | null;
    items: ItemRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Comande', href: '/comande' }];

const currentArea = computed(() => props.areas.find((a) => a.id === props.areaId) ?? null);

// Queue first, then what's waiting to be handed over.
const pending = computed(() => props.items.filter((i) => i.status === 'pending'));
const ready = computed(() => props.items.filter((i) => i.status === 'ready'));

function pickScreen(areaId: number | null, subAreaId: number | null) {
    router.get('/comande', { area: areaId ?? undefined, sub_area: subAreaId ?? undefined }, { preserveState: false });
}

function setStatus(item: ItemRow, status: ItemRow['status']) {
    router.put(route('kitchen.update', item.id), { status }, { preserveScroll: true, preserveState: false });
}

// A kitchen screen must update itself: poll while the tab is visible.
let timer: number | undefined;
const paused = ref(false);

function tick() {
    if (!document.hidden && !paused.value) {
        router.reload({ only: ['items'] });
    }
}

onMounted(() => {
    timer = window.setInterval(tick, 15000);
});
onBeforeUnmount(() => window.clearInterval(timer));

function waitedMinutes(iso: string | null): number | null {
    if (!iso) return null;
    return Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
}
</script>

<template>
    <Head title="Comande" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Comande</h1>
                <p class="text-sm text-muted-foreground">
                    Cosa preparare<span v-if="currentArea"> in {{ currentArea.name }}</span
                    ><span v-if="event"> · {{ event.name }}</span
                    >. La pagina si aggiorna da sola.
                </p>
            </div>

            <p v-if="!event" class="text-sm text-muted-foreground">Nessun evento selezionato.</p>
            <p v-else-if="areas.length === 0" class="text-sm text-muted-foreground">Nessuna area da gestire in questo evento.</p>

            <template v-else>
                <!-- Screen picker: area, then optionally one sub-reparto -->
                <nav class="flex flex-wrap gap-1 border-b pb-2">
                    <Button
                        v-for="a in areas"
                        :key="a.id"
                        :variant="a.id === areaId ? 'secondary' : 'ghost'"
                        size="sm"
                        @click="pickScreen(a.id, null)"
                    >
                        {{ a.name }}
                    </Button>
                </nav>
                <nav v-if="currentArea && currentArea.subAreas.length" class="flex flex-wrap gap-1">
                    <Button :variant="subAreaId === null ? 'secondary' : 'ghost'" size="sm" @click="pickScreen(areaId, null)">Tutta l'area</Button>
                    <Button
                        v-for="sa in currentArea.subAreas"
                        :key="sa.id"
                        :variant="sa.id === subAreaId ? 'secondary' : 'ghost'"
                        size="sm"
                        @click="pickScreen(areaId, sa.id)"
                    >
                        {{ sa.name }}
                    </Button>
                </nav>

                <!-- To prepare -->
                <section class="grid gap-2">
                    <h2 class="font-medium">Da preparare <span class="text-muted-foreground">({{ pending.length }})</span></h2>
                    <p v-if="pending.length === 0" class="rounded-xl border p-4 text-sm text-muted-foreground">Niente in coda. Tutto sotto controllo!</p>
                    <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="item in pending" :key="item.id" class="grid gap-2 rounded-xl border bg-card p-3">
                            <div class="flex items-baseline gap-2">
                                <span class="text-2xl font-semibold tabular-nums">{{ item.quantity }}×</span>
                                <span class="min-w-0 flex-1 font-medium">{{ item.name }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                #{{ item.orderNumber }}
                                <span v-if="waitedMinutes(item.orderedAt) !== null"> · {{ waitedMinutes(item.orderedAt) }} min</span>
                            </p>
                            <Button size="sm" @click="setStatus(item, 'ready')"><Check class="h-4 w-4" /> Pronto</Button>
                        </div>
                    </div>
                </section>

                <!-- Ready, waiting to be handed over -->
                <section v-if="ready.length" class="grid gap-2">
                    <h2 class="font-medium">Pronto da consegnare <span class="text-muted-foreground">({{ ready.length }})</span></h2>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="item in ready"
                            :key="item.id"
                            class="grid gap-2 rounded-xl border border-green-200 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950"
                        >
                            <div class="flex items-baseline gap-2">
                                <span class="text-2xl font-semibold tabular-nums">{{ item.quantity }}×</span>
                                <span class="min-w-0 flex-1 font-medium">{{ item.name }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground">#{{ item.orderNumber }}</p>
                            <div class="flex gap-2">
                                <Button size="sm" class="flex-1" @click="setStatus(item, 'served')">Consegnato</Button>
                                <Button variant="ghost" size="icon" aria-label="Rimetti in coda" @click="setStatus(item, 'pending')">
                                    <Undo2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
