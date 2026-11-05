<?php

namespace Modules\Core\Interfaces;

interface RepositoryInterface
{
    /**
     * Get all records
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all($columns = ['*']);

    /**
     * Find record by id
     *
     * @param int $id
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function find($id, $columns = ['*']);

    /**
     * Find record by attribute
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function findBy($attribute, $value, $columns = ['*']);

    /**
     * Create new record
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $data);

    /**
     * Update record
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data);

    /**
     * Delete record
     *
     * @param int $id
     * @return bool
     */
    public function delete($id);

    /**
     * Get paginated results
     *
     * @param int $perPage
     * @param array $columns
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = 15, $columns = ['*']);

    /**
     * Count records
     *
     * @return int
     */
    public function count();
}