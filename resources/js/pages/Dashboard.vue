<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import MarkerMap from '../components/MarkerMap.vue';
import WeatherWidget from '../components/WeatherWidget.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min items-start gap-4 md:grid-cols-2">
                <div class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <MarkerMap />
                </div>
                <div class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-black/5 p-2 dark:border-sidebar-border dark:bg-white/5">
                    <WeatherWidget />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
