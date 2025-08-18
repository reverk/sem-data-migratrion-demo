<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Item;
use App\Models\PaymentMethod;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('transaction_id')->primary();
            // Foreign key constraints
            $table->foreignIdFor(Item::class, 'item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(PaymentMethod::class, 'payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 8, 1);
            $table->decimal('total_spent', 8, 2);
            $table->enum('location', ['In-store', 'Takeaway']);
            $table->date('transaction_date');
            $table->timestamps();

            
            // Indexes for better performance
            $table->index(['transaction_date']);
            $table->index(['item_id']);
            $table->index(['payment_method_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};




