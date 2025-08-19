<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MongoUser extends Model
{
    /**
     * The connection name for the model.
     */
    protected $connection = 'mongodb';

    /**
     * The collection associated with the model.
     */
    protected $table = 'users';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = '_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        '_id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
