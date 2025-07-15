<?php

namespace Martin3r\LaravelActivityLog\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class Activity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'user_id',
        'properties',
        'activity_type',
        'description',
        'message',
        'metadata',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'properties' => 'array',
        'metadata'   => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid = UuidV7::generate();
        });
    }

    /**
     * Polymorphic inverse relation to the subject model.
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Relation to the user who performed the activity.
     */
    public function user(): ?BelongsTo
    {
        $model = config('auth.providers.users.model');

        return ($model && class_exists($model))
            ? $this->belongsTo($model, 'user_id')
            : null;
    }
}