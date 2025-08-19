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
