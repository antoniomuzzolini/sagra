<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { InertiaForm } from '@inertiajs/vue3';

// The shift form fields, shared by the create and edit forms so both read the
// same on mobile: a full-width day, start and end times side by side (never
// split across a wrap), headcount and notes.
interface ShiftData {
    date: string;
    start_time: string;
    end_time: string;
    needed_people: number;
    notes: string;
}

const props = defineProps<{ form: InertiaForm<ShiftData> }>();

// The form is a shared reactive store passed by reference: v-model updates the
// parent's form directly. Alias it locally so that's not flagged as mutating a
// value prop.
const form = props.form;
</script>

<template>
    <div class="grid gap-3">
        <div class="grid gap-1.5">
            <Label class="text-xs">Giorno</Label>
            <Input type="date" v-model="form.date" required />
            <InputError :message="form.errors.date" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="grid gap-1.5">
                <Label class="text-xs">Inizio</Label>
                <Input type="time" v-model="form.start_time" required />
                <InputError :message="form.errors.start_time" />
            </div>
            <div class="grid gap-1.5">
                <Label class="text-xs">Fine</Label>
                <Input type="time" v-model="form.end_time" required />
                <InputError :message="form.errors.end_time" />
            </div>
        </div>

        <div class="grid gap-1.5">
            <Label class="text-xs">Persone necessarie</Label>
            <Input type="number" v-model.number="form.needed_people" min="1" required class="w-28" />
            <InputError :message="form.errors.needed_people" />
        </div>

        <div class="grid gap-1.5">
            <Label class="text-xs">Note <span class="font-normal text-muted-foreground">(facoltative)</span></Label>
            <Input v-model="form.notes" placeholder="Es. portare i guanti" />
        </div>
    </div>
</template>
