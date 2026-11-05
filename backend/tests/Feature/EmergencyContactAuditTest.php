<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Entities\AuditLog;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Entities\EmergencyContact;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmergencyContactAuditTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new Customer([
            'customer_type' => 'individual',
            'first_name' => 'Luis',
            'last_name' => 'Fernandez',
            'identity_document' => 'DOC-EC-1',
            'active' => true,
        ]);
        $this->customer->save();
    }

    public function test_creating_an_emergency_contact_records_exactly_one_audit_row(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->post(route('customer.emergencyContacts.store'), [
            'customer_id' => $this->customer->id,
            'name' => 'Maria Fernandez',
            'relationship' => 'Hermana',
            'phone' => '987654321',
        ]);

        $response->assertRedirect();
        $contact = EmergencyContact::where('customer_id', $this->customer->id)->firstOrFail();

        $this->assertSame(1, AuditLog::where('action_type', 'emergency_contact_created')->count());
        $log = AuditLog::where('action_type', 'emergency_contact_created')->first();
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringContainsString((string) $this->customer->id, $log->action_detail);
        $this->assertStringContainsString((string) $contact->id, $log->action_detail);
        $this->assertStringNotContainsString('987654321', $log->action_detail);
    }

    public function test_updating_an_emergency_contact_records_exactly_one_audit_row(): void
    {
        $user = $this->adminUser();
        $contact = $this->createContact();

        $response = $this->actingAs($user)->put(route('customer.emergencyContacts.update', $contact->id), [
            'name' => 'Maria Fernandez Updated',
            'relationship' => 'Hermana',
            'phone' => '999999999',
        ]);

        $response->assertRedirect();

        $this->assertSame(1, AuditLog::where('action_type', 'emergency_contact_updated')->count());
        $log = AuditLog::where('action_type', 'emergency_contact_updated')->first();
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringContainsString((string) $this->customer->id, $log->action_detail);
        $this->assertStringContainsString((string) $contact->id, $log->action_detail);
        $this->assertStringNotContainsString('999999999', $log->action_detail);
    }

    public function test_deleting_an_emergency_contact_records_exactly_one_audit_row(): void
    {
        $user = $this->adminUser();
        $contact = $this->createContact();
        $contactId = $contact->id;

        $response = $this->actingAs($user)->delete(route('customer.emergencyContacts.destroy', $contact->id));

        $response->assertRedirect();

        $this->assertSame(1, AuditLog::where('action_type', 'emergency_contact_deleted')->count());
        $log = AuditLog::where('action_type', 'emergency_contact_deleted')->first();
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringContainsString((string) $this->customer->id, $log->action_detail);
        $this->assertStringContainsString((string) $contactId, $log->action_detail);
    }

    private function createContact(): EmergencyContact
    {
        $contact = new EmergencyContact([
            'customer_id' => $this->customer->id,
            'name' => 'Maria Fernandez',
            'relationship' => 'Hermana',
            'phone' => '987654321',
        ]);
        $contact->save();

        return $contact;
    }

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'ec_admin_' . uniqid(),
            'email' => 'ec_admin_' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }
}
