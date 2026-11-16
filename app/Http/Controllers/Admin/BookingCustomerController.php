<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingCustomerFormRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BookingCustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-bookings');
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $term = $validated['q'] ?? '';
        $customers = User::query()->where('role', 'customer')->when($term !== '', function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhereRaw("REPLACE(REPLACE(aadhaar, ' ', ''), '-', '') LIKE ?", ['%'.str_replace([' ', '-'], '', $term).'%']);
            });
        })->orderBy('name')->orderBy('id')->simplePaginate(20, ['id', 'name', 'aadhaar']);

        return response()->json([
            'results' => $customers->map(fn (User $customer): array => [
                'id' => $customer->id, 'text' => ($customer->aadhaar ?: 'Aadhaar unavailable').' — '.$customer->name,
            ])->all(),
            'pagination' => ['more' => $customers->hasMorePages()],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(BookingCustomerFormRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);
        $customer = DB::transaction(function () use ($request): User {
            // Public reservations hold this same lock until their customer and booking commit.
            DB::table('booking_code_sequences')->where('id', 1)->lockForUpdate()->first();

            if (User::query()
                ->whereRaw("REPLACE(REPLACE(aadhaar, ' ', ''), '-', '') = ?", [$request->validated('aadhaar')])
                ->lockForUpdate()->first(['id']) !== null) {
                throw ValidationException::withMessages([
                    'aadhaar' => 'A customer with this Aadhaar already exists. Search for the existing customer.',
                ]);
            }

            $customer = new User($request->safe()->only(['name', 'phone']));
            $customer->aadhaar = $request->validated('aadhaar');
            $customer->role = 'customer';
            $customer->save();

            return $customer;
        });

        return response()->json([
            'id' => $customer->id, 'text' => $customer->aadhaar.' — '.$customer->name,
        ], 201)->header('Cache-Control', 'private, no-store');
    }
}
