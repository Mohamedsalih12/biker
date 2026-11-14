<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserFormRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        protected AuditService $audit
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $firstRecord = User::first();

        activity('user')
            ->causedBy(Auth::user())
            ->when($firstRecord, fn ($log) => $log->performedOn($firstRecord))
            ->event('view')
            ->withProperties([
                'action' => 'view',
            ])
            ->log('Users viewed');

        return view('admin.users.index');
    }

    public function userdetails(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        return redirect()->route('xpress.users.index');
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('admin.users.create');
    }

    public function store(UserFormRequest $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();
        $user = new User;
        $user->fill($request->safe()->only(['name', 'email', 'phone', 'address']));
        $user->role = $validated['role'] ?? 'customer';
        $user->password = $validated['password'] ?? null;
        $user->save();

        return redirect()->route('xpress.users.edit', $user)
            ->with('message', 'User account created successfully.');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', compact('user'));
    }

    public function update(UserFormRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        $user->fill($request->safe()->only(['name', 'email', 'phone', 'address']));
        $user->role = $validated['role'] ?? $user->role;
        $user->active_status = $validated['active_status'] ?? $user->active_status;

        if ($request->password) {
            $user->password = Hash::make($request->password);
            $user->remember_token = Str::random(60);

            DB::transaction(function () use ($user): void {
                $user->save();

                if (config('session.driver') === 'database') {
                    DB::table('sessions')
                        ->where('user_id', $user->id)
                        ->delete();
                }
            });
        } else {
            $user->save();
        }

        return redirect()->route('xpress.users.index')->with('message', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        try {
            DB::transaction(function () use ($user): void {
                if ($user->delete() !== true) {
                    throw ValidationException::withMessages(['user' => 'This user could not be deleted.']);
                }
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['user' => 'This user is linked to bookings or other records and cannot be deleted.']);
        }

        return redirect('/admin/users')->with('message', 'User deleted successfully.');
    }
}
