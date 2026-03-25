<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, CreditCard, LoaderCircle, ShoppingCart } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type Product = {
    id: string;
    name: string;
    description: string;
    image_url: string;
    price_cents: number;
};

type CartItem = Product & {
    quantity: number;
};

type CheckoutResponse = {
    checkout_url: string;
};

type CheckoutStatus = 'success' | 'failed' | 'pending';

type PaymentStatusResponse = {
    status: CheckoutStatus;
    message: string;
    clear_cart: boolean;
};

type PaymentMethodPreference = 'card' | 'wallet' | 'bank';

type StatusBanner = {
    kind: 'success' | 'error' | 'info';
    text: string;
};

const props = defineProps<{
    products: Product[];
    currency: string;
    stripeConfigured: boolean;
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
    {
        title: 'Checkout',
        href: '/eshop/checkout',
    },
];

const page = usePage<SharedData>();

const cartStorageKey = 'eshop-cart-v1';
const minQuantity = 1;
const maxQuantity = 99;

const cart = ref<CartItem[]>([]);
const isRedirectingToStripe = ref(false);
const isCheckingPaymentStatus = ref(false);
const statusBanner = ref<StatusBanner | null>(null);

const checkoutForm = reactive({
    first_name: '',
    last_name: '',
    email: page.props.auth?.user?.email ?? '',
    phone: '',
    payment_method_preference: 'card' as PaymentMethodPreference,
});

const paymentOptions: Array<{
    id: PaymentMethodPreference;
    title: string;
    description: string;
}> = [
    {
        id: 'card',
        title: 'Card payment',
        description: 'Visa, Mastercard, and other cards.',
    },
];

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

