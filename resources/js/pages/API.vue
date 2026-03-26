<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

type SubjectUser = {
    id: number;
    name: string;
};

type SubjectItem = {
    id: number;
    user_id: number;
    title: string;
    image: string;
    description: string;
    brand: string;
    production_year: number;
    horsepower: number;
    created_at: string;
    user: SubjectUser;
};

type SubjectMeta = {
    total: number;
    returned: number;
    limit: number;
    sort_by: string;
    sort_dir: string;
    scope: string;
    cache_ttl_seconds: number;
};

type SubjectResponse = {
    data: SubjectItem[];
    meta: SubjectMeta;
};

type DocsResponse = {
    name: string;
    theme: string;
    endpoints: Array<{
        method: string;
        path: string;
        description: string;
        example?: string;
    }>;
    cache: {
        enabled: boolean;
        strategy: string;
        ttl_seconds: number;
        invalidated_on_create: boolean;
    };
};

type Scope = 'all' | 'mine' | 'others';
type SortBy = 'created_at' | 'title' | 'brand' | 'production_year' | 'horsepower';
type SortDir = 'asc' | 'desc';

const props = defineProps<{
    defaultLimit: number;
    docsEndpoint: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'API',
        href: '/api',
    },
];

const page = usePage<SharedData>();
const currentUserId = computed(() => page.props.auth?.user?.id ?? 0);

const items = ref<SubjectItem[]>([]);
const meta = ref<SubjectMeta | null>(null);
const docs = ref<DocsResponse | null>(null);

const loading = ref(false);
const docsLoading = ref(false);
const saving = ref(false);
const error = ref('');

const filters = reactive({
    search: '',
    brand: '',
    scope: 'all' as Scope,
    sort_by: 'created_at' as SortBy,
    sort_dir: 'desc' as SortDir,
    year_from: '',
    year_to: '',
    limit: props.defaultLimit,
});

const form = reactive({
    title: '',
    image: '',
    description: '',
    brand: '',
    production_year: '',
    horsepower: '',
});

const csrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

const requestJson = async <T,>(input: string, init: RequestInit = {}): Promise<T> => {
    const token = csrfToken();
    const response = await fetch(input, {
        headers: {
            Accept: 'application/json',
            ...(init.body ? { 'Content-Type': 'application/json' } : {}),
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        credentials: 'same-origin',
        ...init,
    });
    const body = (await response.json()) as { error?: string } & T;

    if (!response.ok) {
        throw new Error(body.error ?? 'Request failed.');
    }

    return body;
};

const buildQueryString = () => {
    const params = new URLSearchParams();

    if (filters.search.trim() !== '') {
        params.set('search', filters.search.trim());
    }

    if (filters.brand.trim() !== '') {
        params.set('brand', filters.brand.trim());
    }

    const yearFrom = String(filters.year_from ?? '').trim();
    const yearTo = String(filters.year_to ?? '').trim();

    if (yearFrom !== '') {
        params.set('year_from', yearFrom);
    }

    if (yearTo !== '') {
        params.set('year_to', yearTo);
    }

    params.set('scope', filters.scope);
    params.set('sort_by', filters.sort_by);
    params.set('sort_dir', filters.sort_dir);
    params.set('limit', String(filters.limit));

    const query = params.toString();
    return query === '' ? '' : `?${query}`;
};

const loadItems = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await requestJson<SubjectResponse>(`/api/cars${buildQueryString()}`);
        items.value = response.data;
        meta.value = response.meta;
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        loading.value = false;
    }
};

const loadDocs = async () => {
    docsLoading.value = true;

    try {
        docs.value = await requestJson<DocsResponse>(props.docsEndpoint);
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        docsLoading.value = false;
    }
};

const resetFilters = async () => {
    filters.search = '';
    filters.brand = '';
    filters.scope = 'all';
    filters.sort_by = 'created_at';
    filters.sort_dir = 'desc';
    filters.year_from = '';
    filters.year_to = '';
    filters.limit = props.defaultLimit;
    await loadItems();
};

const resetForm = () => {
    form.title = '';
    form.image = '';
    form.description = '';
    form.brand = '';
    form.production_year = '';
    form.horsepower = '';
};

const submitForm = async (event: Event) => {
    event.preventDefault();
    error.value = '';
    saving.value = true;

    try {
        if (
            !form.title.trim() ||
            !form.image.trim() ||
            !form.description.trim() ||
            !form.brand.trim() ||
            !form.production_year.trim() ||
            !form.horsepower.trim()
        ) {
            throw new Error('All fields are required.');
        }

        await requestJson('/api/cars', {
            method: 'POST',
            body: JSON.stringify({
                title: form.title.trim(),
                image: form.image.trim(),
                description: form.description.trim(),
                brand: form.brand.trim(),
                production_year: Number(form.production_year),
                horsepower: Number(form.horsepower),
            }),
        });

        resetForm();
        await loadItems();
    } catch (err) {
        if (err instanceof Error) {
            error.value = err.message;
        }
    } finally {
        saving.value = false;
    }
};

const formatDate = (value: string) => {
    return new Date(value).toLocaleString();
};

const isOwnItem = (item: SubjectItem) => item.user_id === currentUserId.value;

onMounted(async () => {
    await Promise.all([loadItems(), loadDocs()]);
});
</script>

