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

    /**
     * Get the transaction_id attribute (maps to _id).
     */
    public function getTransactionIdAttribute()
    {
        return $this->_id;
    }

    /**
     * Set the transaction_id attribute (maps to _id).
     */
    public function setTransactionIdAttribute($value)
    {
        $this->attributes['_id'] = $value;
    }

    /**
     * Scope to query by transaction_id (maps to _id).
     */
    public function scopeWhereTransactionId($query, $transactionId)
    {
        return $query->where('_id', $transactionId);
    }
}
