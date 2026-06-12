<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Benvenuto" />
    <div class="flex min-h-screen flex-col items-center justify-center gap-8 bg-background p-6 text-foreground">
        <div class="flex flex-col items-center gap-4 text-center">
            <div class="flex size-16 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                <AppLogoIcon class="size-10 fill-current" />
            </div>
            <h1 class="text-3xl font-semibold">{{ $page.props.name }}</h1>
            <p class="max-w-md text-balance text-muted-foreground">
                Turni e volontari per sagre e feste, senza fogli di calcolo: disponibilità dal telefono, copertura sempre sott'occhio.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <Button v-if="$page.props.auth.user" as-child>
                <Link :href="route('dashboard')">Vai alla dashboard</Link>
            </Button>
            <template v-else>
                <Button as-child>
                    <Link :href="route('login')">Accedi</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('register')">Registra la tua associazione</Link>
                </Button>
            </template>
        </div>

        <p class="max-w-md text-center text-sm text-muted-foreground">
            Sei un volontario? Non serve registrarsi:
            <Link :href="route('volunteer.home')" class="underline">vai ai tuoi turni</Link> se sei già entrato, oppure usa il link personale che ti è
            stato inviato (<Link :href="route('volunteer.recover')" class="underline">l'hai perso?</Link>).
        </p>
    </div>
</template>
