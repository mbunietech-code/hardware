<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/** Customers and suppliers (both may be created offline on mobile). */
class PartyService
{
    use Concerns;

    public function createCustomer(array $data, User $user): Customer
    {
        return $this->create(Customer::class, $data, $user, 'customer');
    }

    public function createSupplier(array $data, User $user): Supplier
    {
        return $this->create(Supplier::class, $data, $user, 'supplier');
    }

    public function update(Customer|Supplier $party, array $data): Customer|Supplier
    {
        $data = Validator::make($data, $this->rules())->validate();
        $before = $party->toArray();
        $party->update($data);
        AuditLogger::log(strtolower(class_basename($party)).'.updated', $party, $before, $party->toArray());

        return $party;
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'local_uuid' => 'nullable|uuid',
        ];
    }

    private function create(string $class, array $data, User $user, string $label): Customer|Supplier
    {
        $data = Validator::make($data, $this->rules())->validate();
        if ($existing = $this->findByLocalUuid($class, $data)) {
            return $existing;
        }
        $party = $class::create($data + ['created_by' => $user->id]);
        AuditLogger::log($label.'.created', $party);

        return $party;
    }

    /** Find or create a customer by name/phone for credit sales typed in quickly. */
    public function quickCustomer(?string $name, ?string $phone, User $user): ?int
    {
        if (! $name) {
            return null;
        }
        $customer = Customer::where('name', $name)->when($phone, fn ($q) => $q->where('phone', $phone))->first()
            ?? $this->createCustomer(['name' => $name, 'phone' => $phone], $user);

        return $customer->id;
    }

    public function quickSupplier(?string $name, ?string $phone, User $user): ?int
    {
        if (! $name) {
            return null;
        }
        $supplier = Supplier::where('name', $name)->first()
            ?? $this->createSupplier(['name' => $name, 'phone' => $phone], $user);

        return $supplier->id;
    }
}
