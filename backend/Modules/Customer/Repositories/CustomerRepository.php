<?php

namespace Modules\Customer\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Customer\Interfaces\CustomerRepositoryInterface;
use Modules\Customer\Entities\Customer;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    /**
     * CustomerRepository constructor.
     *
     * @param Customer $model
     */
    public function __construct(Customer $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmail($email)
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdentityDocument($document)
    {
        return $this->model->where('identity_document', $document)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getByType($type, $perPage = 15)
    {
        return $this->model->where('customer_type', $type)->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getBySegment($segment, $perPage = 15)
    {
        return $this->model->where('segment', $segment)->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function search($search, $perPage = 15)
    {
        return $this->applyCustomerSearch($this->model->query(), $search)->paginate($perPage);
    }

    public function searchSuggestions(string $search, int $limit = 8)
    {
        $search = trim($search);

        return $this->model
            ->select(['id', 'first_name', 'last_name', 'identity_document', 'phone', 'email'])
            ->where(function ($query) use ($search) {
                $this->addCustomerSearchConditions($query, $search);
            })
            ->orderByRaw(
                'CASE WHEN identity_document = ? OR phone = ? THEN 0 ELSE 1 END',
                [$search, $search]
            )
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(max(1, min($limit, 10)))
            ->get();
    }

    private function applyCustomerSearch($query, string $search)
    {
        $search = $this->normalizeSearchText($search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $this->addCustomerSearchConditions($q, $search);
        });
    }

    private function addCustomerSearchConditions($query, string $search): void
    {
        $search = $this->normalizeSearchText($search);
        $terms = $this->searchTerms($search);

        $query->where('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$search}%"])
            ->orWhereRaw("CONCAT_WS(' ', last_name, first_name) LIKE ?", ["%{$search}%"])
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('identity_document', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%");

        if (count($terms) > 1) {
            $query->orWhere(function ($termQuery) use ($terms) {
                foreach ($terms as $term) {
                    $termQuery->where(function ($q) use ($term) {
                        $q->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('identity_document', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
                }
            });
        }
    }

    private function normalizeSearchText(string $search): string
    {
        return trim(preg_replace('/\s+/', ' ', $search) ?? '');
    }

    private function searchTerms(string $search): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', $search) ?: [],
            fn ($term) => $term !== ''
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getWithFilters(array $filters, $perPage = 15)
    {
        $query = $this->model->query();

        // Eager Loading for Dashboard
        $query->with([
            'contracts' => function($q) {
                $q->with('plan');
            },
            'addresses',
            'invoices' => function($q) {
                $q->latest('created_at');
            }
        ]);

        if (isset($filters['type'])) {
            $query->where('customer_type', $filters['type']);
        }

        if (isset($filters['segment'])) {
            $query->where('segment', $filters['segment']);
        }

        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $this->applyCustomerSearch($query, $filters['search']);
        }

        if (isset($filters['plan']) && $filters['plan'] !== '') {
            $planId = $filters['plan'];
            $query->whereHas('contracts', function($q) use ($planId) {
                $q->where('contracts.plan_id', $planId)->where('contracts.status', 'active');
            });
        }

        if (isset($filters['zone']) && $filters['zone'] !== '') {
            $zoneName = $filters['zone'];
            $query->whereHas('addresses', function($q) use ($zoneName) {
                $q->where('addresses.city', $zoneName);
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = $filters['status'];
            if ($status === 'overdue') {
                // Clientes con al menos una factura vencida
                $query->whereHas('invoices', function($q) {
                    $q->where('invoices.status', 'overdue');
                });
            } elseif ($status === 'pending') {
                // Clientes con facturas pendientes/parciales pero NINGUNA vencida
                $query->whereHas('invoices', function($q) {
                    $q->whereIn('invoices.status', ['pending', 'partial']);
                })->whereDoesntHave('invoices', function($q) {
                    $q->where('invoices.status', 'overdue');
                });
            } elseif ($status === 'paid') {
                // Clientes sin deuda pendiente/vencida, y con al menos una factura pagada
                $query->whereDoesntHave('invoices', function($q) {
                    $q->whereIn('invoices.status', ['pending', 'partial', 'overdue']);
                })->whereHas('invoices', function($q) {
                    $q->where('invoices.status', 'paid');
                });
            }
        }

        if (isset($filters['customer_active']) && $filters['customer_active'] !== '') {
            $query->where('active', $filters['customer_active']);
        }

        if (isset($filters['contract_status']) && $filters['contract_status'] !== '') {
            $cStatus = $filters['contract_status'];
            if ($cStatus === 'active') {
                $query->whereHas('contracts', function ($q) {
                    $q->where('contracts.status', 'active');
                });
            } elseif ($cStatus === 'suspended') {
                $query->whereHas('contracts', function ($q) {
                    $q->where('contracts.status', 'suspended');
                })->whereDoesntHave('contracts', function ($q) {
                    $q->where('contracts.status', 'active');
                });
            } elseif ($cStatus === 'expired') {
                $query->whereHas('contracts', function ($q) {
                    $q->whereIn('contracts.status', ['expired', 'renewed']);
                })->whereDoesntHave('contracts', function ($q) {
                    $q->whereIn('contracts.status', ['active', 'suspended']);
                });
            } elseif ($cStatus === 'cancelled') {
                $query->whereHas('contracts', function ($q) {
                    $q->where('contracts.status', 'cancelled');
                })->whereDoesntHave('contracts', function ($q) {
                    $q->whereIn('contracts.status', ['active', 'suspended', 'expired', 'renewed']);
                });
            }
        }


        if (isset($filters['incomplete']) && $filters['incomplete'] !== '') {
            $incomplete = $filters['incomplete'];
            if ($incomplete === 'no_plan') {
                $query->whereDoesntHave('contracts', function ($q) {
                    $q->where('contracts.status', 'active');
                });
            } elseif ($incomplete === 'no_dni') {
                $query->where(function ($q) {
                    $q->whereNull('identity_document')
                      ->orWhere('identity_document', '');
                });
            } elseif ($incomplete === 'no_phone') {
                $query->where(function ($q) {
                    $q->whereNull('phone')
                      ->orWhere('phone', '');
                });
            }
        }

        if (isset($filters['date_from'])) {
            $query->where('registration_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('registration_date', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Obtener conteo de estadísticas para el dashboard superior
     */
    public function getDashboardStats()
    {
        $activeWithPlan = $this->model->whereHas('contracts', function($q) {
            $q->where('status', 'active');
        })->count();

        $withoutActivePlan = $this->model->whereDoesntHave('contracts', function($q) {
            $q->where('status', 'active');
        })->count();

        return [
            'active_with_plan' => $activeWithPlan,
            'without_active_plan' => $withoutActivePlan
        ];
    }
        public function getActiveCustomers()
    {
        return $this->model->where('active', true)->get();
    }
}
