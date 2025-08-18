<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Predefined menu items with their standard prices
        $menuItems = [
            ['name' => 'Coffee', 'price' => 2.00],
            ['name' => 'Tea', 'price' => 1.50],
            ['name' => 'Sandwich', 'price' => 4.00],
            ['name' => 'Salad', 'price' => 5.00],
            ['name' => 'Cake', 'price' => 3.00],
            ['name' => 'Cookie', 'price' => 1.00],
            ['name' => 'Smoothie', 'price' => 4.00],
            ['name' => 'Juice', 'price' => 3.00],
        ];

        $item = $this->faker->randomElement($menuItems);

        return [
            'item_name' => $item['name'],
            'price_per_unit' => $item['price'],
        ];
    }

    /**
     * Create a specific menu item.
     */
    public function menuItem(string $name, float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'item_name' => $name,
            'price_per_unit' => $price,
        ]);
    }

    /**
     * Create all standard menu items.
     */
    public static function createStandardMenu(): void
    {
        $menuItems = [
            ['name' => 'Coffee', 'price' => 2.00],
            ['name' => 'Tea', 'price' => 1.50],
            ['name' => 'Sandwich', 'price' => 4.00],
            ['name' => 'Salad', 'price' => 5.00],
            ['name' => 'Cake', 'price' => 3.00],
            ['name' => 'Cookie', 'price' => 1.00],
            ['name' => 'Smoothie', 'price' => 4.00],
            ['name' => 'Juice', 'price' => 3.00],
        ];

        foreach ($menuItems as $item) {
            Item::create([
                'item_name' => $item['name'],
                'price_per_unit' => $item['price'],
            ]);
        }
    }
}




