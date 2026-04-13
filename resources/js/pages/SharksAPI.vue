<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type SharkItem = Record<string, unknown>;
type SharkValue = string | number | boolean | null | SharkValue[] | { [key: string]: SharkValue };

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

async function requestJson<T>(input: string): Promise<T> {
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
}

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

const filteredSharks = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (query === '') {
        return sharks.value;
    }

    return sharks.value.filter((item) => {
        const searchable = JSON.stringify(item).toLowerCase();

        return searchable.includes(query);
    });
});

const formatLabel = (key: string): string => {
    return key
        .replace(/[_-]+/g, ' ')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
};

const isImageUrl = (value: unknown): boolean => {
    return typeof value === 'string' && /^https?:\/\/.+\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i.test(value.trim());
};

const toDisplayValue = (value: unknown): string => {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    if (typeof value === 'string') {
        return value.trim() === '' ? 'N/A' : value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return String(value);
    }
};

const visibleEntries = (item: SharkItem): Array<[string, unknown]> => {
    return Object.entries(item).filter(([_, value]) => value !== null && value !== '' && !isImageUrl(value));
};

const hasExpandedData = (item: SharkItem): boolean => {
    return visibleEntries(item).length > 0;
};

const rawJson = (item: SharkItem): string => {
    return JSON.stringify(item as SharkValue, null, 2);
};

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
                            <p class="mt-1 text-xs">{{ sharkDescription(item) }}</p>

                            <div v-if="hasExpandedData(item)" class="mt-3 grid gap-2">
                                <div
                                    v-for="[key, value] in visibleEntries(item)"
                                    :key="`${String(item.id ?? item.slug ?? index)}-${key}`"
                                    class="rounded-md border border-slate-300/30 bg-white/40 p-2 dark:bg-black/10"
                                >
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                        {{ formatLabel(key) }}
                                    </p>
                                    <pre class="mt-1 overflow-x-auto whitespace-pre-wrap break-words font-sans text-xs text-foreground">{{ toDisplayValue(value) }}</pre>
                                </div>
                            </div>

                            <details class="mt-3 rounded-md border border-slate-300/30 bg-white/30 p-2 text-xs dark:bg-black/10">
                                <summary class="cursor-pointer font-medium">Raw API JSON</summary>
                                <pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-words">{{ rawJson(item) }}</pre>
                            </details>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
