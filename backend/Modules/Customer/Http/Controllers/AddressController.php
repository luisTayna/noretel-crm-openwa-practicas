<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Entities\Address;
use Modules\Customer\Entities\Customer;
use Illuminate\Support\Facades\Auth;
use Modules\Customer\Http\Requests\StoreAddressRequest;
use Modules\Customer\Http\Requests\UpdateAddressRequest;

class AddressController extends Controller
{
    /**
     * Store a newly created address in storage.
     * @param StoreAddressRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAddressRequest $request)
    {
        $addressData = $request->validated();
        $customerId = $addressData['customer_id'];
        
        // Check if customer exists
        $customer = Customer::findOrFail($customerId);
        
        // Check if address is marked as primary
        if (isset($addressData['is_primary']) && $addressData['is_primary']) {
            // Set all other addresses as non-primary
            Address::where('customer_id', $customerId)
                  ->update(['is_primary' => false]);
        } else {
            // If no primary address exists yet, set this one as primary
            $primaryExists = Address::where('customer_id', $customerId)
                                   ->where('is_primary', true)
                                   ->exists();
            
            if (!$primaryExists) {
                $addressData['is_primary'] = true;
            }
        }
        
        // Create the address
        $address = new Address($addressData);
        $address->save();
        
        // Log the action
        // Assuming you have an AuditLog service similar to customer service
        // $this->auditService->log(
        //    'address_create',
        //    $address->id,
        //    Auth::id(),
        //    $request->ip()
        // );
        
        return redirect()->back()
            ->with('success', 'Dirección agregada exitosamente.');
    }

    /**
     * Update the specified address in storage.
     * @param UpdateAddressRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAddressRequest $request, $id)
    {
        $address = Address::findOrFail($id);
        $addressData = $request->validated();
        
        // Check if address is being set as primary
        if (isset($addressData['is_primary']) && $addressData['is_primary']) {
            // Set all other addresses as non-primary
            Address::where('customer_id', $address->customer_id)
                  ->where('id', '!=', $id)
                  ->update(['is_primary' => false]);
        } else {
            // Don't allow removing primary status if it's the only address
            // or if this address is currently primary
            $isPrimary = Address::where('id', $id)
                               ->where('is_primary', true)
                               ->exists();
            
            if ($isPrimary) {
                // Check if there are other addresses
                $otherAddressExists = Address::where('customer_id', $address->customer_id)
                                           ->where('id', '!=', $id)
                                           ->exists();
                
                if (!$otherAddressExists) {
                    // This is the only address, it must remain primary
                    $addressData['is_primary'] = true;
                }
            }
        }
        
        // Update the address
        $address->update($addressData);
        
        // Log the action
        // $this->auditService->log(
        //    'address_update',
        //    $address->id,
        //    Auth::id(),
        //    $request->ip()
        // );
        
        return redirect()->back()
            ->with('success', 'Dirección actualizada exitosamente.');
    }

    /**
     * Remove the specified address from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $address = Address::findOrFail($id);
        
        // Don't allow deleting a primary address
        if ($address->is_primary) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar la dirección primaria. Establezca otra dirección como primaria primero.');
        }
        
        // Check if this is the only address
        $addressCount = Address::where('customer_id', $address->customer_id)->count();
        if ($addressCount <= 1) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar la única dirección del cliente.');
        }
        
        // Delete the address
        $address->delete();
        
        // Log the action
        // $this->auditService->log(
        //    'address_delete',
        //    $address->id,
        //    Auth::id(),
        //    $request->ip()
        // );
        
        return redirect()->back()
            ->with('success', 'Dirección eliminada exitosamente.');
    }
}