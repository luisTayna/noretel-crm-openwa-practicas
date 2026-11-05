<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Entities\EmergencyContact;
use Modules\Customer\Entities\Customer;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Entities\AuditLog;
use Modules\Customer\Http\Requests\StoreEmergencyContactRequest;
use Modules\Customer\Http\Requests\UpdateEmergencyContactRequest;

class EmergencyContactController extends Controller
{
    /**
     * Store a newly created emergency contact in storage.
     * @param StoreEmergencyContactRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreEmergencyContactRequest $request)
    {
        $contactData = $request->validated();
        $customerId = $contactData['customer_id'];
        
        // Check if customer exists
        $customer = Customer::findOrFail($customerId);
        
        // Create the emergency contact
        $contact = new EmergencyContact($contactData);
        $contact->save();

        // Register audit
        AuditLog::register(
            Auth::id(),
            'emergency_contact_created',
            'customers',
            "Contacto de emergencia creado (ID: {$contact->id}) para cliente ID: {$customer->id}",
            $request->ip()
        );

        return redirect()->back()
            ->with('success', 'Contacto de emergencia agregado exitosamente.');
    }

    /**
     * Update the specified emergency contact in storage.
     * @param UpdateEmergencyContactRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateEmergencyContactRequest $request, $id)
    {
        $contact = EmergencyContact::findOrFail($id);
        $contactData = $request->validated();
        
        // Update the emergency contact
        $contact->update($contactData);

        // Register audit
        AuditLog::register(
            Auth::id(),
            'emergency_contact_updated',
            'customers',
            "Contacto de emergencia actualizado (ID: {$contact->id}) para cliente ID: {$contact->customer_id}",
            $request->ip()
        );

        return redirect()->back()
            ->with('success', 'Contacto de emergencia actualizado exitosamente.');
    }

    /**
     * Remove the specified emergency contact from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $contact = EmergencyContact::findOrFail($id);
        $contactId = $contact->id;
        $customerId = $contact->customer_id;

        // Delete the emergency contact
        $contact->delete();

        // Register audit
        AuditLog::register(
            Auth::id(),
            'emergency_contact_deleted',
            'customers',
            "Contacto de emergencia eliminado (ID: {$contactId}) para cliente ID: {$customerId}",
            $request->ip()
        );

        return redirect()->back()
            ->with('success', 'Contacto de emergencia eliminado exitosamente.');
    }
}