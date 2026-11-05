<?php

namespace Modules\Core\Repositories;

use Modules\Core\Entities\User;
use Illuminate\Support\Facades\Hash;

class UserRepository extends BaseRepository
{
    /**
     * UserRepository constructor.
     *
     * @param User $model
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Create a new user with password hashing
     *
     * @param array $data
     * @return User
     */
    public function create(array $data)
    {
        // Asegurarse de que la contraseña se hashea
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return parent::create($data);
    }

    /**
     * Update user with password hashing if password is provided
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data)
    {
        // Hashear la contraseña solo si se proporciona una nueva
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return parent::update($id, $data);
    }

    /**
     * Find user by username
     *
     * @param string $username
     * @return User|null
     */
    public function findByUsername($username)
    {
        return $this->model->where('username', $username)->first();
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail($email)
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Find users by role
     *
     * @param string $roleName
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByRole($roleName)
    {
        return $this->model->whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->get();
    }
    
    /**
     * Get users with their roles
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUsersWithRoles()
    {
        return $this->model->with('roles')->get();
    }

    /**
     * Get active users
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveUsers()
    {
        return $this->model->where('status', 'active')->get();
    }
}