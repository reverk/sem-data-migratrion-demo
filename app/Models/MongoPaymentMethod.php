<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MongoPaymentMethod extends Model
{
    /**
     * The connection name for the model.
     */
    protected $connection = 'mongodb';

    /**
     * The collection associated with the model.
     */
    protected $table = 'payment_methods';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = '_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        '_id',
        'method_name',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the transactions for this payment method.
     */
    public function transactions()
    {
        return $this->hasMany(MongoTransaction::class, 'payment_method_id', '_id');
    }
}
