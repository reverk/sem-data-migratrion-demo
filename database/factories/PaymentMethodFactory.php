<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PaymentMethod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentMethods = ['Cash', 'Credit Card', 'Digital Wallet'];

        return [
            'method_name' => $this->faker->randomElement($paymentMethods),
        ];
    }

    /**
     * Create a specific payment method.
     */
    public function method(string $methodName): static
    {
        return $this->state(fn (array $attributes) => [
            'method_name' => $methodName,
        ]);
    }

    /**
     * Create all standard payment methods.
     */
    public static function createStandardMethods(): void
    {
        $methods = ['Cash', 'Credit Card', 'Digital Wallet'];

        foreach ($methods as $method) {
            PaymentMethod::create([
                'method_name' => $method,
            ]);
        }
    }
}




