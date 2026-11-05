<?php

namespace Modules\Customer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class DocumentType extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'requires_verification',
        'allowed_format'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requires_verification' => 'boolean',
        'allowed_format' => 'json'
    ];

    /**
     * Get the documents of this type.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Set the allowed format attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setAllowedFormatAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['allowed_format'] = json_encode($value);
        } else {
            $this->attributes['allowed_format'] = $value;
        }
    }
}