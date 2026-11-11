<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserFormRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(
        protected AuditService $audit
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query();

        $users = $query->paginate(15);
        $firstRecord = User::first();

        activity('user')
            ->causedBy(Auth::user())
            ->when($firstRecord, fn ($log) => $log->performedOn($firstRecord))
            ->event('view')
            ->withProperties([
                'action' => 'view',
            ])
            ->log('Users viewed');

        return view('admin.users.index', compact('users'));
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

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

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

        $user->name = $request->name;
        $user->email = $validated['email'];
        $user->active_status = $request->active_status;

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

        return redirect('/admin/users')->with('message', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect('/admin/users')->with('message', 'User deleted successfully.');
    }
}
