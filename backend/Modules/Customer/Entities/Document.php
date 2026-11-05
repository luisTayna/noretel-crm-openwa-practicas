<?php

namespace Modules\Customer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Document extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'document_type_id',
        'name',
        'file_path',
        'upload_date',
        'status',
        'classification'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'upload_date' => 'datetime'
    ];

    /**
     * Get the customer that owns the document.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the document type of this document.
     */
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Get the versions of this document.
     */
    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Get the latest version of this document.
     */
    public function latestVersion()
    {
        return $this->hasOne(DocumentVersion::class)->latest('version_number');
    }

    /**
     * Scope a query to only include documents of a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include documents of a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $typeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $typeId)
    {
        return $query->where('document_type_id', $typeId);
    }

    /**
     * Create a new version of this document.
     *
     * @param  string  $filePath
     * @param  string|null  $changes
     * @return DocumentVersion
     */
    public function createNewVersion($filePath, $changes = null)
    {
        $latestVersion = $this->versions()->max('version_number') ?? 0;
        
        return $this->versions()->create([
            'version_number' => $latestVersion + 1,
            'file_path' => $filePath,
            'version_date' => now(),
            'changes' => $changes
        ]);
    }
}