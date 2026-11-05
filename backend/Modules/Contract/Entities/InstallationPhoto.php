<?php

namespace Modules\Contract\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contract\Entities\Installation;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class InstallationPhoto extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'installation_id',
        'file_path',
        'description',
    ];

    /**
     * Get the installation that owns the photo.
     */
    public function installation()
    {
        return $this->belongsTo(Installation::class);
    }

    /**
     * Get the URL to the photo.
     */
    public function getUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Get the thumbnail URL.
     */
    public function getThumbnailUrlAttribute()
    {
        $path_parts = pathinfo($this->file_path);
        $thumbnail_path = $path_parts['dirname'] . '/thumbnails/' . $path_parts['basename'];
        
        return asset('storage/' . $thumbnail_path);
    }
}