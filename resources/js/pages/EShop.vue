<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type Product = {
    id: string;
    name: string;
    image_url: string;
    price_cents: number;
};

type CartItem = Product & {
    quantity: number;
};

const props = defineProps<{
    products: Product[];
    currency: string;
}>();

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

const cartStorageKey = 'eshop-cart-v1';
const minQuantity = 1;
const maxQuantity = 99;

const selectedQuantities = ref<Record<string, number>>({});
const cart = ref<CartItem[]>([]);

const productsById = computed(() => {
    const map = new Map<string, Product>();
    props.products.forEach((product) => {
        map.set(product.id, product);
    });
    return map;
});

const cartCount = computed(() => {
    return cart.value.reduce((total, item) => total + item.quantity, 0);
});

const subtotalCents = computed(() => {
    return cart.value.reduce((total, item) => total + item.price_cents * item.quantity, 0);
});

const formattedSubtotal = computed(() => {
    return formatMoney(subtotalCents.value);
});

const normalizeQuantity = (quantity: number): number => {
    if (!Number.isFinite(quantity)) {
        return minQuantity;
    }

    return Math.min(maxQuantity, Math.max(minQuantity, Math.trunc(quantity)));
};

const formatMoney = (cents: number): string => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: props.currency,
    }).format(cents / 100);
};

const persistCart = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const payload = cart.value.map((item) => ({
        product_id: item.id,
        quantity: item.quantity,
    }));

    window.localStorage.setItem(cartStorageKey, JSON.stringify(payload));
};

const loadCart = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const raw = window.localStorage.getItem(cartStorageKey);

    if (!raw) {
        cart.value = [];
        return;
    }

    try {
        const parsed = JSON.parse(raw) as Array<{ product_id?: string; quantity?: number }>;

        if (!Array.isArray(parsed)) {
            cart.value = [];
            return;
        }

        const hydrated: CartItem[] = [];

        parsed.forEach((entry) => {
            if (typeof entry.product_id !== 'string') {
                return;
            }

            const product = productsById.value.get(entry.product_id);
            if (!product) {
                return;
            }

            const quantity = normalizeQuantity(Number(entry.quantity ?? minQuantity));
            hydrated.push({
                ...product,
                quantity,
            });
        });

        cart.value = hydrated;
    } catch {
        cart.value = [];
    }
};

const clearCart = () => {
    cart.value = [];
    persistCart();
};

const getSelectedQuantity = (productId: string): number => {
    return normalizeQuantity(selectedQuantities.value[productId] ?? minQuantity);
};

const setSelectedQuantity = (productId: string, quantity: number) => {
    selectedQuantities.value[productId] = normalizeQuantity(quantity);
};

const onSelectedQuantityInput = (productId: string, event: Event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    setSelectedQuantity(productId, Number(target.value));
};

const addToCart = (productId: string) => {
    const product = productsById.value.get(productId);
    if (!product) {
        return;
    }

    const quantityToAdd = getSelectedQuantity(productId);
    const existing = cart.value.find((item) => item.id === productId);

    if (existing) {
        existing.quantity = normalizeQuantity(existing.quantity + quantityToAdd);
    } else {
        cart.value.push({
            ...product,
            quantity: quantityToAdd,
        });
    }

    selectedQuantities.value[productId] = minQuantity;
    persistCart();
};

const updateCartQuantity = (productId: string, quantity: number) => {
    const item = cart.value.find((row) => row.id === productId);
    if (!item) {
        return;
    }

    item.quantity = normalizeQuantity(quantity);
    persistCart();
};

const onCartQuantityInput = (productId: string, event: Event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    updateCartQuantity(productId, Number(target.value));
};

const removeCartItem = (productId: string) => {
    cart.value = cart.value.filter((item) => item.id !== productId);
    persistCart();
};

watch(
    () => props.products,
    () => {
        props.products.forEach((product) => {
            if (!selectedQuantities.value[product.id]) {
                selectedQuantities.value[product.id] = minQuantity;
            }
        });
    },
    { immediate: true },
);

onMounted(() => {
    loadCart();
});
</script>

