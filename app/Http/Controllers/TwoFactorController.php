<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisableTwoFactorRequest;
use App\Http\Requests\EnableTwoFactorRequest;
use App\Models\User;
use App\Services\TwoFactorAssurance;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorAssurance $twoFactorAssurance) {}

    public function setup(?User $user = null): View|RedirectResponse
    {
        $currentUser = Auth::user();

        if (! $currentUser) {
            return redirect()->route('login');
        }

        $targetUser = $user ?? $currentUser;

        if ($targetUser->id !== $currentUser->id) {
            $this->authorize('manage-users');
            $this->twoFactorAssurance->ensureVerified(request());
        }

        if (filled($targetUser->google2fa_secret)) {
            session()->forget(['2fa_secret', '2fa_target_user_id', '2fa_setup_user_id']);
            abort(403, 'An authenticator is already enrolled. Use the verified recovery process to replace it.');
        }

        $google2fa = app('pragmarx.google2fa');
        $isCurrentSetup = session('2fa_target_user_id') === $targetUser->id
            && session('2fa_setup_user_id') === $currentUser->id;
        $secret = ($isCurrentSetup ? session('2fa_secret') : null) ?? $google2fa->generateSecretKey();

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $targetUser->email,
            $secret
        );

        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd
            )
        );

        $qrCodeSvg = base64_encode($writer->writeString($qrUrl));

        session([
            '2fa_secret' => $secret,
            '2fa_target_user_id' => $targetUser->id,
            '2fa_setup_user_id' => $currentUser->id,
        ]);

        return view('auth.2fa_setup', compact('qrCodeSvg', 'secret', 'targetUser'));
    }

    public function enable(EnableTwoFactorRequest $request): RedirectResponse
    {
        $currentUser = Auth::user();

        if (! $currentUser) {
            return redirect()->route('login');
        }

        $secret = session('2fa_secret');
        $targetUserId = session('2fa_target_user_id');
        $targetUser = User::find($targetUserId);

        if (! $targetUser || ! $secret) {
            session()->forget(['2fa_secret', '2fa_target_user_id', '2fa_setup_user_id']);

            return redirect()->route('login')->with('error', 'Your 2FA setup session expired. Please try again.');
        }

        if ($targetUser->id !== $currentUser->id) {
            $this->authorize('manage-users');
            $this->twoFactorAssurance->ensureVerified($request);
        }

        if (filled($targetUser->google2fa_secret)) {
            session()->forget(['2fa_secret', '2fa_target_user_id', '2fa_setup_user_id']);
            abort(403, 'An authenticator is already enrolled. Use the verified recovery process to replace it.');
        }

        if (session('2fa_setup_user_id') !== $currentUser->id) {
            session()->forget(['2fa_secret', '2fa_target_user_id', '2fa_setup_user_id']);

            return redirect()->route('login')->with('error', 'Your 2FA setup session expired. Please try again.');
        }

        $google2fa = app('pragmarx.google2fa');

        if ($google2fa->verifyKey($secret, $request->otp)) {
            DB::transaction(function () use ($targetUser, $secret): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($targetUser->id);
                abort_if(filled($lockedUser->google2fa_secret), 403, 'An authenticator is already enrolled.');
                $lockedUser->google2fa_secret = $secret;
                $lockedUser->save();
            });

            session()->forget(['2fa_secret', '2fa_target_user_id', '2fa_setup_user_id']);

            return redirect()->route('home')->with('success', '2FA enabled successfully for '.$targetUser->email.'.');
        }

        return back()->with('error', 'Invalid OTP, please try again.');
    }

    public function showDisableForm(?User $user = null): View|RedirectResponse
    {
        $currentUser = Auth::user();

        if (! $currentUser) {
            return redirect()->route('login');
        }

        $targetUser = $user ?? $currentUser;

        if ($targetUser->id !== $currentUser->id) {
            $this->authorize('manage-users');
        }

        $this->twoFactorAssurance->ensureVerified(request());

        if (! $targetUser->google2fa_secret) {
            return redirect()->route('home')->with('info', '2FA is not enabled for this user.');
        }

        return view('auth.2fa_disable', compact('targetUser'));
    }

    public function disable(DisableTwoFactorRequest $request): RedirectResponse
    {
        $currentUser = Auth::user();

        if (! $currentUser) {
            return redirect()->route('login');
        }

        $targetUserId = $request->input('user_id', $currentUser->id);
        $targetUser = User::find($targetUserId) ?? $currentUser;

        if ($targetUser->id !== $currentUser->id) {
            $this->authorize('manage-users');
        }

        $this->twoFactorAssurance->ensureVerified($request);

        if (! $targetUser->google2fa_secret) {
            return redirect()->route('home')->with('info', '2FA is not enabled for this user.');
        }

        $isAnotherUser = $currentUser->id !== $targetUser->id;

        if (! $isAnotherUser) {
            $google2fa = app('pragmarx.google2fa');

            if (! $google2fa->verifyKey($targetUser->google2fa_secret, $request->input('otp'))) {
                return back()->with('error', 'Invalid OTP. Please try again.');
            }
        }

        $targetUser->google2fa_secret = null;
        $targetUser->save();

        session()->forget(['google2fa_passed', '2fa_secret', '2fa_target_user_id', '2fa_setup_user_id']);

        if (! $isAnotherUser) {
            session()->forget(config('google2fa.session_var', 'google2fa'));
        }

        return redirect()->route('home')->with('success', '2FA has been disabled for '.$targetUser->email.'.');
    }
}