<template>
    <Head title="API" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-3 rounded-xl p-3">
            <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-3 dark:bg-white/5">
                <h2 class="text-base font-semibold">Cars JSON API</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Add your favorite cars and browse entries from yourself and other users.
                </p>

                <p v-if="docsLoading" class="mt-2 text-xs text-muted-foreground">Loading API docs...</p>

                <div v-if="docs" class="mt-2 grid gap-2 text-xs">
                    <p><strong>Name:</strong> {{ docs.name }} | <strong>Theme:</strong> {{ docs.theme }}</p>
                    <p><strong>List endpoint:</strong> <code>/api/cars</code></p>
                    <p><strong>Create endpoint:</strong> <code>/api/cars</code> (POST)</p>
                    <p><strong>Docs endpoint:</strong> <code>{{ docsEndpoint }}</code></p>
                    <p><strong>Cache:</strong> {{ docs.cache.strategy }} (TTL {{ docs.cache.ttl_seconds }}s)</p>
                </div>
            </section>

            <p v-if="error" class="rounded-md border border-rose-300/50 bg-rose-50 p-2 text-xs text-rose-700">
                {{ error }}
            </p>

            <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-3 dark:bg-white/5">
                <h2 class="text-base font-semibold">Add car record</h2>

                <form class="mt-2 grid gap-2" @submit="submitForm">
                    <div class="grid gap-2 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium" for="title">Title</label>
                            <input id="title" v-model="form.title" class="mt-1 w-full rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" type="text" />
                        </div>
                        <div>
                            <label class="text-xs font-medium" for="image">Image URL</label>
                            <input id="image" v-model="form.image" class="mt-1 w-full rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" type="url" />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-medium" for="description">Description</label>
                        <textarea id="description" v-model="form.description" class="mt-1 min-h-20 w-full rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-3">
                        <div>
                            <label class="text-xs font-medium" for="brand">Brand</label>
                            <input id="brand" v-model="form.brand" class="mt-1 w-full rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" type="text" />
                        </div>
                        <div>
                            <label class="text-xs font-medium" for="production_year">Production year</label>
                            <input id="production_year" v-model="form.production_year" class="mt-1 w-full rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" type="number" />
                        </div>
                        <div>
                            <label class="text-xs font-medium" for="horsepower">Horsepower</label>
                            <input id="horsepower" v-model="form.horsepower" class="mt-1 w-full rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" type="number" />
                        </div>
                    </div>

                    <div class="mt-1 flex gap-2">
                        <button :disabled="saving" class="rounded-md bg-sky-500 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50" type="submit">
                            {{ saving ? 'Saving...' : 'Save entry' }}
                        </button>
                        <button class="rounded-md border border-slate-300/40 bg-black/5 px-3 py-1.5 text-sm" type="button" @click="resetForm">
                            Clear
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-3 dark:bg-white/5">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold">Browse and filter</h2>
                    <button class="rounded-md border border-slate-300/40 bg-black/5 px-2.5 py-1 text-xs" type="button" @click="resetFilters">
                        Reset filters
                    </button>
                </div>

                <div class="mt-2 grid gap-2 md:grid-cols-3 xl:grid-cols-4">
                    <input
                        v-model="filters.search"
                        class="rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm"
                        placeholder="Search"
                        type="text"
                    />
                    <select v-model="filters.scope" class="rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm">
                        <option value="all">All users</option>
                        <option value="mine">Only mine</option>
                        <option value="others">Only others</option>
                    </select>
                    <select v-model="filters.sort_by" class="rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm">
                        <option value="created_at">Newest</option>
                        <option value="title">Title</option>
                        <option value="brand">Brand</option>
                        <option value="production_year">Year</option>
                        <option value="horsepower">Horsepower</option>
                    </select>
                    <select v-model="filters.sort_dir" class="rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm">
                        <option value="desc">Descending</option>
                        <option value="asc">Ascending</option>
                    </select>
                    <input v-model="filters.year_from" class="rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" placeholder="Year from" type="number" />
                    <input v-model="filters.year_to" class="rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" placeholder="Year to" type="number" />
                    <input v-model.number="filters.limit" class="rounded-md border border-slate-300/40 bg-black/5 px-2 py-1.5 text-sm" max="100" min="1" placeholder="Limit" type="number" />
                </div>

                <div class="mt-2">
                    <button :disabled="loading" class="rounded-md bg-slate-700 px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50" type="button" @click="loadItems">
                        {{ loading ? 'Loading...' : 'Apply filters' }}
                    </button>
                </div>

                <div v-if="meta" class="mt-2 text-xs text-muted-foreground">
                    Total {{ meta.total }} | Returned {{ meta.returned }} | Limit {{ meta.limit }} | Sort {{ meta.sort_by }} {{ meta.sort_dir }} | Scope {{ meta.scope }}
                </div>
            </section>

            <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-3 dark:bg-white/5">
                <h2 class="text-base font-semibold">Cars</h2>

                <p v-if="loading" class="mt-2 text-sm text-muted-foreground">Loading...</p>
                <p v-else-if="items.length === 0" class="mt-2 text-sm text-muted-foreground">No entries found.</p>

                <div v-else class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="item in items" :key="item.id" class="rounded-md border border-slate-300/40 bg-black/5 p-2.5">
                        <img :src="item.image" :alt="item.title" class="aspect-[6/3] w-full rounded-md object-cover object-center" loading="lazy" />
                        <div class="mt-2">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold">{{ item.title }}</h3>
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                        isOwnItem(item) ? 'bg-emerald-200/70 text-emerald-900' : 'bg-slate-200/70 text-slate-800',
                                    ]"
                                >
                                    {{ isOwnItem(item) ? 'Mine' : 'Others' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ item.brand }} · {{ item.production_year }} · {{ item.horsepower }} hp
                            </p>
                            <p class="mt-1 text-xs">{{ item.description }}</p>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Added by {{ item.user?.name ?? 'Unknown' }} · {{ formatDate(item.created_at) }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
