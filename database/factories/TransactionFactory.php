<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Item;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate a random transaction ID in the format TXN_XXXXXXX
        $transactionId = 'TXN_' . $this->faker->numberBetween(1000000, 9999999);
        
        // Generate random quantity (typically 1-5 items)
        $quantity = $this->faker->randomFloat(1, 1, 5);
        
        // Get random item and payment method IDs
        // Note: These should exist in the database when transactions are created
        $itemId = Item::inRandomOrder()->first()?->item_id ?? 1;
        $paymentMethodId = PaymentMethod::inRandomOrder()->first()?->payment_method_id ?? 1;
        
        // Calculate total spent based on item price and quantity
        $item = Item::find($itemId);
        $pricePerUnit = $item ? $item->price_per_unit : 2.00; // Default to coffee price
        $totalSpent = round($quantity * $pricePerUnit, 2);

        return [
            'transaction_id' => $transactionId,
            'item_id' => $itemId,
            'payment_method_id' => $paymentMethodId,
            'quantity' => $quantity,
            'total_spent' => $totalSpent,
            'location' => $this->faker->randomElement(['In-store', 'Takeaway']),
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }

    /**
     * Create a transaction with specific item and payment method.
     */
    public function withItemAndPayment(int $itemId, int $paymentMethodId): static
    {
        return $this->state(function (array $attributes) use ($itemId, $paymentMethodId) {
            $item = Item::find($itemId);
            $quantity = $attributes['quantity'] ?? $this->faker->randomFloat(1, 1, 5);
            $pricePerUnit = $item ? $item->price_per_unit : 2.00;
            $totalSpent = round($quantity * $pricePerUnit, 2);

            return [
                'item_id' => $itemId,
                'payment_method_id' => $paymentMethodId,
                'quantity' => $quantity,
                'total_spent' => $totalSpent,
            ];
        });
    }

    /**
     * Create a transaction for a specific date.
     */
    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_date' => $date,
        ]);
    }

    /**
     * Create a takeaway transaction.
     */
    public function takeaway(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => 'Takeaway',
        ]);
    }

    /**
     * Create an in-store transaction.
     */
    public function inStore(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => 'In-store',
        ]);
    }
}




