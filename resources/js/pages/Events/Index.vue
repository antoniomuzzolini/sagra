<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, phaseTypeLabels } from '@/lib/event-helpers';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface EventRow {
    id: number;
    name: string;
    startsOn: string | null;
    endsOn: string | null;
    areasCount: number;
}

const props = defineProps<{ events: EventRow[]; years: number[]; selectedYear: number | null }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Eventi', href: '/events' }];

const createOpen = ref(false);
const form = useForm({
    name: '',
    phases: [{ type: 'service', starts_on: '', ends_on: '' }],
});

function addPhase() {
    form.phases.push({ type: 'service', starts_on: '', ends_on: '' });
}

function removePhase(index: number) {
    form.phases.splice(index, 1);
}

function submit() {
    form.post(route('events.store'), { onSuccess: () => (createOpen.value = false) });
}
</script>

<template>
    <Head title="Eventi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Eventi</h1>
                <Button @click="createOpen = true">Nuovo evento</Button>
            </div>

            <div v-if="props.years.length > 1" class="flex flex-wrap gap-1">
                <Button v-for="year in props.years" :key="year" :variant="year === props.selectedYear ? 'secondary' : 'ghost'" size="sm" as-child>
                    <Link :href="route('events.index', { year })" preserve-scroll>{{ year }}</Link>
                </Button>
            </div>

            <p v-if="props.years.length === 0 && props.events.length === 0" class="text-muted-foreground">
                Nessun evento ancora. Crea il primo: nome e date delle fasi, al resto pensiamo dopo.
            </p>
            <p v-else-if="props.events.length === 0" class="text-muted-foreground">Nessun evento nel {{ props.selectedYear }}.</p>

            <ul v-if="props.events.length > 0" class="divide-y rounded-xl border">
                <li v-for="event in props.events" :key="event.id">
                    <Link :href="route('events.show', event.id)" class="flex items-center gap-2 p-3 hover:bg-muted/50">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ event.name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ formatDate(event.startsOn) }} → {{ formatDate(event.endsOn) }} · {{ event.areasCount }} aree
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>

        <Dialog v-model:open="createOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nuovo evento</DialogTitle>
                    <DialogDescription>
                        L'evento è fatto di fasi: preparazione, apertura al pubblico (anche più di una, ad esempio due weekend) e smontaggio.
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="event-name">Nome</Label>
                        <Input id="event-name" v-model="form.name" required placeholder="Sagra 2026" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-3">
                        <Label>Fasi</Label>
                        <div v-for="(phase, i) in form.phases" :key="i" class="grid grid-cols-[1fr_auto] gap-2 rounded-md border p-2">
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
                                v-if="form.phases.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                @click="removePhase(i)"
                                aria-label="Rimuovi fase"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                        <InputError :message="form.errors.phases" />
                        <Button type="button" variant="outline" size="sm" @click="addPhase"> <Plus class="h-4 w-4" /> Aggiungi fase </Button>
                    </div>

                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">Crea evento</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
