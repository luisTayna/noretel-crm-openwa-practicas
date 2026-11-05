<?php

namespace Modules\Customer\Services;

use Modules\Customer\Interfaces\DocumentRepositoryInterface;
use Modules\Customer\Entities\DocumentType;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DocumentService
{
    /**
     * @var DocumentRepositoryInterface
     */
    protected $documentRepository;

    /**
     * DocumentService constructor.
     *
     * @param DocumentRepositoryInterface $documentRepository
     */
    public function __construct(DocumentRepositoryInterface $documentRepository)
    {
        $this->documentRepository = $documentRepository;
    }

    /**
     * Upload a new document
     *
     * @param array $documentData
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $uploadedBy
     * @param string $ip
     * @return array
     */
    public function uploadDocument(array $documentData, $file, $uploadedBy, $ip)
    {
        DB::beginTransaction();

        try {
            // Verify document type exists.
            $documentType = DocumentType::find($documentData['document_type_id']);
            
            if (!$documentType) {
                return [
                    'success' => false,
                    'message' => 'Tipo de documento no encontrado.'
                ];
            }
            
            // Upload file to storage
            $filePath = $this->storeFile($file, $documentData['customer_id'], $documentType->name);
            
            // Create document
            $documentData['file_path'] = $filePath;
            $documentData['upload_date'] = now();
            $documentData['status'] = $documentData['status'] ?? 'pending';
            
            $document = $this->documentRepository->create($documentData);
            
            // Create initial version
            $this->documentRepository->createVersion($document->id, [
                'file_path' => $filePath,
                'version_number' => 1,
                'version_date' => now(),
                'changes' => 'Versión inicial'
            ]);
            
            // Register audit
            AuditLog::register(
                $uploadedBy,
                'document_uploaded',
                'customer_document',
                "Documento subido: {$document->name}",
                $ip,
                null,
                $document->toArray()
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'document' => $document
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Error al subir el documento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update document information
     *
     * @param int $documentId
     * @param array $documentData
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function updateDocument($documentId, array $documentData, $updatedBy, $ip)
    {
        // Get document before update for audit
        $document = $this->documentRepository->find($documentId);
        
        if (!$document) {
            return [
                'success' => false,
                'message' => 'Documento no encontrado.'
            ];
        }
        
        $oldData = $document->toArray();
        
        // Update document
        $this->documentRepository->update($documentId, $documentData);
        $updatedDocument = $this->documentRepository->find($documentId);
        
        // Register audit
        AuditLog::register(
            $updatedBy,
            'document_updated',
            'customer_document',
            "Documento actualizado: {$updatedDocument->name}",
            $ip,
            $oldData,
            $updatedDocument->toArray()
        );
        
        return [
            'success' => true,
            'document' => $updatedDocument
        ];
    }

    /**
     * Upload a new version of a document
     *
     * @param int $documentId
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $changes
     * @param int $uploadedBy
     * @param string $ip
     * @return array
     */
    public function uploadNewVersion($documentId, $file, $changes, $uploadedBy, $ip)
    {
        DB::beginTransaction();
        
        try {
            $document = $this->documentRepository->find($documentId);
            
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Documento no encontrado.'
                ];
            }
            
            // Upload file to storage
            $filePath = $this->storeFile($file, $document->customer_id, $document->documentType->name);
            
            // Create new version
            $version = $this->documentRepository->createVersion($documentId, [
                'file_path' => $filePath,
                'version_date' => now(),
                'changes' => $changes
            ]);
            
            // Update document's file_path to point to latest version
            $this->documentRepository->update($documentId, [
                'file_path' => $filePath,
                'upload_date' => now()
            ]);
            
            // Register audit
            AuditLog::register(
                $uploadedBy,
                'document_version_uploaded',
                'customer_document',
                "Nueva versión de documento subida: {$document->name} (v{$version->version_number})",
                $ip,
                null,
                $version->toArray()
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'version' => $version
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Error al subir la nueva versión del documento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change document status
     *
     * @param int $documentId
     * @param string $status
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function changeStatus($documentId, $status, $updatedBy, $ip)
    {
        // Get document
        $document = $this->documentRepository->find($documentId);
        
        if (!$document) {
            return [
                'success' => false,
                'message' => 'Documento no encontrado.'
            ];
        }
        
        $oldStatus = $document->status;
        
        // Update status
        $this->documentRepository->update($documentId, ['status' => $status]);
        
        // Register audit
        AuditLog::register(
            $updatedBy,
            'document_status_changed',
            'customer_document',
            "Estado de documento cambiado: {$document->name} ($oldStatus → $status)",
            $ip,
            ['status' => $oldStatus],
            ['status' => $status]
        );
        
        return [
            'success' => true,
            'message' => 'Estado de documento actualizado correctamente.'
        ];
    }

    /**
     * Delete a document
     *
     * @param int $documentId
     * @param int $deletedBy
     * @param string $ip
     * @return array
     */
    public function deleteDocument($documentId, $deletedBy, $ip)
    {
        // Get document
        $document = $this->documentRepository->find($documentId);
        
        if (!$document) {
            return [
                'success' => false,
                'message' => 'Documento no encontrado.'
            ];
        }
        
        $documentData = $document->toArray();
        
        DB::beginTransaction();
        
        try {
            // Delete document file(s)
            $this->deleteDocumentFiles($document);
            
            // Delete document from database
            $this->documentRepository->delete($documentId);
            
            // Register audit
            AuditLog::register(
                $deletedBy,
                'document_deleted',
                'customer_document',
                "Documento eliminado: {$document->name}",
                $ip,
                $documentData,
                null
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Documento eliminado correctamente.'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Error al eliminar el documento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Store an uploaded file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $customerId
     * @param string $documentType
     * @return string
     */
    protected function storeFile($file, $customerId, $documentType)
    {
        $path = "customers/{$customerId}/documents/{$documentType}";
        $filename = time() . '_' . $file->getClientOriginalName();
        
        return $file->storeAs($path, $filename, 'public');
    }

    /**
     * Delete document files from storage
     *
     * @param \Modules\Customer\Entities\Document $document
     * @return void
     */
    protected function deleteDocumentFiles($document)
    {
        // Delete main document file
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }
        
        // Delete all version files
        foreach ($document->versions as $version) {
            if (Storage::disk('public')->exists($version->file_path)) {
                Storage::disk('public')->delete($version->file_path);
            }
        }
    }
}
