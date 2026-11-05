<?php

namespace Modules\Customer\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Customer\Interfaces\DocumentRepositoryInterface;
use Modules\Customer\Entities\Document;
use Modules\Customer\Entities\DocumentVersion;

class DocumentRepository extends BaseRepository implements DocumentRepositoryInterface
{
    /**
     * DocumentRepository constructor.
     *
     * @param Document $model
     */
    public function __construct(Document $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function getByCustomer($customerId)
    {
        return $this->model->where('customer_id', $customerId)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByType($customerId, $typeId)
    {
        return $this->model->where('customer_id', $customerId)
                          ->where('document_type_id', $typeId)
                          ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByStatus($customerId, $status)
    {
        return $this->model->where('customer_id', $customerId)
                          ->where('status', $status)
                          ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function createVersion($documentId, array $data)
    {
        $document = $this->find($documentId);
        
        if (!$document) {
            throw new \Exception('Document not found');
        }
        
        $latestVersion = $document->versions()->max('version_number') ?? 0;
        
        return $document->versions()->create([
            'version_number' => $latestVersion + 1,
            'file_path' => $data['file_path'],
            'version_date' => $data['version_date'] ?? now(),
            'changes' => $data['changes'] ?? null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getVersions($documentId)
    {
        $document = $this->find($documentId);
        
        if (!$document) {
            return collect();
        }
        
        return $document->versions()->orderBy('version_number', 'desc')->get();
    }
}