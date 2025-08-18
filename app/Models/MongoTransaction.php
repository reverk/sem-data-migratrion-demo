<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MongoTransaction extends Model
{
    /**
     * The connection name for the model.
     */
    protected $connection = 'mongodb';

    /**
     * The collection associated with the model.
     */
    protected $table = 'transactions';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = '_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        '_id',
        'transaction_id',
        'item_id',
        'payment_method_id',
        'quantity',
        'total_spent',
        'location',
        'transaction_date',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'quantity' => 'float',
        'total_spent' => 'float',
        'transaction_date' => 'date',
    ];

    /**
     * Get the item that belongs to this transaction.
     */
    public function item()
    {
        return $this->belongsTo(MongoItem::class, 'item_id', '_id');
    }

    /**
     * Get the payment method that belongs to this transaction.
     */
    public function paymentMethod()
    {
        return $this->belongsTo(MongoPaymentMethod::class, 'payment_method_id', '_id');
    }
}
