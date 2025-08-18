<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MongoTransactionWithDetails extends Model
{
    /**
     * The connection name for the model.
     */
    protected $connection = 'mongodb';

    /**
     * The collection associated with the model.
     */
    protected $table = 'transactions_with_details';

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
        'quantity',
        'total_spent',
        'location',
        'transaction_date',
        'item',
        'payment_method',
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
        'item' => 'array',
        'payment_method' => 'array',
    ];
}
