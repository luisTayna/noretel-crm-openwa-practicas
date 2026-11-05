<?php

namespace Modules\Customer\Interfaces;

use Modules\Core\Interfaces\RepositoryInterface;

interface DocumentRepositoryInterface extends RepositoryInterface
{
    /**
     * Get documents by customer
     *
     * @param int $customerId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByCustomer($customerId);

    /**
     * Get documents by type
     *
     * @param int $customerId
     * @param int $typeId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByType($customerId, $typeId);

    /**
     * Get documents by status
     *
     * @param int $customerId
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByStatus($customerId, $status);

    /**
     * Create new document version
     *
     * @param int $documentId
     * @param array $data
     * @return \Modules\Customer\Entities\DocumentVersion
     */
    public function createVersion($documentId, array $data);

    /**
     * Get document versions
     *
     * @param int $documentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getVersions($documentId);
}