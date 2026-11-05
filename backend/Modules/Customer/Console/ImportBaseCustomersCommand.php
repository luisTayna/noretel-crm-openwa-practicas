<?php

namespace Modules\Customer\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Customer\Entities\Address;
use Modules\Customer\Entities\Customer;

class ImportBaseCustomersCommand extends Command
{
    protected $signature = 'customers:import-base
        {file : Ruta del CSV de clientes base}
        {--dry-run : Simula la importación sin guardar cambios}';

    protected $description = 'Importa clientes base desde CSV sin asumir que el DNI es único.';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));

        if (! is_file($path)) {
            $this->error("No existe el archivo CSV: {$path}");
            return self::FAILURE;
        }

        [$headers, $rows] = $this->readCsv($path);
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'created' => 0,
            'updated' => 0,
            'addresses_created' => 0,
            'addresses_existing' => 0,
            'blank_dni_imported' => 0,
            'duplicate_dni_allowed' => 0,
            'skipped_invalid_name' => 0,
            'failed' => 0,
        ];

        $duplicateDocuments = $this->duplicateDocuments($rows);
        $seenCompositeKeys = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $document = $this->clean($row['identity_document'] ?? '');
            $firstName = $this->clean($row['first_name'] ?? '');
            $lastName = $this->clean($row['last_name'] ?? '');
            $phone = $this->clean($row['phone'] ?? '');
            $address = $this->clean($row['address'] ?? '');
            $isBusiness = $this->customerType($row, $document) === 'business';

            if ($firstName === '' || (! $isBusiness && $lastName === '')) {
                $stats['skipped_invalid_name']++;
                $errors[] = "Línea {$line}: nombre o apellido vacío.";
                continue;
            }

            if ($document === '') {
                $stats['blank_dni_imported']++;
            } elseif (isset($duplicateDocuments[$document])) {
                $stats['duplicate_dni_allowed']++;
            }

            $payload = $this->customerPayload($row, $firstName, $lastName, $document);
            $compositeKey = $this->compositeKey($document, $firstName, $lastName, $phone);
            $seenCompositeKeys[$compositeKey] = ($seenCompositeKeys[$compositeKey] ?? 0) + 1;
            $customer = $this->findMatchingCustomer($document, $firstName, $lastName, $phone, $seenCompositeKeys[$compositeKey]);

            if ($dryRun) {
                $customer ? $stats['updated']++ : $stats['created']++;
                if ($address !== '') {
                    $customer && $this->addressExists($customer, $address)
                        ? $stats['addresses_existing']++
                        : $stats['addresses_created']++;
                }
                continue;
            }

            try {
                if ($customer) {
                    $customer->fill($this->removeEmptyOptionalValues($payload));
                    $customer->save();
                    $stats['updated']++;
                } else {
                    $customer = Customer::create($payload);
                    $stats['created']++;
                }

                if ($address !== '') {
                    if ($this->addressExists($customer, $address)) {
                        $stats['addresses_existing']++;
                    } else {
                        Address::create([
                            'customer_id' => $customer->id,
                            'address_type' => 'installation',
                            'street' => $address,
                            'city' => 'Cusco',
                            'state' => 'Cusco',
                            'is_primary' => ! $customer->addresses()->exists(),
                        ]);
                        $stats['addresses_created']++;
                    }
                }
            } catch (\Throwable $exception) {
                $stats['failed']++;
                $label = $document !== '' ? "DNI {$document}" : trim("{$firstName} {$lastName}");
                $errors[] = "Línea {$line} {$label}: {$exception->getMessage()}";
            }
        }

        $this->line($dryRun ? 'Resultado de simulación:' : 'Resultado de importación:');
        $this->table(['Métrica', 'Cantidad'], collect($stats)->map(fn ($value, $key) => [$key, $value])->all());

        if ($duplicateDocuments !== []) {
            $this->warn('DNIs repetidos detectados, pero permitidos. No se usan como llave única:');
            foreach ($duplicateDocuments as $document => $count) {
                $this->line("- {$document}: {$count} filas");
            }
        }

        if ($errors !== []) {
            $this->warn('Observaciones:');
            foreach (array_slice($errors, 0, 20) as $error) {
                $this->line("- {$error}");
            }
            if (count($errors) > 20) {
                $this->line('- ... ' . (count($errors) - 20) . ' observaciones adicionales.');
            }
        }

        if ($dryRun) {
            $this->info('No se guardó nada porque usaste --dry-run.');
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function findMatchingCustomer(string $document, string $firstName, string $lastName, string $phone, int $occurrence = 1): ?Customer
    {
        $query = Customer::query()
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)]);

        if ($document !== '') {
            $query->where('identity_document', $document);
        } else {
            $query->whereNull('identity_document');
        }

        if ($phone !== '') {
            $query->where('phone', $phone);
        } else {
            $query->where(function ($phoneQuery) {
                $phoneQuery->whereNull('phone')->orWhere('phone', '');
            });
        }

        return $query->orderBy('id')->skip(max(0, $occurrence - 1))->first();
    }

    private function compositeKey(string $document, string $firstName, string $lastName, string $phone): string
    {
        return implode('|', [
            mb_strtolower($document),
            mb_strtolower($firstName),
            mb_strtolower($lastName),
            $phone,
        ]);
    }

    private function addressExists(Customer $customer, string $address): bool
    {
        return $customer->addresses()
            ->where('street', $address)
            ->where('city', 'Cusco')
            ->where('state', 'Cusco')
            ->exists();
    }

    private function customerPayload(array $row, string $firstName, string $lastName, string $document): array
    {
        return [
            'customer_type' => $this->customerType($row, $document),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'identity_document' => $document === '' ? null : $document,
            'email' => $this->nullable($row['email'] ?? null),
            'phone' => $this->nullable($row['phone'] ?? null),
            'credit_score' => $this->nullableInteger($row['credit_score'] ?? null),
            'contact_preferences' => $this->nullable($row['contact_preferences'] ?? null),
            'segment' => $this->nullable($row['segment'] ?? null),
            'registration_date' => $this->nullableDate($row['registration_date'] ?? null),
            'active' => $this->booleanValue($row['active'] ?? true),
            'customer_status' => $this->clean($row['customer_status'] ?? '') ?: 'active',
            'acquisition_channel' => $this->nullable($row['acquisition_channel'] ?? null),
            'utm_source' => $this->nullable($row['utm_source'] ?? null),
            'utm_campaign' => $this->nullable($row['utm_campaign'] ?? null),
        ];
    }

    private function removeEmptyOptionalValues(array $payload): array
    {
        foreach (['identity_document', 'email', 'phone', 'credit_score', 'contact_preferences', 'segment', 'registration_date', 'acquisition_channel', 'utm_source', 'utm_campaign'] as $key) {
            if ($payload[$key] === null || $payload[$key] === '') {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    private function duplicateDocuments(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $document = $this->clean($row['identity_document'] ?? '');
            if ($document === '') {
                continue;
            }
            $counts[$document] = ($counts[$document] ?? 0) + 1;
        }

        return array_filter($counts, fn (int $count) => $count > 1);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("No se pudo abrir el archivo {$path}.");
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('El CSV está vacío.');
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return [$headers, $rows];
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    private function normalizeHeader(string $header): string
    {
        return trim(str_replace(["\xEF\xBB\xBF", "\u{FEFF}"], '', $header), " \t\n\r\0\x0B\"");
    }

    private function clean(?string $value): string
    {
        return trim((string) $value);
    }

    private function nullable(?string $value): ?string
    {
        $value = $this->clean($value);
        return $value === '' ? null : $value;
    }

    private function nullableInteger(?string $value): ?int
    {
        $value = $this->clean($value);
        return $value === '' ? null : (int) $value;
    }

    private function nullableDate(?string $value): ?Carbon
    {
        $value = $this->clean($value);
        return $value === '' ? null : Carbon::parse($value);
    }

    private function booleanValue(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'si', 'sí', 'active'], true);
    }

    private function customerType(array $row, string $document): string
    {
        $type = mb_strtolower($this->clean($row['customer_type'] ?? ''));

        if (in_array($type, ['business', 'company', 'corporate', 'empresa'], true)) {
            return 'business';
        }

        if (preg_match('/^\d{11}$/', $document) === 1) {
            return 'business';
        }

        return 'individual';
    }
}
