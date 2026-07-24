<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Boxes, CalendarCog, CalendarDays, ChefHat, ClipboardList, LayoutGrid, ListChecks, Package, Receipt, Settings2, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

// One shell, one menu (D19/D20): organizer and area manager see the same
// entries in the same order — same views, different scope. The menu is in two
// groups: the current event (Panoramica, Calendario, Gestione turni,
// Prenotazione turni) and, below a separator, the cross-event pages
// (Volontari, and Eventi for the organizer). Turni are split by
// responsibility — "Gestione turni" (create/assign) vs "Prenotazione turni"
// (declare availability, universal).
const isManager = computed(() => page.props.auth.role === 'manager');

const eventNav = computed<NavItem[]>(() => [
    { title: 'Panoramica', href: isManager.value ? '/manage/overview' : '/dashboard', icon: LayoutGrid },
    { title: 'Calendario', href: isManager.value ? '/manage/calendar' : '/calendar', icon: CalendarDays },
    // Defining the event's areas is the organizer's job; managers just run theirs.
    ...(isManager.value ? [] : [{ title: 'Aree', href: '/manage/areas', icon: Boxes }]),
    { title: 'Gestione turni', href: '/manage/shifts', icon: ClipboardList },
    { title: 'Prenotazione turni', href: '/me', icon: ListChecks },
    { title: 'Forniture', href: '/forniture', icon: Package },
    { title: 'Cassa', href: '/cassa', icon: Receipt },
    { title: 'Comande', href: '/comande', icon: ChefHat },
]);

const crossNav = computed<NavItem[]>(() =>
    isManager.value
        ? [{ title: 'Volontari', href: '/manage/people', icon: Users }]
        : [
              { title: 'Volontari', href: '/people', icon: Users },
              { title: 'Eventi', href: '/events', icon: CalendarCog },
              { title: 'Impostazioni organizzazione', href: '/organization', icon: Settings2 },
          ],
);

const homeHref = computed(() => (isManager.value ? '/manage/overview' : '/dashboard'));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="eventNav" />
            <SidebarSeparator />
            <NavMain :items="crossNav" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