<template>
    <Head title="E-Shop" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <section class="flex justify-end">
                <div class="flex min-w-52 items-center gap-2 rounded-2xl border border-sidebar-border/70 bg-black/5 px-4 py-3 dark:bg-white/5">
                    <ShoppingCart class="h-5 w-5 text-stone-700" />
                    <div>
                        <p class="text-xs uppercase tracking-wide text-stone-500">In cart</p>
                        <p class="text-lg font-semibold">{{ cartCount }} items</p>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <section class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-4 dark:bg-white/5">

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="product in products"
                            :key="product.id"
                            class="group flex h-full flex-col overflow-hidden rounded-2xl border border-sidebar-border/70 bg-black/5 dark:bg-white/5"
                        >
                            <img :src="product.image_url" :alt="product.name" class="h-36 w-full object-cover" loading="lazy" />

                            <div class="flex flex-1 flex-col p-3">
                                <h3 class="text-sm font-semibold">{{ product.name }}</h3>

                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold">{{ formatMoney(product.price_cents) }}</p>
                                    <div class="flex items-center gap-1 rounded-lg border border-sidebar-border/70 bg-muted/40 p-1">
                                        <button
                                            class="rounded-md px-2 py-1 text-sm hover:bg-background"
                                            type="button"
                                            @click="setSelectedQuantity(product.id, getSelectedQuantity(product.id) - 1)"
                                        >
                                            -
                                        </button>
                                        <input
                                            :value="getSelectedQuantity(product.id)"
                                            class="w-12 rounded-md border border-sidebar-border/70 bg-background px-1 py-1 text-center text-xs"
                                            max="99"
                                            min="1"
                                            type="number"
                                            @input="onSelectedQuantityInput(product.id, $event)"
                                        />
                                        <button
                                            class="rounded-md px-2 py-1 text-sm hover:bg-background"
                                            type="button"
                                            @click="setSelectedQuantity(product.id, getSelectedQuantity(product.id) + 1)"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>

                                <button
                                    class="mt-3 inline-flex items-center justify-center rounded-xl bg-stone-900 px-3 py-2 text-xs font-medium text-white transition"
                                    type="button"
                                    @click="addToCart(product.id)"
                                >
                                    Add to cart
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="space-y-4">
                    <article class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-4 dark:bg-white/5">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-lg font-semibold">Cart</h2>
                            <button
                                v-if="cart.length > 0"
                                class="text-xs text-muted-foreground hover:text-foreground"
                                type="button"
                                @click="clearCart"
                            >
                                Clear
                            </button>
                        </div>

                        <div v-if="cart.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground">
                            Your cart is empty.
                        </div>

                        <div v-else class="space-y-2">
                            <div v-for="item in cart" :key="item.id" class="rounded-xl border border-sidebar-border/70 bg-black/5 p-3 dark:bg-white/5">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium">{{ item.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ formatMoney(item.price_cents) }} / item</p>
                                    </div>

                                    <button class="rounded-md p-1 text-muted-foreground hover:text-rose-600" type="button" @click="removeCartItem(item.id)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>

                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center gap-1 rounded-lg border border-sidebar-border/70 bg-muted/40 p-1">
                                        <button
                                            class="rounded-md px-2 py-1 text-sm hover:bg-background"
                                            type="button"
                                            @click="updateCartQuantity(item.id, item.quantity - 1)"
                                        >
                                            -
                                        </button>
                                        <input
                                            :value="item.quantity"
                                            class="w-12 rounded-md border border-sidebar-border/70 bg-background px-1 py-1 text-center text-xs"
                                            max="99"
                                            min="1"
                                            type="number"
                                            @input="onCartQuantityInput(item.id, $event)"
                                        />
                                        <button
                                            class="rounded-md px-2 py-1 text-sm hover:bg-background"
                                            type="button"
                                            @click="updateCartQuantity(item.id, item.quantity + 1)"
                                        >
                                            +
                                        </button>
                                    </div>

                                    <p class="text-sm font-semibold">{{ formatMoney(item.price_cents * item.quantity) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t border-sidebar-border/70 pt-3">
                            <p class="text-sm text-muted-foreground">Total</p>
                            <p class="text-lg font-semibold">{{ formattedSubtotal }}</p>
                        </div>

                        <div class="mt-3">
                            <Link
                                v-if="cart.length > 0"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition"
                                href="/eshop/checkout"
                            >
                                Continue to payment
                            </Link>
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
