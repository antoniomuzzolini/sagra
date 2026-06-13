<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Mail, MessageCircle, Phone } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Tapping a volunteer's name opens quick ways to reach them — call,
// WhatsApp, email — but only the contacts that are actually known.
// With no contact at all it stays plain text: nothing to tap.
const props = defineProps<{
    name: string;
    phone?: string | null;
    email?: string | null;
}>();

const open = ref(false);

const hasContact = computed(() => Boolean(props.phone || props.email));

// wa.me wants digits only; tel: tolerates the leading + but not spaces.
const whatsappUrl = computed(() => {
    const digits = props.phone?.replace(/[^0-9]/g, '');
    return digits ? `https://wa.me/${digits}` : null;
});
const telUrl = computed(() => (props.phone ? `tel:${props.phone.replace(/\s+/g, '')}` : null));
const mailUrl = computed(() => (props.email ? `mailto:${props.email}` : null));
</script>

<template>
    <button v-if="hasContact" type="button" class="text-left underline-offset-2 hover:underline" @click.stop="open = true">
        {{ name }}
    </button>
    <template v-else>{{ name }}</template>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xs">
            <DialogHeader>
                <DialogTitle>{{ name }}</DialogTitle>
            </DialogHeader>
            <div class="grid gap-2">
                <Button v-if="telUrl" as-child variant="outline" class="justify-start">
                    <a :href="telUrl"><Phone class="h-4 w-4" /> Chiama {{ phone }}</a>
                </Button>
                <Button v-if="whatsappUrl" as-child variant="outline" class="justify-start">
                    <a :href="whatsappUrl" target="_blank" rel="noopener"><MessageCircle class="h-4 w-4" /> Scrivi su WhatsApp</a>
                </Button>
                <Button v-if="mailUrl" as-child variant="outline" class="justify-start">
                    <a :href="mailUrl"><Mail class="h-4 w-4" /> Invia una mail</a>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
