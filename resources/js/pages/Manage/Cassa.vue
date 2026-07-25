<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Minus, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SubAreaOpt {
    id: number;
    name: string;
}
interface AreaOpt {
    id: number;
    name: string;
    subAreas: SubAreaOpt[];
}
interface ProductRow {
    id: number;
    name: string;
    price: string;
    areaId: number | null;
    area: string | null;
    subAreaId: number | null;
}
interface OrderRow {
    id: number;
    number: number;
    total: string;
    paid: boolean;
    createdAt: string;
    items: { name: string; quantity: number }[];
}

interface TillRow {
    id: number;
    name: string;
    areaId: number | null;
    canManage: boolean;
    productIds: number[];
}

const props = defineProps<{
    event: { id: number; name: string } | null;
    canManageListino: boolean;
    areas: AreaOpt[];
    products: ProductRow[];
    orders: OrderRow[];
    tills: TillRow[];
    currentTillId: number | null;
    listino: { id: number; name: string; price: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cassa', href: '/cassa' }];

const euro = (v: number | string) => `€ ${Number(v).toFixed(2)}`;

function subAreasFor(areaId: number | null): SubAreaOpt[] {
    return props.areas.find((a) => a.id === areaId)?.subAreas ?? [];
}

const productsByArea = computed(() => {
    const groups = new Map<string, ProductRow[]>();
    for (const p of props.products) {
        const key = p.area ?? 'Altro';
        groups.set(key, [...(groups.get(key) ?? []), p]);
    }
    return [...groups.entries()];
});

// --- Cart ---
interface Line {
    productId: number;
    name: string;
    price: number;
    quantity: number;
}
const cart = ref<Line[]>([]);
const cartTotal = computed(() => cart.value.reduce((s, l) => s + l.price * l.quantity, 0));

function addToCart(p: ProductRow) {
    const line = cart.value.find((l) => l.productId === p.id);
    if (line) line.quantity++;
    else cart.value.push({ productId: p.id, name: p.name, price: Number(p.price), quantity: 1 });
}
function dec(line: Line) {
    line.quantity--;
    if (line.quantity <= 0) cart.value = cart.value.filter((l) => l !== line);
}
const checkingOut = ref(false);
function checkout() {
    if (!cart.value.length) return;
    checkingOut.value = true;
    router.post(
        route('orders.store'),
        { items: cart.value.map((l) => ({ product_id: l.productId, quantity: l.quantity })), paid: true },
        {
            preserveScroll: true,
            onSuccess: () => (cart.value = []),
            onFinish: () => (checkingOut.value = false),
        },
    );
}

function togglePaid(order: OrderRow) {
    router.put(route('orders.update', order.id), { paid: !order.paid }, { preserveScroll: true });
}

const dayTime = (iso: string) => new Date(iso).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });

// --- Tills (points of sale) ---
const currentTill = computed(() => props.tills.find((t) => t.id === props.currentTillId) ?? null);

function selectTill(id: number) {
    router.post(route('tills.select'), { till_id: id }, { preserveScroll: true });
}

const tillsOpen = ref(false);
const tillForm = useForm({ name: '', area_id: null as number | null });

function submitTill() {
    tillForm.post(route('tills.store'), { preserveScroll: true, onSuccess: () => tillForm.reset() });
}

function deleteTill(till: TillRow) {
    if (confirm(`Eliminare la cassa "${till.name}"? Gli ordini già battuti restano.`)) {
        router.delete(route('tills.destroy', till.id), { preserveScroll: true });
    }
}

// A till's menu: empty selection means it sells the whole listino.
const editingMenu = ref<TillRow | null>(null);
const menuSelection = ref<number[]>([]);

function openMenu(till: TillRow) {
    editingMenu.value = till;
    menuSelection.value = [...till.productIds];
}

function toggleMenuProduct(id: number, on: boolean) {
    menuSelection.value = on ? [...menuSelection.value, id] : menuSelection.value.filter((p) => p !== id);
}

