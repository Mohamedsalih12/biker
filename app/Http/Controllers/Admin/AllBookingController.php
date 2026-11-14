<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class AllBookingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Booking::class);
        activity('booking')->causedBy(auth()->user())->event('view')
            ->withProperties(['action' => 'view'])->log('All Bookings viewed');

        return view('admin.all-bookings.index');
    }
}
