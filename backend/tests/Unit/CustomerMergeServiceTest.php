<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Services\CustomerMergeService;
use Tests\TestCase;

class CustomerMergeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('customer_type')->default('individual');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('identity_document')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->string('customer_status')->default('active');
            $table->unsignedInteger('contract_count')->default(0);
            $table->unsignedInteger('months_as_customer')->default(0);
            $table->timestamp('registration_date')->nullable();
            $table->timestamps();
        });
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('action_date')->nullable();
            $table->string('action_type');
            $table->string('module');
            $table->text('action_detail');
            $table->string('source_ip')->nullable();
            $table->text('previous_data')->nullable();
            $table->text('new_data')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('customers');

        parent::tearDown();
    }

    public function test_merge_moves_contracts_and_deletes_only_the_duplicate_customer_record(): void
    {
        $target = Customer::create([
            'first_name' => 'Rolando',
            'last_name' => 'Huitoccollo',
            'identity_document' => '43767709',
            'phone' => '979381328',
            'active' => true,
            'customer_status' => 'active',
        ]);
        $source = Customer::create([
            'first_name' => 'Rolando',
            'last_name' => 'Huitoccollo Ttito',
            'identity_document' => '43767709',
            'phone' => '979381328',
            'active' => true,
            'customer_status' => 'active',
        ]);
        DB::table('contracts')->insert([
            ['id' => 284, 'customer_id' => $target->id, 'status' => 'active'],
            ['id' => 409, 'customer_id' => $source->id, 'status' => 'active'],
        ]);

        $result = app(CustomerMergeService::class)->merge($target, [$source->id], 1, '127.0.0.1');

        $this->assertNull(Customer::find($source->id));
        $this->assertSame(2, DB::table('contracts')->where('customer_id', $target->id)->count());
        $this->assertSame(2, $result['target']->contract_count);
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'customers_merged',
            'module' => 'customer',
        ]);
    }

    public function test_preview_blocks_different_identity_documents(): void
    {
        $target = Customer::create([
            'first_name' => 'Persona',
            'identity_document' => '11111111',
        ]);
        $source = Customer::create([
            'first_name' => 'Otra',
            'identity_document' => '22222222',
        ]);

        $preview = app(CustomerMergeService::class)->preview($target, [$source->id]);

        $this->assertFalse($preview['can_merge']);
        $this->assertStringContainsString('DNI/RUC diferentes', implode(' ', $preview['blockers']));
    }
}
