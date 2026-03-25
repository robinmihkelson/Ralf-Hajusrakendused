<?php

namespace App\Support;

use Illuminate\Support\Arr;

class EshopCatalog
{
    /**
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     image_url: string,
     *     price_cents: int
     * }>
     */
    public static function all(): array
    {
        $products = config('eshop.products', []);

        if (! is_array($products)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            $id = Arr::get($item, 'id');
            $name = Arr::get($item, 'name');
            $description = Arr::get($item, 'description');
            $imageUrl = Arr::get($item, 'image_url');
            $priceCents = Arr::get($item, 'price_cents');

            if (! is_string($id) || ! is_string($name) || ! is_string($imageUrl)) {
                return null;
            }

            $normalizedDescription = is_string($description) && trim($description) !== ''
                ? $description
                : 'No description available.';

            if (is_int($priceCents)) {
                $normalizedPriceCents = $priceCents;
            } elseif (is_numeric($priceCents)) {
                $normalizedPriceCents = (int) $priceCents;
            } else {
                return null;
            }

            return [
                'id' => $id,
                'name' => $name,
                'description' => $normalizedDescription,
                'image_url' => $imageUrl,
                'price_cents' => $normalizedPriceCents,
            ];
        }, $products)));
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     image_url: string,
     *     price_cents: int
     * }|null
     */
    public static function find(string $productId): ?array
    {
        foreach (self::all() as $product) {
            if ($product['id'] === $productId) {
                return $product;
            }
        }

        return null;
    }
}
