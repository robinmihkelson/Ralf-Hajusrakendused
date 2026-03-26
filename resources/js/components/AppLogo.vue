<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import sidebarLogo from '../../images/logo.png';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-md">
        <AppLogoIcon :src="sidebarLogo" alt="Hajusrakendused logo" class="size-8 object-contain" />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span class="truncate font-semibold leading-none">Hajusrakendused</span>
    </div>
</template>
