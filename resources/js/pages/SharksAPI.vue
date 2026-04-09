<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type SharkItem = Record<string, unknown>;

type SharksResponse = {
    source: string;
    cached: boolean;
    data: SharkItem[];
    meta: {
        total: number;
        returned: number;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Robbys Sharks API',
        href: '/sharks',
    },
];

const sharks = ref<SharkItem[]>([]);
const loading = ref(false);
const error = ref('');
const source = ref('');
const search = ref('');

const requestJson = async <T,>(input: string): Promise<T> => {
    const response = await fetch(input, {
        headers: {
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });
    const body = (await response.json()) as { error?: string } & T;

    if (!response.ok) {
        throw new Error(body.error ?? 'Request failed.');
    }

    return body;
};

const loadSharks = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await requestJson<SharksResponse>('/api/sharks');
        sharks.value = response.data;
        source.value = response.source;
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        loading.value = false;
    }
};

const pickString = (item: SharkItem, keys: string[], fallback = ''): string => {
    for (const key of keys) {
        const value = item[key];
        if (typeof value === 'string' && value.trim() !== '') {
            return value.trim();
        }
        if (typeof value === 'number') {
            return String(value);
        }
    }

    return fallback;
};

const sharkName = (item: SharkItem): string => pickString(item, ['name', 'title', 'common_name', 'species'], 'Unknown shark');
const sharkImage = (item: SharkItem): string => pickString(item, ['image', 'image_url', 'photo', 'thumbnail', 'poster']);
const sharkDescription = (item: SharkItem): string => pickString(item, ['description', 'summary', 'details', 'habitat'], 'No description available.');

const sharkMeta = (item: SharkItem): string => {
    const species = pickString(item, ['species', 'scientific_name']);
    const region = pickString(item, ['region', 'ocean', 'location']);
    const length = pickString(item, ['length', 'max_length']);

    const parts = [species, region, length].filter((part) => part !== '');
    return parts.join(' · ');
};

const filteredSharks = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (query === '') {
        return sharks.value;
    }

    return sharks.value.filter((item) => {
        const searchable = [
            sharkName(item),
            sharkDescription(item),
            sharkMeta(item),
        ]
            .join(' ')
            .toLowerCase();

        return searchable.includes(query);
    });
});

onMounted(async () => {
    await loadSharks();
});
</script>

<template>
    <Head title="Sharks API" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-3 rounded-xl p-3">

            <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-3 dark:bg-white/5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold">Sharks</h2>
                    <input
                        v-model="search"
                        class="w-64 max-w-full rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm"
                        placeholder="Search"
                        type="text"
                    />
                </div>

                <p v-if="loading" class="mt-2 text-sm text-muted-foreground">Loading...</p>
                <p v-else-if="filteredSharks.length === 0" class="mt-2 text-sm text-muted-foreground">No sharks found.</p>

                <div v-else class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="(item, index) in filteredSharks" :key="String(item.id ?? item.slug ?? `${sharkName(item)}-${index}`)" class="rounded-md border border-slate-300/40 bg-black/5 p-2.5">
                        <img v-if="sharkImage(item) !== ''" :src="sharkImage(item)" :alt="sharkName(item)" class="aspect-[6/3] w-full rounded-md object-cover object-center" loading="lazy" />
                        <div class="mt-2">
                            <h3 class="text-sm font-semibold">{{ sharkName(item) }}</h3>
                            <p v-if="sharkMeta(item) !== ''" class="mt-1 text-xs text-muted-foreground">{{ sharkMeta(item) }}</p>
                            <p class="mt-1 text-xs">{{ sharkDescription(item) }}</p>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

