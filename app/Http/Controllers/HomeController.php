<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\MaintenanceLog;
use App\Models\Payment;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'staff'], true), 403);

        $takenCount = Booking::query()->where('booking_status', 'taken')->count();
        $vehicleCount = Vehicle::query()->count();
        $availableCount = Vehicle::query()->where('status', 'available')->count();
        $overdueCount = Booking::query()->where('payment_status', 'overdue')->count();
        $revenue = (float) Payment::query()->sum('amount_received');
        $paymentCount = Payment::query()->count();

        $recentBookings = Booking::query()
            ->with(['customer:id,name', 'vehicle:id,registration_number'])
            ->latest('id')
            ->limit(5)
            ->get();

        $activeRentals = Booking::query()
            ->where('booking_status', 'taken')
            ->with(['customer:id,name', 'vehicle:id,registration_number'])
            ->orderBy('end_date')
            ->limit(4)
            ->get();

        $workshopLogs = MaintenanceLog::query()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->with('vehicle:id,registration_number')
            ->orderBy('delivered_date')
            ->limit(2)
            ->get();

        $chart = $this->bookingsPerMonthChart();

        return view('home', compact(
            'takenCount', 'vehicleCount', 'availableCount', 'overdueCount',
            'revenue', 'paymentCount', 'recentBookings', 'activeRentals', 'workshopLogs', 'chart',
        ));
    }

    /**
     * Bookings started per month for the last six months.
     *
     * Grouped in PHP so the query stays driver-agnostic (tests run on SQLite).
     *
     * @return list<array{label: string, count: int}>
     */
    private function bookingsPerMonthChart(): array
    {
        $start = now()->subMonths(5)->startOfMonth();

        $counts = Booking::query()
            ->where('start_date', '>=', $start->toDateString())
            ->pluck('start_date')
            ->countBy(fn (Carbon $date): string => $date->format('Y-m'));

        return collect(range(0, 5))
            ->map(function (int $offset) use ($counts): array {
                $month = now()->subMonths(5 - $offset)->startOfMonth();

                return [
                    'label' => $month->format('M'),
                    'count' => (int) $counts->get($month->format('Y-m'), 0),
                ];
            })
            ->all();
    }

    public function login()
    {
        return view('auth.login');
    }
}
