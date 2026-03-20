<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

type WeatherPayload = {
    search: {
        query: string;
    };
    cached: boolean;
    data: {
        city: string;
        country: string;
        country_code: string;
        time: string;
        coordinates: {
            lat: number;
            lon: number;
        };
        current: {
            temperature: number;
            wind_speed: number;
            wind_direction: number;
            condition: string;
            icon: string;
            unit_temp: string;
            unit_wind: string;
        };
        forecast: {
            min_temp: number | null;
            max_temp: number | null;
            today_humidity: number | null;
        };
    };
};

type WeatherError = {
    error: string;
};

const query = ref('Tallinn, EE');
const loading = ref(false);
const error = ref('');
const weather = ref<WeatherPayload | null>(null);

const displayCode = computed(() => {
    return weather.value ? `${weather.value.data.current.icon} ${weather.value.data.current.condition}` : '';
});

const directionLabel = computed(() => {
    if (!weather.value) {
        return '';
    }

    const wind = weather.value.data.current.wind_direction;
    if (wind < 45) {
        return 'N';
    }
    if (wind < 135) {
        return 'E';
    }
    if (wind < 225) {
        return 'S';
    }
    if (wind < 315) {
        return 'W';
    }

    return 'N';
});

const tempRange = computed(() => {
    const data = weather.value?.data.forecast;
    if (!data) {
        return '';
    }

    return `${data.min_temp ?? '-'} °C / ${data.max_temp ?? '-'} °C`;
});

const loadWeather = async () => {
    loading.value = true;
    error.value = '';
    weather.value = null;

    try {
        const response = await fetch(`/weather?search=${encodeURIComponent(query.value)}`);
        const result = (await response.json()) as WeatherPayload | WeatherError;

        if (!response.ok) {
            error.value = (result as WeatherError).error || 'Failed to load weather data';
            return;
        }

        weather.value = result as WeatherPayload;
    } catch {
        error.value = 'Network request failed';
    } finally {
        loading.value = false;
    }
};

const onSubmit = (event: Event) => {
    event.preventDefault();
    void loadWeather();
};

onMounted(() => {
    void loadWeather();
});
</script>

<template>
    <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-4 text-slate-900 dark:text-slate-100">
        <h2 class="text-lg font-semibold">Weather</h2>

        <form class="mt-4 flex flex-col gap-2 sm:flex-row" @submit="onSubmit">
            <input
                v-model="query"
                class="min-w-0 flex-1 rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-sky-400"
                placeholder="Tallinn, EE"
                type="text"
            />
            <button
                :disabled="loading"
                class="rounded-md bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-900 disabled:bg-sky-700 disabled:text-slate-300"
                type="submit"
            >
                Search
            </button>
        </form>

        <p v-if="loading" class="mt-3 text-sm text-slate-300">Loading…</p>
        <p v-if="error" class="mt-3 rounded-md bg-rose-900/30 p-2 text-sm text-rose-200">{{ error }}</p>

        <div v-if="weather" class="mt-4 space-y-3">
            <div class="rounded-lg border border-slate-300/40 bg-black/5 p-3 dark:border-slate-700 dark:bg-white/5">
                <p class="text-xl mt-4 font-bold">{{ weather.data.city }} ({{ weather.data.country_code }})</p>
                <p class="mt-2 font-bold text-7xl">{{ weather.data.current.temperature }}{{ weather.data.current.unit_temp }}</p>
                <p class="text-xl">{{ displayCode }}</p>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <p class="rounded-md border border-slate-300/40 bg-black/5 p-2 text-sm dark:border-slate-700 dark:bg-white/5">Wind {{ weather.data.current.wind_speed }} {{ weather.data.current.unit_wind }} ({{ directionLabel }})</p>
                <p class="rounded-md border border-slate-300/40 bg-black/5 p-2 text-sm dark:border-slate-700 dark:bg-white/5">Min / Max {{ tempRange }}</p>
            </div>
        </div>
    </section>
</template>
