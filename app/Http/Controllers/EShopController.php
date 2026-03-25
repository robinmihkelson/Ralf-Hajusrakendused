<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\EshopCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class EShopController extends Controller
{
    /**
     * @return Response
     */
    public function index(): Response
    {
        return Inertia::render('EShop', $this->shopPageProps());
    }

    public function checkout(): Response
    {
        return Inertia::render('EShopCheckout', $this->shopPageProps());
    }

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'payment_method_preference' => ['required', 'string', Rule::in(['card', 'wallet', 'bank'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        if (! $this->hasStripeCredentials()) {
            return response()->json([
                'error' => 'Stripe is not configured. Add STRIPE_SECRET and STRIPE_KEY to .env.',
            ], 500);
        }

        $groupedItems = [];
        foreach ($payload['items'] as $item) {
            $productId = (string) $item['product_id'];
            $quantity = (int) $item['quantity'];
            $groupedItems[$productId] = ($groupedItems[$productId] ?? 0) + $quantity;
        }

        $currency = strtolower((string) config('eshop.currency', 'eur'));
        $totalCents = 0;
        $orderItems = [];
        $stripeLineItems = [];

        foreach ($groupedItems as $productId => $quantity) {
            $product = EshopCatalog::find($productId);

            if ($product === null) {
                return response()->json([
                    'error' => "Product with ID '{$productId}' was not found in the catalog.",
                ], 422);
            }

            $lineTotalCents = $product['price_cents'] * $quantity;
            $totalCents += $lineTotalCents;

            $orderItems[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'product_description' => $product['description'],
                'product_image_url' => $product['image_url'],
                'unit_amount_cents' => $product['price_cents'],
                'quantity' => $quantity,
                'line_total_cents' => $lineTotalCents,
            ];

            $stripeLineItems[] = [
                'quantity' => $quantity,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $product['price_cents'],
                    'product_data' => [
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'images' => [$product['image_url']],
                        'metadata' => [
                            'product_id' => $product['id'],
                        ],
                    ],
                ],
            ];
        }

        $order = DB::transaction(function () use ($payload, $request, $currency, $totalCents, $orderItems): Order {
            /** @var Order $order */
            $order = Order::query()->create([
                'user_id' => $request->user()->id,
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'currency' => $currency,
                'total_amount_cents' => $totalCents,
                'payment_method_preference' => $payload['payment_method_preference'],
                'status' => Order::STATUS_PENDING,
            ]);

            $order->items()->createMany($orderItems);

            return $order;
        });

        try {
            $checkoutUrl = route('eshop.checkout', [], true);
            $session = $this->stripeClient()->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => $stripeLineItems,
                'customer_email' => $order->email,
                'client_reference_id' => (string) $order->id,
                'success_url' => $checkoutUrl.'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $checkoutUrl.'?checkout=cancel',
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'user_id' => (string) $request->user()->id,
                    'payment_method_preference' => $order->payment_method_preference,
                ],
            ]);

            if (! is_string($session->id) || ! is_string($session->url)) {
                throw new \RuntimeException('Stripe session response was incomplete.');
            }

            $order->update([
                'stripe_checkout_session_id' => $session->id,
            ]);

            return response()->json([
                'checkout_url' => $session->url,
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to create Stripe checkout session.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            $order->update([
                'status' => Order::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to start Stripe payment. Please try again.',
            ], 500);
        }
    }

    public function paymentStatus(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        /** @var Order|null $order */
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('stripe_checkout_session_id', $payload['session_id'])
            ->first();

        if ($order === null) {
            return response()->json([
                'error' => 'No order was found for this payment session.',
            ], 404);
        }

        try {
            $session = $this->stripeClient()->checkout->sessions->retrieve(
                $payload['session_id'],
                ['expand' => ['payment_intent']]
            );

            $result = $this->synchronizeOrderStatus($order, $session);

            return response()->json($result);
        } catch (Throwable $exception) {
            Log::warning('Failed to retrieve Stripe checkout session.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json($this->statusPayloadFromOrder($order));
        }
    }

    public function stripeWebhook(Request $request): JsonResponse
    {
        $webhookSecret = (string) config('services.stripe.webhook_secret', '');
        $signature = (string) $request->header('Stripe-Signature', '');

        if ($webhookSecret === '') {
            return response()->json(['error' => 'Stripe webhook secret is not configured.'], 500);
        }

        if ($signature === '') {
            return response()->json(['error' => 'Stripe signature is missing.'], 400);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $webhookSecret);
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Stripe webhook signature verification failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Stripe webhook signature is invalid.'], 400);
        }

        $object = $event->data->object;

        if ($object instanceof StripeCheckoutSession) {
            $sessionId = is_string($object->id) ? $object->id : null;
            $metadata = $object->metadata;
            $orderIdRaw = is_object($metadata) ? ($metadata->order_id ?? null) : null;
            $orderId = is_numeric($orderIdRaw) ? (int) $orderIdRaw : 0;

            if ($orderId <= 0 && $sessionId === null) {
                return response()->json(['received' => true]);
            }

            /** @var Order|null $order */
            $order = Order::query()
                ->when(
                    $orderId > 0,
                    fn ($query) => $query->whereKey($orderId),
                    fn ($query) => $query->where('stripe_checkout_session_id', $sessionId ?? '')
                )
                ->first();

            if ($order !== null) {
                $this->synchronizeOrderStatus($order, $object);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * @return array{
     *     status: string,
     *     message: string,
     *     clear_cart: bool
     * }
     */
    private function synchronizeOrderStatus(Order $order, StripeCheckoutSession $session): array
    {
        $sessionId = is_string($session->id) ? $session->id : null;
        $sessionStatus = is_string($session->status) ? $session->status : 'open';
        $paymentStatus = is_string($session->payment_status) ? $session->payment_status : 'unpaid';
        $paymentIntent = $session->payment_intent;
        $paymentIntentId = null;

        if (is_string($paymentIntent)) {
            $paymentIntentId = $paymentIntent;
        } elseif (is_object($paymentIntent)) {
            $paymentIntentObjectId = $paymentIntent->id ?? null;
            $paymentIntentId = is_string($paymentIntentObjectId) ? $paymentIntentObjectId : null;
        }

        $mapped = $this->mapStripeStatus($sessionStatus, $paymentStatus);

        if ($order->status === Order::STATUS_PAID && $mapped['order_status'] !== Order::STATUS_PAID) {
            return $this->statusPayloadFromOrder($order);
        }

        $attributes = [
            'status' => $mapped['order_status'],
            'stripe_checkout_session_id' => $sessionId ?? $order->stripe_checkout_session_id,
            'stripe_payment_intent_id' => $paymentIntentId ?? $order->stripe_payment_intent_id,
        ];

        if ($mapped['order_status'] === Order::STATUS_PAID) {
            $attributes['paid_at'] = $order->paid_at ?? now();
            $attributes['failure_reason'] = null;
        }

        if ($mapped['order_status'] === Order::STATUS_FAILED) {
            $attributes['paid_at'] = null;
            $attributes['failure_reason'] = 'Payment failed, canceled or session expired.';
        }

        if ($mapped['order_status'] === Order::STATUS_PENDING) {
            $attributes['failure_reason'] = null;
        }

        $order->fill($attributes);
        $order->save();

        return [
            'status' => $mapped['client_status'],
            'message' => $mapped['message'],
            'clear_cart' => $mapped['client_status'] === 'success',
        ];
    }

    /**
     * @return array{
     *     order_status: string,
     *     client_status: string,
     *     message: string
     * }
     */
    private function mapStripeStatus(string $sessionStatus, string $paymentStatus): array
    {
        if ($paymentStatus === 'paid') {
            return [
                'order_status' => Order::STATUS_PAID,
                'client_status' => 'success',
                'message' => 'Payment was successful. Thank you for your order.',
            ];
        }

        if ($sessionStatus === 'expired' || ($sessionStatus === 'complete' && $paymentStatus === 'unpaid')) {
            return [
                'order_status' => Order::STATUS_FAILED,
                'client_status' => 'failed',
                'message' => 'Payment failed. Products remain in your cart.',
            ];
        }

        return [
            'order_status' => Order::STATUS_PENDING,
            'client_status' => 'pending',
            'message' => 'Payment is pending. Please wait for final confirmation.',
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     message: string,
     *     clear_cart: bool
     * }
     */
    private function statusPayloadFromOrder(Order $order): array
    {
        if ($order->status === Order::STATUS_PAID) {
            return [
                'status' => 'success',
                'message' => 'Payment was successful. Thank you for your order.',
                'clear_cart' => true,
            ];
        }

        if ($order->status === Order::STATUS_FAILED) {
            return [
                'status' => 'failed',
                'message' => 'Payment failed. Products remain in your cart.',
                'clear_cart' => false,
            ];
        }

        return [
            'status' => 'pending',
            'message' => 'Payment is pending.',
            'clear_cart' => false,
        ];
    }

    private function hasStripeCredentials(): bool
    {
        return (string) config('services.stripe.secret', '') !== ''
            && (string) config('services.stripe.key', '') !== '';
    }

    /**
     * @return array{
     *     products: array<int, array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         image_url: string,
     *         price_cents: int
     *     }>,
     *     currency: string,
     *     stripeConfigured: bool
     * }
     */
    private function shopPageProps(): array
    {
        return [
            'products' => EshopCatalog::all(),
            'currency' => strtoupper((string) config('eshop.currency', 'EUR')),
            'stripeConfigured' => $this->hasStripeCredentials(),
        ];
    }

    private function stripeClient(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }
}
