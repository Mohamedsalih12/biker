<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAssurance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserPasswordResetController extends Controller
{
    public function store(User $user)
    {
        $this->authorize('manage-users');

        $currentUser = Auth::user();

        abort_unless($currentUser !== null, 403);
        abort_if($currentUser->id === $user->id, 403, 'You cannot reset your own password from this page.');

        // $temporaryPassword = Str::random(8);
        $temporaryPassword = 'T@'.random_int(100, 999).Str::upper(Str::random(3));

        DB::transaction(function () use ($user, $temporaryPassword, $currentUser) {
            $user->password = Hash::make($temporaryPassword);
            $user->must_change_password = true;
            $user->password_reset_at = now();
            $user->password_reset_by = $currentUser->id;
            $user->remember_token = Str::random(60);
            $user->save();

            // If session driver is database, log out existing sessions for this user
            if (config('session.driver') === 'database') {
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->delete();
            }

            activity('user')
                ->causedBy($currentUser)
                ->performedOn($user)
                ->event('password_reset')
                ->withProperties([
                    'target_user_id' => $user->id,
                    'target_email' => $user->email,
                ])
                ->log('User password reset');
        });

        return redirect()
            ->route('xpress.users.index')
            ->with('reset_password_once', [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'temporary_password' => $temporaryPassword,
            ]);
    }

    public function reset2fa(User $user, Request $request, TwoFactorAssurance $twoFactorAssurance): RedirectResponse
    {
        $this->authorize('manage-users');

        $currentUser = Auth::user();

        abort_unless($currentUser !== null, 403);

        $twoFactorAssurance->ensureVerified($request);

        DB::table('users')
            ->where('id', $user->id)
            ->update(['google2fa_secret' => null]);

        activity('user')
            ->causedBy($currentUser)
            ->performedOn($user)
            ->event('reset2fa')
            ->withProperties([
                'target_user_id' => $user->id,
                'target_email' => $user->email,
            ])
            ->log('User 2FA reset');

        return redirect()
            ->route('xpress.users.index')
            ->with('reset2fa', [
                'user_name' => $user->name,
                'user_email' => $user->email,
            ]);
    }
}
