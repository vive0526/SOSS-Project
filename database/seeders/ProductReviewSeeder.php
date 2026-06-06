<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductReviewSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()
            ->where('role', 'customer')
            ->orderBy('created_at')
            ->first();

        if (!$customer) {
            return;
        }

        $comments = [
            [5, 'Product quality is very good and delivery was smooth.'],
            [5, 'Fresh, well packed, and exactly as described.'],
            [4, 'Good product overall. I would order again.'],
            [4, 'Satisfied with the purchase and service.'],
            [5, 'Excellent quality. Recommended for other customers.'],
        ];

        Product::query()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->take(12)
            ->get()
            ->each(function (Product $product, int $index) use ($customer, $comments) {
                if ($product->reviews()->where('is_dummy', true)->exists()) {
                    return;
                }

                [$rating, $comment] = $comments[$index % count($comments)];

                ProductReview::create([
                    'product_id' => $product->getKey(),
                    'order_id' => null,
                    'order_item_id' => null,
                    'user_id' => $customer->getKey(),
                    'rating' => $rating,
                    'comment' => $comment,
                    'status' => 'approved',
                    'is_dummy' => true,
                ]);
            });
    }
}
