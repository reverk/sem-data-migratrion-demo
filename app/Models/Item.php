<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
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
    protected $primaryKey = 'item_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_name',
        'price_per_unit',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price_per_unit' => 'decimal:2',
    ];

    /**
     * Get the transactions for this item.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'item_id', 'item_id');
    }
}




