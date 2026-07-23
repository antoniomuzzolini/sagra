<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PersonContact from '@/components/PersonContact.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { areaFamilyLabels } from '@/lib/event-helpers';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';

interface ManagerRow {
    id: number;
    personId: number;
    name: string;
    phone: string | null;
    email: string | null;
}
interface AreaRow {
    id: number;
    name: string;
    family: string | null;
    managers: ManagerRow[];
}

const props = defineProps<{
    event: { id: number; name: string } | null;
    areas: AreaRow[];
    people: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Aree', href: '/manage/areas' }];

const areaForm = useForm({ name: '', family: '' });

function submitArea() {
    if (!props.event) return;
    areaForm.post(route('areas.store', props.event.id), {
        preserveScroll: true,
        onSuccess: () => areaForm.reset(),
    });
}

function destroyArea(area: AreaRow) {
    if (confirm(`Eliminare l'area ${area.name} e tutti i suoi turni?`)) {
        router.delete(route('areas.destroy', area.id), { preserveScroll: true });
    }
}

function addManager(area: AreaRow, event: Event) {
    const select = event.target as HTMLSelectElement;
    if (select.value) {
        router.post(route('areas.managers.store', area.id), { person_id: Number(select.value) }, { preserveScroll: true });
        select.value = '';
    }
}

function removeManager(manager: ManagerRow) {
    router.delete(route('person-roles.destroy', manager.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Aree" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Aree</h1>
                <p class="text-sm text-muted-foreground">
                    I reparti di <span v-if="event" class="font-medium">{{ event.name }}</span
                    ><span v-else>questo evento</span> e chi li gestisce.
                </p>
            </div>

            <p v-if="!event" class="text-sm text-muted-foreground">
                Nessun evento selezionato. Creane uno da <strong>Eventi</strong>, poi torna qui per definirne le aree.
            </p>

            <template v-else>
                <!-- New area -->
                <form class="flex flex-wrap items-end gap-2 rounded-xl border bg-muted/30 p-3" @submit.prevent="submitArea">
                    <div class="grid gap-1">
                        <Label for="area-name" class="text-xs">Nuova area</Label>
                        <Input id="area-name" v-model="areaForm.name" required placeholder="Cucina, bar, parcheggi…" />
                    </div>
                    <select v-model="areaForm.family" class="h-10 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="">Famiglia (opzionale)</option>
                        <option v-for="(label, value) in areaFamilyLabels" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <Button type="submit" :disabled="areaForm.processing"><Plus class="h-4 w-4" /> Aggiungi</Button>
                    <InputError :message="areaForm.errors.name" class="w-full" />
                </form>

                <p v-if="areas.length === 0" class="text-sm text-muted-foreground">
                    Nessuna area ancora. Le aree sono i posti dove si lavora: cucina, bar, cassa, pulizie…
                </p>

                <!-- Area list -->
                <div v-for="area in areas" :key="area.id" class="grid gap-2 rounded-xl border p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="flex items-baseline gap-2 font-medium">
                            {{ area.name }}
                            <span v-if="area.family" class="text-xs font-normal text-muted-foreground">{{ areaFamilyLabels[area.family] }}</span>
                        </p>
                        <Button variant="ghost" size="icon" aria-label="Elimina area" @click="destroyArea(area)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-muted-foreground">Responsabili:</span>
                        <span v-for="manager in area.managers" :key="manager.id" class="flex items-center gap-1 rounded-full bg-muted px-2 py-0.5">
                            <PersonContact :name="manager.name" :phone="manager.phone" :email="manager.email" />
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                aria-label="Rimuovi responsabile"
                                @click="removeManager(manager)"
                            >
                                ×
                            </button>
                        </span>
                        <select class="h-7 rounded-md border border-input bg-transparent px-2 text-xs" @change="addManager(area, $event)">
                            <option value="">+ aggiungi…</option>
                            <option
                                v-for="person in people.filter((p) => !area.managers.some((m) => m.personId === p.id))"
                                :key="person.id"
                                :value="person.id"
                            >
                                {{ person.name }}
                            </option>
                        </select>
                    </div>
                    <p class="text-xs text-muted-foreground">Per farlo accedere come responsabile, invita l'account dalla pagina Volontari (🔑).</p>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
