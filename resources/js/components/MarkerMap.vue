<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type MarkerRecord = {
    id: number;
    name: string;
    latitude: number;
    longitude: number;
    description: string | null;
    added: string;
    edited: string | null;
};

type MarkerForm = {
    id: number | null;
    name: string;
    latitude: string;
    longitude: string;
    description: string;
};

const mapContainer = ref<HTMLElement | null>(null);
const markers = ref<MarkerRecord[]>([]);
const form = ref<MarkerForm>({
    id: null,
    name: '',
    latitude: '',
    longitude: '',
    description: '',
});
const status = ref('Loading markers…');
const loading = ref(false);
const saving = ref(false);
const savingError = ref('');
const deleting = ref<number | null>(null);

let mapInstance: any = null;
let markerLayer: any = null;
let Leaflet: any = null;

const submitLabel = computed(() => (form.value.id === null ? 'Add marker' : 'Save changes'));

const csrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
};

const resetForm = (coords: { latitude: string; longitude: string } | null = null) => {
    form.value = {
        id: null,
        name: '',
        latitude: coords?.latitude ?? '',
        longitude: coords?.longitude ?? '',
        description: '',
    };
};

const setFormFromMarker = (marker: MarkerRecord) => {
    form.value = {
        id: marker.id,
        name: marker.name,
        latitude: marker.latitude.toString(),
        longitude: marker.longitude.toString(),
        description: marker.description ?? '',
    };
};

const loadLeaflet = async (): Promise<any> => {
    if ((window as any).L) {
        return (window as any).L;
    }

    if (!document.querySelector('link[href*="leaflet"]')) {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
        document.head.appendChild(css);
    }

    if (!document.querySelector('script[src*="leaflet"]')) {
        await new Promise<void>((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load Leaflet script.'));
            document.head.appendChild(script);
        });
    }

    return (window as any).L;
};

