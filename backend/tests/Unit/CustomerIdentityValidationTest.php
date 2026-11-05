<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;
use Modules\Customer\Http\Requests\StoreCustomerRequest;
use Modules\Customer\Http\Requests\UpdateCustomerRequest;
use Tests\TestCase;

class CustomerIdentityValidationTest extends TestCase
{
    public function test_store_requires_identity_document_to_be_unique(): void
    {
        $request = StoreCustomerRequest::create('/', 'POST', [
            'customer_type' => 'individual',
        ]);

        $this->assertTrue(collect($request->rules()['identity_document'])
            ->contains(static fn ($rule) => $rule instanceof Unique));
    }

    public function test_update_requires_identity_document_to_remain_unique(): void
    {
        $request = UpdateCustomerRequest::create('/', 'PUT', [
            'customer_type' => 'individual',
        ]);

        $this->assertTrue(collect($request->rules()['identity_document'])
            ->contains(static fn ($rule) => $rule instanceof Unique));
    }

    /**
     * @dataProvider storeIdentityDocuments
     */
    public function test_store_validates_identity_document_by_customer_type(
        string $customerType,
        string $document,
        bool $expectedToPass
    ): void {
        $request = StoreCustomerRequest::create('/', 'POST', [
            'customer_type' => $customerType,
        ]);
        $rules = $request->rules();

        $identityRules = array_values(array_filter(
            $rules['identity_document'],
            static fn ($rule) => !$rule instanceof Unique
        ));

        $validator = Validator::make(
            ['customer_type' => $customerType, 'identity_document' => $document],
            ['customer_type' => $rules['customer_type'], 'identity_document' => $identityRules]
        );

        $this->assertSame($expectedToPass, $validator->passes());
    }

    /**
     * @dataProvider updateIdentityDocuments
     */
    public function test_update_validates_identity_document_using_payload_or_stored_type(
        ?string $payloadType,
        string $storedType,
        string $document,
        bool $expectedToPass
    ): void {
        $request = new class($storedType) extends UpdateCustomerRequest {
            public function __construct(private readonly string $storedType)
            {
                parent::__construct();
            }

            protected function customerTypeForIdentityValidation(): ?string
            {
                return $this->input('customer_type') ?: $this->storedType;
            }
        };
        $data = array_filter([
            'customer_type' => $payloadType,
            'identity_document' => $document,
        ], static fn ($value) => $value !== null);
        $request->merge($data);
        $rules = $request->rules();

        $identityRules = array_values(array_filter(
            $rules['identity_document'],
            static fn ($rule) => !$rule instanceof Unique
        ));

        $validator = Validator::make(
            $data,
            ['customer_type' => $rules['customer_type'], 'identity_document' => $identityRules]
        );

        $this->assertSame($expectedToPass, $validator->passes());
    }

    public static function storeIdentityDocuments(): array
    {
        return [
            ['individual', '12345678', true],
            ['individual', '1234567', false],
            ['individual', '123456789', false],
            ['individual', '12345678901', false],
            ['business', '12345678901', true],
            ['business', '12345678', false],
            ['business', '1234567890', false],
            ['business', '123456789012', false],
        ];
    }

    public static function updateIdentityDocuments(): array
    {
        return [
            ['individual', 'business', '12345678', true],
            ['individual', 'business', '12345678901', false],
            ['business', 'individual', '12345678901', true],
            ['business', 'individual', '12345678', false],
            [null, 'individual', '12345678', true],
            [null, 'individual', '12345678901', false],
            [null, 'business', '12345678901', true],
            [null, 'business', '12345678', false],
        ];
    }
}
