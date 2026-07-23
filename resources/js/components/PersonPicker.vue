<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, BookUser, Plus, Search, UserPlus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

// A searchable person picker (D20 usability): type to filter, the first match
// is highlighted and taken on Enter; or add a brand-new volunteer through a
// small form (optionally imported from the phone's address book). The parent
// then associates the person (assign to a shift, put in charge of an area).
const props = withDefaults(
    defineProps<{
        people: { id: number; name: string }[];
        exclude?: number[];
        label?: string;
        title?: string;
    }>(),
    { exclude: () => [], label: 'Aggiungi', title: 'Scegli una persona' },
);

const emit = defineEmits<{ pick: [id: number]; create: [payload: { name: string; phone: string | null }] }>();

const open = ref(false);
const mode = ref<'search' | 'create'>('search');
const query = ref('');
const activeIndex = ref(0);
const searchInput = ref<InstanceType<typeof Input> | null>(null);
const nameInput = ref<InstanceType<typeof Input> | null>(null);
const newForm = ref({ name: '', phone: '' });

const available = computed(() => props.people.filter((person) => !props.exclude.includes(person.id)));

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return q ? available.value.filter((person) => person.name.toLowerCase().includes(q)) : available.value;
});

// Reset the highlight to the top whenever the list changes.
watch(filtered, () => (activeIndex.value = 0));

// Address book import, where the browser supports it (Android Chrome).
const contactsSupported = typeof navigator !== 'undefined' && 'contacts' in navigator && 'ContactsManager' in window;

function openPicker() {
    query.value = '';
    mode.value = 'search';
    open.value = true;
    nextTick(() => searchInput.value?.$el?.focus());
}

function pick(id: number) {
    emit('pick', id);
    open.value = false;
}

function moveActive(delta: number) {
    if (!filtered.value.length) return;
    activeIndex.value = (activeIndex.value + delta + filtered.value.length) % filtered.value.length;
}

// Enter: take the highlighted result, or — with nothing matching — jump to
// the insertion form instead of blindly creating.
function onSearchEnter() {
    if (filtered.value.length) {
        pick(filtered.value[activeIndex.value]?.id ?? filtered.value[0].id);
    } else {
        startCreate();
    }
}

function startCreate() {
    newForm.value = { name: query.value.trim(), phone: '' };
    mode.value = 'create';
    nextTick(() => nameInput.value?.$el?.focus());
}

function submitCreate() {
    const name = newForm.value.name.trim();
    if (!name) return;
    emit('create', { name, phone: newForm.value.phone.trim() || null });
    open.value = false;
}

async function importFromContacts() {
    try {
         
        const selected = await (navigator as any).contacts.select(['name', 'tel'], { multiple: false });
        if (selected.length) {
            newForm.value.name = selected[0].name?.[0] ?? newForm.value.name;
            newForm.value.phone = selected[0].tel?.[0] ?? newForm.value.phone;
        }
    } catch {
        // Cancelled or unavailable — ignore.
    }
}
</script>

<template>
    <Button variant="outline" size="sm" @click="openPicker"><Plus class="h-4 w-4" /> {{ label }}</Button>

    <Dialog v-model:open="open">
        <!-- Top-anchored on mobile so the keyboard doesn't hide the list. -->
        <DialogContent class="top-[5vh] max-h-[90vh] translate-y-0 overflow-y-auto sm:top-1/2 sm:-translate-y-1/2">
            <!-- Search mode -->
            <template v-if="mode === 'search'">
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                </DialogHeader>

                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        ref="searchInput"
                        v-model="query"
                        placeholder="Cerca un nome…"
                        class="pl-8"
                        @keydown.enter.prevent="onSearchEnter"
                        @keydown.down.prevent="moveActive(1)"
                        @keydown.up.prevent="moveActive(-1)"
                    />
                </div>

                <div class="max-h-64 overflow-y-auto">
                    <button
                        v-for="(person, i) in filtered"
                        :key="person.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm"
                        :class="i === activeIndex ? 'bg-accent' : 'hover:bg-muted'"
                        @click="pick(person.id)"
                        @mousemove="activeIndex = i"
                    >
                        <Avatar :name="person.name" :size="24" />
                        <span class="truncate">{{ person.name }}</span>
                    </button>
                    <p v-if="filtered.length === 0" class="px-2 py-3 text-center text-sm text-muted-foreground">Nessuna corrispondenza.</p>
                </div>

                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md border border-dashed px-2 py-2 text-left text-sm hover:bg-muted"
                    @click="startCreate"
                >
                    <UserPlus class="h-4 w-4 shrink-0" />
                    <span class="truncate">Aggiungi nuovo volontario<template v-if="query.trim()">: «{{ query.trim() }}»</template></span>
                </button>
            </template>

            <!-- Create mode -->
            <template v-else>
                <DialogHeader>
                    <DialogTitle>Nuovo volontario</DialogTitle>
                    <DialogDescription>Il nome basta; il telefono è utile per i promemoria (facoltativo).</DialogDescription>
                </DialogHeader>

                <form class="grid gap-3" @submit.prevent="submitCreate">
                    <Button v-if="contactsSupported" type="button" variant="outline" class="justify-start" @click="importFromContacts">
                        <BookUser class="h-4 w-4" /> Importa dalla rubrica
                    </Button>
                    <div class="grid gap-1">
                        <Label for="pp-name">Nome</Label>
                        <Input id="pp-name" ref="nameInput" v-model="newForm.name" required placeholder="Nome e cognome" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="pp-phone">Telefono</Label>
                        <Input id="pp-phone" v-model="newForm.phone" type="tel" placeholder="+39 333 1234567" />
                    </div>
                    <div class="flex justify-between gap-2">
                        <Button type="button" variant="ghost" @click="mode = 'search'"><ArrowLeft class="h-4 w-4" /> Indietro</Button>
                        <Button type="submit" :disabled="!newForm.name.trim()">Aggiungi e associa</Button>
                    </div>
                </form>
            </template>
        </DialogContent>
    </Dialog>
</template>
