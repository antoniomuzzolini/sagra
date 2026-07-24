<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface SubAreaOpt {
    id: number;
    name: string;
}
interface AreaOpt {
    id: number;
    name: string;
    subAreas: SubAreaOpt[];
}
interface SupplierRow {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    notes: string | null;
}
interface SupplyRow {
    id: number;
    type: string;
    description: string;
    cost: string | null;
    acquiredOn: string | null;
    notes: string | null;
    areaId: number | null;
    area: string | null;
    subArea: string | null;
    supplierId: number | null;
    supplier: string | null;
}

const props = defineProps<{
    event: { id: number; name: string } | null;
    areas: AreaOpt[];
    suppliers: SupplierRow[];
    supplies: SupplyRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Forniture', href: '/forniture' }];

const typeLabels: Record<string, string> = { purchase: 'Acquisto', rental: 'Noleggio', loan: 'Prestito' };

function subAreasFor(areaId: number | null): SubAreaOpt[] {
    return props.areas.find((a) => a.id === areaId)?.subAreas ?? [];
}

function formatCost(cost: string | null): string {
    return cost === null ? '' : `€ ${Number(cost).toFixed(2)}`;
}

// --- Add a supply ---
const emptySupply = { type: 'purchase', description: '', area_id: null as number | null, sub_area_id: null as number | null, supplier_id: null as number | null, cost: '', acquired_on: '', notes: '' };
const addOpen = ref(false);
const supplyForm = useForm({ ...emptySupply });

function submitSupply() {
    supplyForm.post(route('supplies.store'), {
        preserveScroll: true,
        onSuccess: () => {
            supplyForm.reset();
            addOpen.value = false;
        },
    });
}

// --- Edit a supply ---
const editing = ref<SupplyRow | null>(null);
const editForm = useForm({ ...emptySupply });

function openEdit(supply: SupplyRow) {
    editing.value = supply;
    editForm.type = supply.type;
    editForm.description = supply.description;
    editForm.area_id = supply.areaId;
    editForm.sub_area_id = null; // reset; sub-area belongs to the area, re-pick if needed
    editForm.supplier_id = supply.supplierId;
    editForm.cost = supply.cost ?? '';
    editForm.acquired_on = supply.acquiredOn ?? '';
    editForm.notes = supply.notes ?? '';
    editForm.clearErrors();
}

function submitEdit() {
    if (!editing.value) return;
    editForm.put(route('supplies.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}

function deleteSupply(supply: SupplyRow) {
    if (confirm(`Eliminare "${supply.description}"?`)) {
        router.delete(route('supplies.destroy', supply.id), { preserveScroll: true });
    }
}

// --- Suppliers (address book) ---
const supplierForm = useForm({ name: '', phone: '', email: '', notes: '' });

function submitSupplier() {
    supplierForm.post(route('suppliers.store'), {
        preserveScroll: true,
        onSuccess: () => supplierForm.reset(),
    });
}

const editingSupplier = ref<SupplierRow | null>(null);
const editSupplierForm = useForm({ name: '', phone: '', email: '', notes: '' });

function openEditSupplier(supplier: SupplierRow) {
    editingSupplier.value = supplier;
    editSupplierForm.name = supplier.name;
    editSupplierForm.phone = supplier.phone ?? '';
    editSupplierForm.email = supplier.email ?? '';
    editSupplierForm.notes = supplier.notes ?? '';
    editSupplierForm.clearErrors();
}

function submitEditSupplier() {
    if (!editingSupplier.value) return;
    editSupplierForm.put(route('suppliers.update', editingSupplier.value.id), {
        preserveScroll: true,
        onSuccess: () => (editingSupplier.value = null),
    });
}

function deleteSupplier(supplier: SupplierRow) {
    if (confirm(`Eliminare il fornitore ${supplier.name}? Le forniture collegate restano, senza fornitore.`)) {
        router.delete(route('suppliers.destroy', supplier.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Forniture" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Forniture</h1>
                <p class="text-sm text-muted-foreground">
                    Acquisti, noleggi e prestiti di <span v-if="event" class="font-medium">{{ event.name }}</span
                    ><span v-else>questo evento</span>, e i tuoi fornitori.
                </p>
            </div>

            <p v-if="!event" class="text-sm text-muted-foreground">
                Nessun evento selezionato. Creane uno da <strong>Eventi</strong> per registrare le forniture.
            </p>

            <template v-else>
                <div>
                    <Button v-if="!addOpen" variant="outline" size="sm" @click="addOpen = true"><Plus class="h-4 w-4" /> Aggiungi fornitura</Button>
                </div>

                <!-- Add form -->
                <form v-if="addOpen" class="grid gap-3 rounded-xl border border-dashed p-3" @submit.prevent="submitSupply">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-1">
                            <Label>Tipo</Label>
                            <select v-model="supplyForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1">
                            <Label>Cosa</Label>
                            <Input v-model="supplyForm.description" required placeholder="Es. 20 kg di farina, gazebo…" />
                            <InputError :message="supplyForm.errors.description" />
                        </div>
                        <div class="grid gap-1">
                            <Label>Area</Label>
                            <select
                                v-model="supplyForm.area_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                @change="supplyForm.sub_area_id = null"
                            >
                                <option :value="null">Nessuna (a livello evento)</option>
                                <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <div v-if="subAreasFor(supplyForm.area_id).length" class="grid gap-1">
                            <Label>Sotto-reparto</Label>
                            <select v-model="supplyForm.sub_area_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option :value="null">Tutta l'area</option>
                                <option v-for="sa in subAreasFor(supplyForm.area_id)" :key="sa.id" :value="sa.id">{{ sa.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1">
                            <Label>Fornitore</Label>
                            <select v-model="supplyForm.supplier_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option :value="null">—</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1">
                            <Label>Costo (€)</Label>
                            <Input v-model="supplyForm.cost" type="number" step="0.01" min="0" placeholder="Facoltativo" />
                        </div>
                        <div class="grid gap-1">
                            <Label>Data</Label>
                            <Input v-model="supplyForm.acquired_on" type="date" />
                        </div>
                    </div>
                    <div class="grid gap-1">
                        <Label>Note</Label>
                        <textarea v-model="supplyForm.notes" rows="2" class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="supplyForm.processing">Salva</Button>
                        <Button type="button" variant="ghost" size="sm" @click="((addOpen = false), supplyForm.reset())">Annulla</Button>
                    </div>
                </form>

                <!-- Supplies list -->
                <p v-if="supplies.length === 0" class="text-sm text-muted-foreground">Nessuna fornitura registrata per questo evento.</p>
                <ul v-else class="divide-y rounded-xl border">
                    <li v-for="supply in supplies" :key="supply.id" class="flex items-start gap-3 p-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-medium">
                                {{ supply.description }}
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-normal">{{ typeLabels[supply.type] }}</span>
                                <span v-if="supply.cost" class="text-sm font-normal text-muted-foreground">{{ formatCost(supply.cost) }}</span>
                            </p>
                            <p class="text-sm text-muted-foreground">
                                <span v-if="supply.area">{{ supply.area }}<span v-if="supply.subArea"> · {{ supply.subArea }}</span> · </span>
                                <span v-if="supply.supplier">{{ supply.supplier }} · </span>
                                <span v-if="supply.acquiredOn">{{ supply.acquiredOn }}</span>
                            </p>
                            <p v-if="supply.notes" class="text-sm text-muted-foreground">{{ supply.notes }}</p>
                        </div>
                        <Button variant="ghost" size="icon" aria-label="Modifica" @click="openEdit(supply)"><Pencil class="h-4 w-4" /></Button>
                        <Button variant="ghost" size="icon" aria-label="Elimina" @click="deleteSupply(supply)"><Trash2 class="h-4 w-4" /></Button>
                    </li>
                </ul>

                <!-- Suppliers address book -->
                <div class="mt-2 grid gap-3 rounded-xl border p-3">
                    <p class="text-sm font-medium">Fornitori</p>
                    <ul v-if="suppliers.length" class="divide-y">
                        <li v-for="s in suppliers" :key="s.id" class="flex items-center gap-2 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ s.name }}</p>
                                <p v-if="s.phone || s.email" class="text-sm text-muted-foreground">{{ [s.phone, s.email].filter(Boolean).join(' · ') }}</p>
                            </div>
                            <Button variant="ghost" size="icon" aria-label="Modifica fornitore" @click="openEditSupplier(s)"><Pencil class="h-4 w-4" /></Button>
                            <Button variant="ghost" size="icon" aria-label="Elimina fornitore" @click="deleteSupplier(s)"><Trash2 class="h-4 w-4" /></Button>
                        </li>
                    </ul>
                    <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitSupplier">
                        <div class="grid gap-1">
                            <Label class="text-xs">Nuovo fornitore</Label>
                            <Input v-model="supplierForm.name" required placeholder="Nome" class="h-9" />
                        </div>
                        <Input v-model="supplierForm.phone" placeholder="Telefono" class="h-9 w-36" />
                        <Input v-model="supplierForm.email" type="email" placeholder="Email" class="h-9 w-48" />
                        <Button type="submit" variant="outline" size="sm" :disabled="supplierForm.processing"><Plus class="h-4 w-4" /> Aggiungi</Button>
                        <InputError :message="supplierForm.errors.name" class="w-full" />
                    </form>
                </div>
            </template>
        </div>

        <!-- Edit supply dialog -->
        <Dialog :open="editing !== null" @update:open="(o: boolean) => !o && (editing = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifica fornitura</DialogTitle>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitEdit">
                    <div class="grid gap-1">
                        <Label>Tipo</Label>
                        <select v-model="editForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                            <option v-for="(label, value) in typeLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label>Cosa</Label>
                        <Input v-model="editForm.description" required />
                        <InputError :message="editForm.errors.description" />
                    </div>
                    <div class="grid gap-1">
                        <Label>Area</Label>
                        <select
                            v-model="editForm.area_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            @change="editForm.sub_area_id = null"
                        >
                            <option :value="null">Nessuna (a livello evento)</option>
                            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div v-if="subAreasFor(editForm.area_id).length" class="grid gap-1">
                        <Label>Sotto-reparto</Label>
                        <select v-model="editForm.sub_area_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                            <option :value="null">Tutta l'area</option>
                            <option v-for="sa in subAreasFor(editForm.area_id)" :key="sa.id" :value="sa.id">{{ sa.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="grid gap-1">
                            <Label>Fornitore</Label>
                            <select v-model="editForm.supplier_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option :value="null">—</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1">
                            <Label>Costo (€)</Label>
                            <Input v-model="editForm.cost" type="number" step="0.01" min="0" />
                        </div>
                    </div>
                    <div class="grid gap-1">
                        <Label>Data</Label>
                        <Input v-model="editForm.acquired_on" type="date" />
                    </div>
                    <div class="grid gap-1">
                        <Label>Note</Label>
                        <textarea v-model="editForm.notes" rows="2" class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"></textarea>
                    </div>
                    <Button type="submit" :disabled="editForm.processing">Salva modifiche</Button>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit supplier dialog -->
        <Dialog :open="editingSupplier !== null" @update:open="(o: boolean) => !o && (editingSupplier = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Modifica fornitore</DialogTitle>
                </DialogHeader>
                <form class="grid gap-3" @submit.prevent="submitEditSupplier">
                    <div class="grid gap-1">
                        <Label>Nome</Label>
                        <Input v-model="editSupplierForm.name" required />
                        <InputError :message="editSupplierForm.errors.name" />
                    </div>
                    <div class="grid gap-1">
                        <Label>Telefono</Label>
                        <Input v-model="editSupplierForm.phone" />
                    </div>
                    <div class="grid gap-1">
                        <Label>Email</Label>
                        <Input v-model="editSupplierForm.email" type="email" />
                        <InputError :message="editSupplierForm.errors.email" />
                    </div>
                    <div class="grid gap-1">
                        <Label>Note</Label>
                        <textarea v-model="editSupplierForm.notes" rows="2" class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"></textarea>
                    </div>
                    <Button type="submit" :disabled="editSupplierForm.processing">Salva</Button>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
