<?php

namespace Modules\Core\Repositories;

use Modules\Core\Entities\Employee;

class EmployeeRepository
{
    /**
     * Get all employees
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all()
    {
        return Employee::orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Find employees by position.
     *
     * This is a descriptive employee field, not a permission/assignment rule.
     *
     * @param string $position
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByPosition($position)
    {
        return Employee::query()
            ->where(function ($query) use ($position) {
                $query->where('position', $position)
                    ->orWhere('position', 'like', "%{$position}%");
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Find active employees assignable as installation technicians.
     *
     * The technician role is the source of truth for installation assignment.
     * The employee position remains descriptive only.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findAssignableTechnicians()
    {
        return Employee::query()
            ->where('status', 'active')
            ->whereHas('user', function ($userQuery) {
                $userQuery->where('status', 'active')
                    ->whereHas('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'technician');
                    });
            })
            ->with(['user.roles'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Find an employee by ID
     *
     * @param int $id
     * @return User
     */
    public function find($id)
    {
        return Employee::findOrFail($id);
    }

    /**
     * Get active employees
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActive()
    {
        return Employee::where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Get employees by department
     *
     * @param string $department
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByDepartment($department)
    {
        return Employee::where('department', $department)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Search employees
     *
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function search($search)
    {
        return Employee::where(function ($query) use ($search) {
            $query->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%")
                          ->orWhere('username', 'like', "%{$search}%");
                  });
        })->orderBy('first_name')->orderBy('last_name')->get();
    }

    /**
     * Create a new employee
     *
     * @param array $data
     * @return User
     */
    public function create(array $data)
    {
        return Employee::create($data);
    }

    /**
     * Update an employee
     *
     * @param int $id
     * @param array $data
     * @return User
     */
    public function update($id, array $data)
    {
        $employee = $this->find($id);
        $employee->update($data);
        return $employee;
    }

    /**
     * Delete an employee
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $employee = $this->find($id);
        return $employee->delete();
    }

    /**
     * Get query builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Employee::query();
    }
}
