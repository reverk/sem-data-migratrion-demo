<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MongoItem extends Model
{
    /**
     * The connection name for the model.
     */
    protected $connection = 'mongodb';

    /**
     * The collection associated with the model.
     */
    protected $table = 'items';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = '_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        '_id',
        'item_name',
        'price_per_unit',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price_per_unit' => 'float',
    ];

    /**
     * Get the transactions for this item.
     */
    public function transactions()
    {
        return $this->hasMany(MongoTransaction::class, 'item_id', '_id');
    }
}
