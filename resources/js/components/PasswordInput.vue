<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, type HTMLAttributes } from 'vue';

// A password field with a reveal toggle. Self-contained (native input styled
// like ui/Input) so a template ref exposes focus() — some forms focus it on
// error. id/required/autocomplete/etc. pass through via $attrs.
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    modelValue?: string;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const model = useVModel(props, 'modelValue', emits, { passive: true });
const revealed = ref(false);
const input = ref<HTMLInputElement | null>(null);

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div class="relative">
        <input
            ref="input"
            v-model="model"
            :type="revealed ? 'text' : 'password'"
            v-bind="$attrs"
            :class="
                cn(
                    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                    props.class,
                )
            "
        />
        <button
            type="button"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
            :aria-label="revealed ? 'Nascondi password' : 'Mostra password'"
            @click="revealed = !revealed"
        >
            <EyeOff v-if="revealed" class="h-4 w-4" />
            <Eye v-else class="h-4 w-4" />
        </button>
    </div>
</template>
