<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForcedPasswordChangeRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ForcedPasswordChangeController extends Controller
{
    public function edit()
    {
        return view('auth.force-change-password');
    }

    public function update(ForcedPasswordChangeRequest $request)
    {
        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Temporary password is incorrect.',
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->remember_token = Str::random(60);
        $user->save();

        activity('user')
            ->causedBy($user)
            ->performedOn($user)
            ->event('forced_password_changed')
            ->withProperties([
                'action' => 'update',
                'email' => $user->email,
            ])
            ->log('User changed password after password reset');

        return redirect()
            ->route('home')
            ->with('message', 'Password changed successfully.');
    }
}
