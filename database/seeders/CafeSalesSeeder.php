<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class CafeSalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create standard menu items
        $menuItems = [
            ['item_name' => 'Coffee', 'price_per_unit' => 2.00],
            ['item_name' => 'Tea', 'price_per_unit' => 1.50],
            ['item_name' => 'Sandwich', 'price_per_unit' => 4.00],
            ['item_name' => 'Salad', 'price_per_unit' => 5.00],
            ['item_name' => 'Cake', 'price_per_unit' => 3.00],
            ['item_name' => 'Cookie', 'price_per_unit' => 1.00],
            ['item_name' => 'Smoothie', 'price_per_unit' => 4.00],
            ['item_name' => 'Juice', 'price_per_unit' => 3.00],
        ];

        foreach ($menuItems as $item) {
            Item::create($item);
        }

        // Create standard payment methods
        $paymentMethods = ['Cash', 'Credit Card', 'Digital Wallet'];

        foreach ($paymentMethods as $method) {
            PaymentMethod::create(['method_name' => $method]);
        }

        // Create sample transactions using the factory
        Transaction::factory()->count(100)->create();

        $this->command->info('Cafe sales data seeded successfully!');
        $this->command->info('Created: ' . Item::count() . ' items');
        $this->command->info('Created: ' . PaymentMethod::count() . ' payment methods');
        $this->command->info('Created: ' . Transaction::count() . ' transactions');
    }
}




