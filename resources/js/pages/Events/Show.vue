<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, phaseTypeLabels } from '@/lib/event-helpers';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Boxes, ClipboardList, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

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
        areas: { id: number; name: string }[];
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

function destroyEvent() {
    if (confirm(`Eliminare l'evento ${props.event.name} con tutte le sue aree e turni?`)) {
        router.delete(route('events.destroy', props.event.id));
    }
}

// Areas and shifts are managed in their own pages, scoped to the current
// event (D20): make this edition current, then jump there.
function manage(path: string) {
    router.post('/current-event', { event_id: props.event.id }, {
        preserveScroll: true,
        onSuccess: () => router.visit(path),
    });
}
</script>

<template>
    <Head :title="event.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h1 class="text-xl font-semibold">{{ event.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        <span v-for="(phase, i) in event.phases" :key="phase.id">
                            <template v-if="i > 0"> · </template>
                            {{ phaseTypeLabels[phase.type] }} {{ formatDate(phase.starts_on) }}–{{ formatDate(phase.ends_on) }}
                        </span>
                        <span v-if="event.phases.length === 0">Nessuna fase.</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="editOpen = true">Modifica</Button>
                    <Button variant="ghost" size="icon" aria-label="Elimina evento" @click="destroyEvent">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Areas and shifts live in their own pages (D20) -->
            <div class="grid gap-3 rounded-xl border bg-muted/30 p-4">
                <p class="text-sm text-muted-foreground">
                    {{ event.areas.length }} {{ event.areas.length === 1 ? 'area definita' : 'aree definite' }}. Aree, responsabili e turni si
                    gestiscono nelle loro pagine.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="manage('/manage/areas')"><Boxes class="h-4 w-4" /> Gestisci aree</Button>
                    <Button variant="outline" @click="manage('/manage/shifts')"><ClipboardList class="h-4 w-4" /> Gestisci turni</Button>
                </div>
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
                                aria-label="Rimuovi fase"
                                @click="eventForm.phases.splice(i, 1)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <InputError :message="eventForm.errors.phases" />
                        <Button type="button" variant="outline" size="sm" @click="eventForm.phases.push({ type: 'service', starts_on: '', ends_on: '' })">
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
