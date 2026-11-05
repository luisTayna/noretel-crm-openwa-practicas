<?php

namespace Modules\Customer\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\Customer\Entities\Customer;

class ExportHistoricalCustomerTemplateCommand extends Command
{
    protected $signature = 'customers:export-historical-template
        {source? : CSV original para exportar solo esos clientes}
        {--output=storage/app/historical_customers_template.csv : Ruta del CSV de salida}';

    protected $description = 'Exporta una plantilla con customer_id y columnas vacías para completar datos históricos.';

    public function handle(): int
    {
        $outputPath = $this->resolvePath((string) $this->option('output'));
        $sourceRows = $this->argument('source') ? $this->rowsFromSource($this->resolvePath((string) $this->argument('source'))) : [];

        $customers = $sourceRows !== []
            ? $this->customersFromSourceRows($sourceRows)
            : Customer::query()->orderBy('id')->get();

        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($outputPath, 'wb');
        if ($handle === false) {
            $this->error("No se pudo crear el archivo: {$outputPath}");
            return self::FAILURE;
        }

        fputcsv($handle, [
            'customer_id',
            'identity_document',
            'first_name',
            'last_name',
            'phone',
            'address',
            'plan_id',
            'monthly_price',
            'installed_at',
            'debt_start_date',
            'debt_end_month',
            'notes',
        ]);

        foreach ($customers as $customer) {
            fputcsv($handle, [
                $customer->id,
                $customer->identity_document,
                $customer->first_name,
                $customer->last_name,
                $customer->phone,
                optional($customer->addresses()->primary()->first())->street ?? '',
                '',
                '',
                '',
                '',
                '',
                '',
            ]);
        }

        fclose($handle);

        $this->info("Plantilla generada: {$outputPath}");
        $this->info("Clientes exportados: {$customers->count()}");

        return self::SUCCESS;
    }

    private function customersFromSourceRows(array $rows)
    {
        $customers = collect();
        $seenCompositeKeys = [];

        foreach ($rows as $row) {
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $document = trim((string) ($row['identity_document'] ?? ''));
            $type = mb_strtolower(trim((string) ($row['customer_type'] ?? '')));
            $isBusiness = in_array($type, ['business', 'company', 'corporate', 'empresa'], true)
                || preg_match('/^\d{11}$/', $document) === 1;

            if ($firstName === '' || (! $isBusiness && $lastName === '')) {
                continue;
            }

            $phone = trim((string) ($row['phone'] ?? ''));
            $compositeKey = $this->compositeKey($document, $firstName, $lastName, $phone);
            $seenCompositeKeys[$compositeKey] = ($seenCompositeKeys[$compositeKey] ?? 0) + 1;

            $customer = $this->findMatchingCustomer(
                $document,
                $firstName,
                $lastName,
                $phone,
                $seenCompositeKeys[$compositeKey]
            );

            if ($customer) {
                $customers->push($customer);
            }
        }

        return $customers;
    }

    private function findMatchingCustomer(string $document, string $firstName, string $lastName, string $phone, int $occurrence = 1): ?Customer
    {
        /** @var Builder $query */
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

    private function rowsFromSource(string $path): array
    {
        if (! is_file($path)) {
            $this->error("No existe el archivo CSV fuente: {$path}");
            return [];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $headers = array_map(fn ($header) => trim(str_replace(["\xEF\xBB\xBF", "\u{FEFF}"], '', (string) $header), " \t\n\r\0\x0B\""), $headers);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? null;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
