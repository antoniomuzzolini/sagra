<script setup lang="ts">
import Avatar from '@/components/Avatar.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Plus, Search, UserPlus } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

// A searchable person picker (D20 usability): type to filter, pick someone,
// or add a brand-new volunteer on the spot with the typed name — the parent
// then associates them (assign to a shift, put in charge of an area).
const props = withDefaults(
    defineProps<{
        people: { id: number; name: string }[];
        exclude?: number[];
        label?: string;
        title?: string;
    }>(),
    { exclude: () => [], label: 'Aggiungi', title: 'Scegli una persona' },
);

const emit = defineEmits<{ pick: [id: number]; create: [name: string] }>();

const open = ref(false);
const query = ref('');
const searchInput = ref<InstanceType<typeof Input> | null>(null);

const available = computed(() => props.people.filter((person) => !props.exclude.includes(person.id)));

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return q ? available.value.filter((person) => person.name.toLowerCase().includes(q)) : available.value;
});

const trimmed = computed(() => query.value.trim());
const exactMatch = computed(() => available.value.some((person) => person.name.trim().toLowerCase() === trimmed.value.toLowerCase()));
const canCreate = computed(() => trimmed.value.length > 0 && !exactMatch.value);

function openPicker() {
    query.value = '';
    open.value = true;
    nextTick(() => searchInput.value?.$el?.focus());
}

function pick(id: number) {
    emit('pick', id);
    open.value = false;
}

function createNew() {
    if (canCreate.value) {
        emit('create', trimmed.value);
        open.value = false;
    }
}

function onEnter() {
    if (filtered.value.length === 1) {
        pick(filtered.value[0].id);
    } else if (canCreate.value) {
        createNew();
    }
}
</script>

<template>
    <Button variant="outline" size="sm" @click="openPicker"><Plus class="h-4 w-4" /> {{ label }}</Button>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
            </DialogHeader>

            <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input ref="searchInput" v-model="query" placeholder="Cerca o scrivi un nome nuovo…" class="pl-8" @keydown.enter.prevent="onEnter" />
            </div>

            <div class="max-h-64 overflow-y-auto">
                <button
                    v-for="person in filtered"
                    :key="person.id"
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm hover:bg-muted"
                    @click="pick(person.id)"
                >
                    <Avatar :name="person.name" :size="24" />
                    <span class="truncate">{{ person.name }}</span>
                </button>
                <p v-if="filtered.length === 0 && !trimmed" class="px-2 py-4 text-center text-sm text-muted-foreground">Nessuna persona disponibile.</p>
            </div>

            <button
                v-if="canCreate"
                type="button"
                class="flex w-full items-center gap-2 rounded-md border border-dashed px-2 py-2 text-left text-sm hover:bg-muted"
                @click="createNew"
            >
                <UserPlus class="h-4 w-4 shrink-0" />
                <span class="truncate">Aggiungi «{{ trimmed }}» come nuovo volontario</span>
            </button>
        </DialogContent>
    </Dialog>
</template>
