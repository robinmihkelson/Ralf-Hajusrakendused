<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'E-Shop',
        href: '/eshop',
    },
];
</script>

<template>
    <Head title="E-Shop" />

    <AppLayout :breadcrumbs="breadcrumbs">

    </AppLayout>
</template>
