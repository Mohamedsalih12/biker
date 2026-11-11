<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateAdminProfileRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function edit()
    {
        // $this->authorize('isAdmin');

        return view('admin.users.profile');
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['name']));

        return redirect()->back()->with('message', 'User profile updated successfully.');
    }

    public function editProfileAdmin(User $user): View
    {
        $this->authorize('isAdmin');

        return view('admin.users.profile', compact('user'));
    }

    public function updateProfileAdmin(User $user, UpdateAdminProfileRequest $request): RedirectResponse
    {
        $this->authorize('isAdmin');

        $user->update($request->safe()->only(['name']));

        return redirect()->route('xpress.users.index')
            ->with('message', 'User profile updated successfully.');
    }

    public function changePasswordIndex()
    {
        return view('admin.users.change-password');
    }

    public function changePassword(ChangePasswordRequest $request)
    {

        /** @var User|null $user */
        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->password = Hash::make($request->new_password);
        $user->remember_token = Str::random(60);
        $user->save();

        /** @var User|null $currentUser */
        $currentUser = Auth::user();

        activity('password')
            ->causedBy($currentUser)
            ->performedOn(User::first())
            ->event('password_changed')
            ->withProperties([
                'action' => 'update',
                'email' => $user->email,
            ])
            ->log('User Password Changed');

        return redirect()->back()->with('message', 'Password changed successfully.');
    }
}