const escapeHtml = (value: string) => {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const requestJson = async <T,>(input: string, init: RequestInit = {}): Promise<T> => {
    const token = csrfToken();
    const response = await fetch(input, {
        headers: {
            Accept: 'application/json',
            ...(!init.body ? {} : { 'Content-Type': 'application/json' }),
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        credentials: 'same-origin',
        ...init,
    });
    const body = await response.json();

    if (!response.ok) {
        throw new Error(body.error ?? 'Request failed.');
    }

    return body as T;
};

const loadMarkers = async () => {
    loading.value = true;
    status.value = 'Loading markers…';
    savingError.value = '';
    try {
        markers.value = await requestJson<MarkerRecord[]>('/dashboard/markers');
        status.value = `${markers.value.length} markers`;
        renderMarkers();
    } catch (error) {
        if (error instanceof Error) {
            savingError.value = error.message;
            status.value = 'Could not load markers';
        }
    } finally {
        loading.value = false;
    }
};

const renderMarkers = () => {
    if (!mapInstance || !Leaflet) {
        return;
    }

    if (markerLayer) {
        markerLayer.clearLayers();
    }

    markerLayer = Leaflet.layerGroup();
    for (const marker of markers.value) {
        const point = Leaflet.marker([marker.latitude, marker.longitude]).addTo(markerLayer);
        point.on('click', () => {
            setFormFromMarker(marker);
        });
        point.bindPopup(`<strong>${escapeHtml(marker.name)}</strong><br/>${escapeHtml(marker.description ?? '')}`);
    }

    markerLayer.addTo(mapInstance);
};

const initMap = async () => {
    Leaflet = await loadLeaflet();
    if (!mapContainer.value) {
        return;
    }

    mapInstance = Leaflet.map(mapContainer.value).setView([58.595, 25.0136], 7);
    Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(mapInstance);

    mapInstance.on('click', (event: any) => {
        const { lat, lng } = event.latlng;
        resetForm({
            latitude: lat.toFixed(6),
            longitude: lng.toFixed(6),
        });
    });

    renderMarkers();
};

const submitMarker = async (event: Event) => {
    event.preventDefault();
    saving.value = true;
    savingError.value = '';

    const payload = {
        name: form.value.name.trim(),
        latitude: Number(form.value.latitude),
        longitude: Number(form.value.longitude),
        description: form.value.description.trim(),
    };

    if (!payload.name || Number.isNaN(payload.latitude) || Number.isNaN(payload.longitude)) {
        savingError.value = 'Please enter a valid name, latitude, and longitude.';
        saving.value = false;
        return;
    }

    try {
        if (form.value.id === null) {
            await requestJson('/dashboard/markers', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        } else {
            await requestJson(`/dashboard/markers/${form.value.id}`, {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
        }

        resetForm();
        await loadMarkers();
    } catch (error) {
        if (error instanceof Error) {
            savingError.value = error.message;
        }
    } finally {
        saving.value = false;
    }
};

const deleteMarker = async (marker: MarkerRecord) => {
    if (!confirm(`Delete marker ${marker.name}?`)) {
        return;
    }

    deleting.value = marker.id;
    savingError.value = '';

    try {
        await requestJson(`/dashboard/markers/${marker.id}`, {
            method: 'DELETE',
        });

        if (form.value.id === marker.id) {
            resetForm();
        }

        await loadMarkers();
    } catch (error) {
        if (error instanceof Error) {
            savingError.value = error.message;
        }
    } finally {
        deleting.value = null;
    }
};

const centerOnMarker = (marker: MarkerRecord) => {
    if (!mapInstance) {
        return;
    }

    mapInstance.setView([marker.latitude, marker.longitude], 12, {
        animate: true,
    });
    setFormFromMarker(marker);
};

onMounted(async () => {
    await loadMarkers();
    await initMap();
});

onBeforeUnmount(() => {
    if (mapInstance) {
        mapInstance.remove();
    }
});
</script>

<template>
    <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-4">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Map</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ status }}</p>

        <p v-if="savingError" class="mt-3 rounded-md bg-rose-900/20 p-2 text-sm text-rose-200 dark:bg-rose-500/10 dark:text-rose-300">
            {{ savingError }}
        </p>

        <div ref="mapContainer" class="mt-4 h-[360px] w-full rounded-lg border border-slate-300/40 bg-black/5 dark:border-slate-700 dark:bg-white/5"></div>

        <form class="mt-4 space-y-2" @submit="submitMarker">
            <label class="text-sm font-medium" for="marker-name">Marker name</label>
            <input
                id="marker-name"
                v-model="form.name"
                class="w-full rounded-md border border-slate-300/40 bg-black/5 px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-white/5 dark:text-slate-100"
                placeholder="Example place"
                type="text"
            />

            <div class="grid grid-cols-2 gap-2">
                <input
                    v-model="form.latitude"
                    class="rounded-md border border-slate-300/40 bg-black/5 px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-white/5 dark:text-slate-100"
                    placeholder="Latitude"
                    step="0.000001"
                    type="number"
                />
                <input
                    v-model="form.longitude"
                    class="rounded-md border border-slate-300/40 bg-black/5 px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-white/5 dark:text-slate-100"
                    placeholder="Longitude"
                    step="0.000001"
                    type="number"
                />
            </div>

            <textarea
                v-model="form.description"
                class="min-h-20 w-full rounded-md border border-slate-300/40 bg-black/5 px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-white/5 dark:text-slate-100"
                placeholder="Description (optional)"
            ></textarea>

            <div class="flex gap-2">
                <button
                    :disabled="saving"
                    class="rounded-md border border-slate-300 bg-black/5 px-4 py-2 text-sm font-semibold text-slate-900 disabled:opacity-50 dark:border-slate-700 dark:bg-white/5 dark:text-slate-100"
                    type="submit"
                >
                    {{ submitLabel }}
                </button>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-700"
                    @click="resetForm()"
                >
                    Clear
                </button>
            </div>
        </form>

        <div class="mt-4 space-y-2">
            <p class="text-sm font-medium">Saved markers</p>
            <div v-if="loading" class="text-sm text-slate-500">Loading marker list...</div>
            <div v-else-if="markers.length === 0" class="text-sm text-slate-500">No markers yet.</div>
            <div v-else class="max-h-48 space-y-2 overflow-auto pr-1">
                <article
                    v-for="marker in markers"
                    :key="marker.id"
                    class="rounded-md border border-slate-300/40 bg-black/5 p-3 text-sm dark:border-slate-700 dark:bg-white/5"
                >
                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ marker.name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ marker.latitude }}, {{ marker.longitude }}
                    </p>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">{{ marker.description || 'No description' }}</p>

                    <div class="mt-2 flex justify-end gap-2">
                        <button
                            class="rounded-md border border-slate-500 px-2 py-1 text-xs dark:border-slate-700"
                            type="button"
                            @click="centerOnMarker(marker)"
                        >
                            Focus
                        </button>
                        <button
                            class="rounded-md border border-emerald-500 px-2 py-1 text-xs text-emerald-700 dark:border-emerald-600 dark:text-emerald-300"
                            type="button"
                            @click="setFormFromMarker(marker)"
                        >
                            Edit
                        </button>
                        <button
                            class="rounded-md border border-rose-300 px-2 py-1 text-xs text-rose-700 dark:border-rose-600 dark:text-rose-300"
                            :disabled="deleting === marker.id"
                            type="button"
                            @click="deleteMarker(marker)"
                        >
                            {{ deleting === marker.id ? 'Deleting…' : 'Delete' }}
                        </button>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
