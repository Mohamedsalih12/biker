<?php

namespace Tests\Feature;

use App\Http\Requests\BookingCustomerFormRequest;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingCustomerTest extends TestCase
{
    public function test_quick_add_creates_and_returns_a_customer_without_credentials_or_privilege_escalation(): void
    {
        $response = $this->actingAsUser()->postJson('/admin/booking-customers', [
            'name' => 'New Customer', 'phone' => '9876543210', 'aadhaar' => '1234 5678 9012',
            'role' => 'admin', 'active_status' => false, 'password' => 'Injected',
        ])->assertCreated();
        $customer = User::query()->findOrFail($response->json('id'));

        $response->assertExactJson(['id' => $customer->id, 'text' => '123456789012 — New Customer']);
        $this->assertSame('customer', $customer->role);
        $this->assertSame('123456789012', $customer->aadhaar);
        $this->assertNull($customer->password);
        $this->assertNull($customer->email);
        $this->assertTrue($customer->active_status === 1 || $customer->active_status === true);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'user', 'event' => 'created', 'subject_id' => $customer->id]);
    }

    public function test_duplicate_aadhaar_is_rejected_without_creating_another_customer(): void
    {
        User::factory()->create(['aadhaar' => '123456789012']);
        $count = User::query()->count();
        $this->actingAsUser()->postJson('/admin/booking-customers', ['name' => 'Duplicate', 'phone' => '9876543210', 'aadhaar' => '1234 5678 9012'])
            ->assertUnprocessable()->assertJsonValidationErrors('aadhaar');
        $this->assertDatabaseCount('users', $count);
    }

    public function test_customer_created_after_validation_is_not_duplicated_by_staff(): void
    {
        $count = User::query()->count();
        app()->afterResolving(BookingCustomerFormRequest::class, function (): void {
            User::factory()->create(['aadhaar' => '123456789012', 'name' => 'Concurrent Customer']);
        });

        $this->actingAsUser()->postJson('/admin/booking-customers', [
            'name' => 'Duplicate', 'phone' => '9876543210', 'aadhaar' => '1234 5678 9012',
        ])->assertUnprocessable()->assertJsonValidationErrors('aadhaar');

        $this->assertDatabaseCount('users', $count + 1);
        $this->assertDatabaseHas('users', ['aadhaar' => '123456789012', 'name' => 'Concurrent Customer']);
    }

    #[DataProvider('invalidValues')]
    public function test_quick_add_validates_customer_fields(string $field, mixed $value): void
    {
        $count = User::query()->count();
        $this->actingAsUser()->postJson('/admin/booking-customers', array_replace([
            'name' => 'Customer', 'phone' => '9876543210', 'aadhaar' => '123456789012',
        ], [$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('users', $count);
    }

    public static function invalidValues(): array
    {
        return [
            'name required' => ['name', ''], 'name length' => ['name', str_repeat('x', 256)],
            'phone required' => ['phone', ''], 'phone length' => ['phone', str_repeat('1', 31)],
            'aadhaar required' => ['aadhaar', ''], 'aadhaar length' => ['aadhaar', '123'],
            'aadhaar digits' => ['aadhaar', 'abcdefghijkl'], 'aadhaar array' => ['aadhaar', ['123456789012']],
        ];
    }

    public function test_quick_add_and_customer_lookup_are_admin_only(): void
    {
        $this->getJson('/admin/booking-customers')->assertUnauthorized();
        $this->postJson('/admin/booking-customers', [])->assertUnauthorized();
        foreach (['staff', 'customer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->getJson('/admin/booking-customers')->assertForbidden();
            $this->postJson('/admin/booking-customers', [])->assertForbidden();
        }
    }

    public function test_customer_lookup_searches_aadhaar_and_names_without_exposing_credentials_or_staff(): void
    {
        $customer = User::factory()->create(['name' => 'Lookup Customer', 'role' => 'customer', 'aadhaar' => '123456789012']);
        User::factory()->admin()->create(['name' => 'Lookup Admin', 'aadhaar' => '987654321012']);
        foreach (['1234', 'Lookup', '1234 5678 9012', '1234-5678-9012'] as $term) {
            $this->actingAsUser()->getJson('/admin/booking-customers?q='.urlencode($term))->assertExactJson([
                'results' => [['id' => $customer->id, 'text' => '123456789012 — Lookup Customer']], 'pagination' => ['more' => false],
            ]);
        }
    }

    public function test_customer_lookup_paginates_results(): void
    {
        User::factory()->count(21)->create(['role' => 'customer']);
        $this->actingAsUser()->getJson('/admin/booking-customers')->assertJsonCount(20, 'results')->assertJsonPath('pagination.more', true);
        $this->getJson('/admin/booking-customers?page=2')->assertJsonCount(2, 'results')->assertJsonPath('pagination.more', false);
    }
}