const canCheckout = computed(() => {
    return cart.value.length > 0 && !isRedirectingToStripe.value && props.stripeConfigured;
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

const csrfToken = (): string => {
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

const clearCart = () => {
    cart.value = [];
    persistCart();
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

const setStatusBanner = (kind: StatusBanner['kind'], text: string) => {
    statusBanner.value = { kind, text };
};

const clearStatusBanner = () => {
    statusBanner.value = null;
};

const prefillNameFields = () => {
    const fullName = page.props.auth?.user?.name?.trim() ?? '';

    if (!fullName) {
        return;
    }

    const tokens = fullName.split(/\s+/);
    checkoutForm.first_name = tokens.shift() ?? '';
    checkoutForm.last_name = tokens.join(' ');
};

const validateCheckout = (): string | null => {
    if (!props.stripeConfigured) {
        return 'Stripe is not configured. Add STRIPE_KEY and STRIPE_SECRET to `.env`.';
    }

    if (cart.value.length === 0) {
        return 'Your cart is empty. Add products before paying.';
    }

    if (!checkoutForm.first_name.trim()) {
        return 'First name is required.';
    }

    if (!checkoutForm.last_name.trim()) {
        return 'Last name is required.';
    }

    if (!checkoutForm.email.trim()) {
        return 'Email is required.';
    }

    if (!checkoutForm.phone.trim()) {
        return 'Phone number is required.';
    }

    return null;
};

const startCheckout = async () => {
    clearStatusBanner();

    const validationError = validateCheckout();
    if (validationError) {
        setStatusBanner('error', validationError);
        return;
    }

    isRedirectingToStripe.value = true;

    try {
        const payload = {
            first_name: checkoutForm.first_name.trim(),
            last_name: checkoutForm.last_name.trim(),
            email: checkoutForm.email.trim(),
            phone: checkoutForm.phone.trim(),
            payment_method_preference: checkoutForm.payment_method_preference,
            items: cart.value.map((item) => ({
                product_id: item.id,
                quantity: item.quantity,
            })),
        };

        const result = await requestJson<CheckoutResponse>('/eshop/checkout/session', {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        window.location.assign(result.checkout_url);
    } catch (error) {
        if (error instanceof Error) {
            setStatusBanner('error', error.message);
        } else {
            setStatusBanner('error', 'Unable to start payment.');
        }

        isRedirectingToStripe.value = false;
    }
};

const checkoutStatusToBanner = (status: CheckoutStatus): StatusBanner['kind'] => {
    if (status === 'success') {
        return 'success';
    }

    if (status === 'failed') {
        return 'error';
    }

    return 'info';
};

const handleCheckoutReturn = async () => {
    if (typeof window === 'undefined') {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const checkoutState = params.get('checkout');
    const sessionId = params.get('session_id');

    if (!checkoutState) {
        return;
    }

    if (checkoutState === 'cancel') {
        setStatusBanner('error', 'Payment was canceled. Products remain in your cart.');
        window.history.replaceState({}, '', window.location.pathname);
        return;
    }

    if (checkoutState !== 'success' || !sessionId) {
        setStatusBanner('info', 'Payment is pending. Please check again shortly.');
        window.history.replaceState({}, '', window.location.pathname);
        return;
    }

    isCheckingPaymentStatus.value = true;

    try {
        const result = await requestJson<PaymentStatusResponse>(`/eshop/checkout/status?session_id=${encodeURIComponent(sessionId)}`);
        setStatusBanner(checkoutStatusToBanner(result.status), result.message);

        if (result.clear_cart) {
            clearCart();
        }
    } catch (error) {
        if (error instanceof Error) {
            setStatusBanner('error', error.message);
        } else {
            setStatusBanner('error', 'Unable to verify payment status.');
        }
    } finally {
        isCheckingPaymentStatus.value = false;
        window.history.replaceState({}, '', window.location.pathname);
    }
};

onMounted(async () => {
    prefillNameFields();
    loadCart();
    await handleCheckoutReturn();
});
</script>

<template>
    <Head title="Checkout" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-3">
            <section class="flex items-center justify-between gap-3">
                <Link class="text-sm text-muted-foreground hover:text-foreground" href="/eshop">← Back to products</Link>
                <div class="flex min-w-52 items-center gap-2 rounded-2xl border border-sidebar-border/70 bg-black/5 px-4 py-3 dark:bg-white/5">
                    <ShoppingCart class="h-5 w-5 text-stone-700" />
                    <div>
                        <p class="text-xs uppercase tracking-wide text-stone-500">In cart</p>
                        <p class="text-lg font-semibold">{{ cartCount }} items</p>
                    </div>
                </div>
            </section>

            <section
                v-if="statusBanner"
                :class="[
                    'rounded-2xl border px-4 py-3 text-sm',
                    statusBanner.kind === 'success' && 'border-emerald-300 bg-emerald-50 text-emerald-900',
                    statusBanner.kind === 'error' && 'border-rose-300 bg-rose-50 text-rose-900',
                    statusBanner.kind === 'info' && 'border-sky-300 bg-sky-50 text-sky-900',
                ]"
            >
                <div class="flex items-start gap-2">
                    <CircleCheck v-if="statusBanner.kind === 'success'" class="mt-0.5 h-4 w-4 shrink-0" />
                    <CircleAlert v-else class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>{{ statusBanner.text }}</p>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[1.1fr_1fr]">
                <article class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-4 dark:bg-white/5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Order summary</h2>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ cartCount }} items</p>
                    </div>

                    <div v-if="cart.length === 0" class="rounded-xl border border-dashed border-sidebar-border/70 p-3 text-sm text-muted-foreground">
                        Your cart is empty.
                    </div>

                    <div v-else class="space-y-2">
                        <div v-for="item in cart" :key="item.id" class="rounded-xl border border-sidebar-border/70 bg-black/5 p-3 dark:bg-white/5">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium">{{ item.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ item.quantity }} x {{ formatMoney(item.price_cents) }}</p>
                                </div>
                                <p class="text-sm font-semibold">{{ formatMoney(item.price_cents * item.quantity) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-sidebar-border/70 pt-3">
                        <p class="text-sm text-muted-foreground">Total</p>
                        <p class="text-lg font-semibold">{{ formattedSubtotal }}</p>
                    </div>
                </article>

                <article class="rounded-2xl border border-sidebar-border/70 bg-black/5 p-4 dark:bg-white/5">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold">Payment details</h2>
                    </div>

                    <form class="space-y-2" @submit.prevent="startCheckout">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium text-muted-foreground" for="firstName">First name</label>
                                <input
                                    id="firstName"
                                    v-model="checkoutForm.first_name"
                                    class="mt-1 w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    type="text"
                                />
                            </div>

                            <div>
                                <label class="text-xs font-medium text-muted-foreground" for="lastName">Last name</label>
                                <input
                                    id="lastName"
                                    v-model="checkoutForm.last_name"
                                    class="mt-1 w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    type="text"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-muted-foreground" for="email">Email</label>
                            <input id="email" v-model="checkoutForm.email" class="mt-1 w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2 text-sm" type="email" />
                        </div>

                        <div>
                            <label class="text-xs font-medium text-muted-foreground" for="phone">Phone</label>
                            <input id="phone" v-model="checkoutForm.phone" class="mt-1 w-full rounded-xl border border-sidebar-border/70 bg-background px-3 py-2 text-sm" type="tel" />
                        </div>

                        <fieldset class="rounded-xl border border-sidebar-border/70 p-2">
                            <legend class="px-1 text-xs font-medium text-muted-foreground">Payment method preference</legend>
                            <label
                                v-for="option in paymentOptions"
                                :key="option.id"
                                class="mt-2 flex cursor-pointer items-start gap-2 rounded-lg border border-sidebar-border/70 px-2 py-2 first:mt-0"
                            >
                                <input v-model="checkoutForm.payment_method_preference" :value="option.id" class="mt-1" name="paymentMethod" type="radio" />
                                <span>
                                    <span class="block text-sm font-medium">{{ option.title }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ option.description }}</span>
                                </span>
                            </label>
                        </fieldset>

                        <p v-if="!stripeConfigured" class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            Stripe is not configured. Add `STRIPE_KEY`, `STRIPE_SECRET`, and `STRIPE_WEBHOOK_SECRET` to `.env`.
                        </p>

                        <button
                            :disabled="!canCheckout"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                            type="submit"
                        >
                            <LoaderCircle v-if="isRedirectingToStripe || isCheckingPaymentStatus" class="h-4 w-4 animate-spin" />
                            <CreditCard v-else class="h-4 w-4" />
                            {{ isRedirectingToStripe ? 'Redirecting to Stripe...' : 'Pay with Stripe' }}
                        </button>
                    </form>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