function saveMenu() {
    if (!editingMenu.value) return;
    router.put(
        route('tills.menu', editingMenu.value.id),
        { products: menuSelection.value },
        { preserveScroll: true, onSuccess: () => (editingMenu.value = null) },
    );
}

// --- Listino management (organizer) ---
const listinoOpen = ref(false);
const productForm = useForm({ name: '', price: '', area_id: null as number | null, sub_area_id: null as number | null, active: true });

function submitProduct() {
    productForm.post(route('products.store'), { preserveScroll: true, onSuccess: () => productForm.reset() });
}

const editingProduct = ref<ProductRow | null>(null);
const editProductForm = useForm({ name: '', price: '', area_id: null as number | null, sub_area_id: null as number | null, active: true });

function openEditProduct(p: ProductRow) {
    editingProduct.value = p;
    editProductForm.name = p.name;
    editProductForm.price = p.price;
    editProductForm.area_id = p.areaId;
    editProductForm.sub_area_id = p.subAreaId;
    editProductForm.active = true;
    editProductForm.clearErrors();
}
function submitEditProduct() {
    if (!editingProduct.value) return;
    editProductForm.put(route('products.update', editingProduct.value.id), {
        preserveScroll: true,
        onSuccess: () => (editingProduct.value = null),
    });
}
function deleteProduct(p: ProductRow) {
    if (confirm(`Eliminare "${p.name}" dal listino?`)) {
        router.delete(route('products.destroy', p.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Cassa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Cassa</h1>
                <p class="text-sm text-muted-foreground">
                    Batti gli ordini di <span v-if="event" class="font-medium">{{ event.name }}</span
                    ><span v-else>questo evento</span><span v-if="currentTill"> · sei alla {{ currentTill.name }}</span>.
                </p>
            </div>

            <p v-if="!event" class="text-sm text-muted-foreground">Nessun evento selezionato. Creane uno da <strong>Eventi</strong>.</p>

            <!-- Which till you're working at (only worth showing with more than one) -->
            <nav v-if="event && tills.length > 1" class="flex flex-wrap gap-1 border-b pb-2">
                <Button
                    v-for="till in tills"
                    :key="till.id"
                    :variant="till.id === currentTillId ? 'secondary' : 'ghost'"
                    size="sm"
                    @click="selectTill(till.id)"
                >
                    {{ till.name }}
                </Button>
            </nav>

            <p v-if="event && products.length === 0" class="text-sm text-muted-foreground">
                Il listino è vuoto.<span v-if="canManageListino"> Aggiungi i prodotti qui sotto.</span>
            </p>

            <template v-else-if="event">
                <!-- POS: products + cart -->
                <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">
                    <div class="grid gap-3">
                        <section v-for="[area, items] in productsByArea" :key="area" class="grid gap-2">
                            <h2 class="text-sm font-medium text-muted-foreground">{{ area }}</h2>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <button
                                    v-for="p in items"
                                    :key="p.id"
                                    type="button"
                                    class="flex flex-col gap-0.5 rounded-xl border bg-card p-3 text-left transition hover:border-foreground/30 hover:shadow-sm"
                                    @click="addToCart(p)"
                                >
                                    <span class="font-medium leading-tight">{{ p.name }}</span>
                                    <span class="text-sm text-muted-foreground">{{ euro(p.price) }}</span>
                                </button>
                            </div>
                        </section>
                    </div>

                    <!-- Cart -->
                    <div class="grid content-start gap-2 rounded-xl border p-3 lg:sticky lg:top-4">
                        <p class="font-medium">Ordine</p>
                        <p v-if="cart.length === 0" class="text-sm text-muted-foreground">Tocca i prodotti per aggiungerli.</p>
                        <div v-for="line in cart" :key="line.productId" class="flex items-center gap-2 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ line.name }}</p>
                                <p class="text-muted-foreground">{{ euro(line.price) }}</p>
                            </div>
                            <Button variant="outline" size="icon" class="h-7 w-7" aria-label="Meno" @click="dec(line)"><Minus class="h-3 w-3" /></Button>
                            <span class="w-5 text-center tabular-nums">{{ line.quantity }}</span>
                            <Button variant="outline" size="icon" class="h-7 w-7" aria-label="Più" @click="line.quantity++"><Plus class="h-3 w-3" /></Button>
                        </div>
                        <div class="mt-1 flex items-center justify-between border-t pt-2">
                            <span class="text-sm text-muted-foreground">Totale</span>
                            <span class="text-lg font-semibold">{{ euro(cartTotal) }}</span>
                        </div>
                        <Button :disabled="cart.length === 0 || checkingOut" @click="checkout">Incassa {{ euro(cartTotal) }}</Button>
                    </div>
                </div>
            </template>

            <!-- Recent orders -->
            <section v-if="event" class="grid gap-2">
                <h2 class="font-medium">Ordini recenti</h2>
                <p v-if="orders.length === 0" class="text-sm text-muted-foreground">Ancora nessun ordine.</p>
                <ul v-else class="divide-y rounded-xl border">
                    <li v-for="order in orders" :key="order.id" class="flex items-center gap-3 p-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                #{{ order.number }} · {{ euro(order.total) }}
                                <span class="text-sm font-normal text-muted-foreground">· {{ dayTime(order.createdAt) }}</span>
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{ order.items.map((i) => `${i.quantity}× ${i.name}`).join(', ') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="order.paid ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100'"
                            @click="togglePaid(order)"
                        >
                            {{ order.paid ? 'Pagato' : 'Da pagare' }}
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Points of sale: the responsabile of a till's area configures it -->
            <section v-if="event" class="grid gap-2">
                <Button variant="outline" size="sm" class="justify-self-start" @click="tillsOpen = !tillsOpen">
                    {{ tillsOpen ? 'Nascondi casse' : 'Gestisci casse' }}
                </Button>

                <div v-if="tillsOpen" class="grid gap-3 rounded-xl border p-3">
                    <p v-if="tills.length === 0" class="text-sm text-muted-foreground">
                        Nessuna cassa configurata: si vende tutto il listino da un unico punto. Creane una solo se hai più punti cassa con menù
                        diversi.
                    </p>
                    <ul v-else class="divide-y">
                        <li v-for="till in tills" :key="till.id" class="flex items-center gap-2 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ till.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ areas.find((a) => a.id === till.areaId)?.name ?? 'Nessuna area' }} ·
                                    {{ till.productIds.length ? `${till.productIds.length} prodotti` : 'tutto il listino' }}
                                </p>
                            </div>
                            <template v-if="till.canManage">
                                <Button variant="outline" size="sm" @click="openMenu(till)">Menù</Button>
                                <Button variant="ghost" size="icon" aria-label="Elimina cassa" @click="deleteTill(till)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </template>
                        </li>
                    </ul>

                    <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitTill">
                        <div class="grid gap-1">
                            <Label class="text-xs">Nuova cassa</Label>
                            <Input v-model="tillForm.name" required placeholder="Es. Cassa centrale" class="h-9" />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs">Area che la gestisce</Label>
                            <select v-model="tillForm.area_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                                <option :value="null">Nessuna (organizzatore)</option>
                                <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <Button type="submit" variant="outline" size="sm" :disabled="tillForm.processing"><Plus class="h-4 w-4" /> Aggiungi</Button>
                        <InputError :message="tillForm.errors.name" class="w-full" />
                    </form>
                </div>
            </section>

            <!-- Listino management (organizer) -->
            <section v-if="canManageListino && event" class="grid gap-2">
                <Button variant="outline" size="sm" class="justify-self-start" @click="listinoOpen = !listinoOpen">
                    {{ listinoOpen ? 'Nascondi listino' : 'Gestisci listino' }}
                </Button>

                <div v-if="listinoOpen" class="grid gap-3 rounded-xl border p-3">
                    <ul v-if="products.length" class="divide-y">
                        <li v-for="p in products" :key="p.id" class="flex items-center gap-2 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ p.name }} <span class="text-sm font-normal text-muted-foreground">{{ euro(p.price) }}</span></p>
                                <p v-if="p.area" class="text-xs text-muted-foreground">{{ p.area }}</p>
                            </div>
                            <Button variant="ghost" size="icon" aria-label="Modifica prodotto" @click="openEditProduct(p)"><Pencil class="h-4 w-4" /></Button>
                            <Button variant="ghost" size="icon" aria-label="Elimina prodotto" @click="deleteProduct(p)"><Trash2 class="h-4 w-4" /></Button>
                        </li>
                    </ul>
                    <form class="grid gap-2 sm:grid-cols-[1fr_6rem_1fr_auto] sm:items-end" @submit.prevent="submitProduct">
                        <div class="grid gap-1">
                            <Label class="text-xs">Nuovo prodotto</Label>
                            <Input v-model="productForm.name" required placeholder="Nome" class="h-9" />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs">Prezzo €</Label>
                            <Input v-model="productForm.price" type="number" step="0.01" min="0" required class="h-9" />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs">Area (opz.)</Label>
                            <select
                                v-model="productForm.area_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                @change="productForm.sub_area_id = null"
                            >
                                <option :value="null">—</option>
                                <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <Button type="submit" variant="outline" size="sm" :disabled="productForm.processing"><Plus class="h-4 w-4" /> Aggiungi</Button>
                        <InputError :message="productForm.errors.name || productForm.errors.price" class="sm:col-span-4" />
                    </form>
                </div>
            </section>
        </div>

        <!-- A till's menu: pick what it sells, or leave empty for everything -->
        <div v-if="editingMenu" class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4" @click.self="editingMenu = null">
            <div class="mt-[8vh] grid max-h-[80vh] w-full max-w-sm gap-3 overflow-y-auto rounded-xl border bg-background p-4 shadow-lg">
                <div>
                    <p class="font-medium">Menù di {{ editingMenu.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        Scegli cosa vende questa cassa. Se non selezioni nulla, vende tutto il listino.
                    </p>
                </div>
                <Label v-for="p in listino" :key="p.id" class="flex items-center gap-3 font-normal">
                    <Checkbox :checked="menuSelection.includes(p.id)" @update:checked="(on: boolean) => toggleMenuProduct(p.id, on)" />
                    <span class="flex-1">{{ p.name }}</span>
                    <span class="text-sm text-muted-foreground">{{ euro(p.price) }}</span>
                </Label>
                <div class="flex gap-2">
                    <Button @click="saveMenu">Salva menù</Button>
                    <Button variant="ghost" @click="editingMenu = null">Annulla</Button>
                </div>
            </div>
        </div>

        <!-- Edit product dialog would go here; kept inline via prompt for slice A simplicity -->
        <div v-if="editingProduct" class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4" @click.self="editingProduct = null">
            <div class="mt-[10vh] w-full max-w-sm rounded-xl border bg-background p-4 shadow-lg">
                <p class="mb-3 font-medium">Modifica prodotto</p>
                <form class="grid gap-3" @submit.prevent="submitEditProduct">
                    <div class="grid gap-1">
                        <Label>Nome</Label>
                        <Input v-model="editProductForm.name" required />
                        <InputError :message="editProductForm.errors.name" />
                    </div>
                    <div class="grid gap-1">
                        <Label>Prezzo €</Label>
                        <Input v-model="editProductForm.price" type="number" step="0.01" min="0" required />
                        <InputError :message="editProductForm.errors.price" />
                    </div>
                    <div class="grid gap-1">
                        <Label>Area (opz.)</Label>
                        <select
                            v-model="editProductForm.area_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            @change="editProductForm.sub_area_id = null"
                        >
                            <option :value="null">—</option>
                            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div v-if="subAreasFor(editProductForm.area_id).length" class="grid gap-1">
                        <Label>Sotto-reparto</Label>
                        <select v-model="editProductForm.sub_area_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                            <option :value="null">Tutta l'area</option>
                            <option v-for="sa in subAreasFor(editProductForm.area_id)" :key="sa.id" :value="sa.id">{{ sa.name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="editProductForm.processing">Salva</Button>
                        <Button type="button" variant="ghost" @click="editingProduct = null">Annulla</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
