<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, LayoutGrid, ListChecks, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

// One shell, role-aware nav (D19): the organizer runs the whole event, the
// area manager gets the same views scoped to the areas they run.
const organizerNav: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    { title: 'Volontari', href: '/people', icon: Users },
    { title: 'Eventi', href: '/events', icon: CalendarDays },
];

const managerNav: NavItem[] = [
    { title: 'Panoramica', href: '/manage/overview', icon: LayoutGrid },
    { title: 'Calendario', href: '/manage/calendar', icon: CalendarDays },
    { title: 'Persone', href: '/manage/people', icon: Users },
    { title: 'I miei turni', href: '/me', icon: ListChecks },
];

const mainNavItems = computed<NavItem[]>(() => (page.props.auth.role === 'manager' ? managerNav : organizerNav));

const homeHref = computed(() => (page.props.auth.role === 'manager' ? '/manage/overview' : '/dashboard'));
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
