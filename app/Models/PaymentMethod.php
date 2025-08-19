<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     */
    protected $connection = 'mysql';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'payment_method_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'method_name',
    ];

    /**
     * Get the transactions for this payment method.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'payment_method_id', 'payment_method_id');
    }
}




