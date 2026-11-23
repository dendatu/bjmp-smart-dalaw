<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BookingStatusChanged;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class BlockedDateController extends Controller
{
    public function index(Request $request): View
    {
        $blockedDates = BlockedDate::visibleToUser($request->user())
            ->with(['facility', 'blockedBy'])
            ->orderBy('blocked_date', 'desc')
            ->paginate(20);

        $facilities = Facility::visibleToUser($request->user())
            ->orderBy('regional_office')
            ->orderBy('name')
            ->get();

        return view('admin.blocked-dates.index', compact('blockedDates', 'facilities'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $facilityRule = $user->isSuperAdmin()
            ? ['required', 'integer', 'exists:facilities,id']
            : ['required', 'integer', 'in:'.($user->facility_id ?? 0)];

        $validated = $request->validate([
            'facility_id' => $facilityRule,
            'blocked_date' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $blockedDate = DB::transaction(function () use ($validated, $user): BlockedDate {
            /** @var BlockedDate $blocked */
            $blocked = BlockedDate::create([
                'facility_id' => $validated['facility_id'],
                'blocked_by' => $user->id,
                'blocked_date' => $validated['blocked_date'],
                'reason' => $validated['reason'],
            ]);

            // Auto-reject all pending and approved bookings on this date for this facility.
            $bookingsToReject = Booking::query()
                ->where('facility_id', $validated['facility_id'])
                ->whereDate('visit_date', $validated['blocked_date'])
                ->whereIn('status', ['Pending', 'Approved'])
                ->get();

            foreach ($bookingsToReject as $booking) {
                $previousStatus = $booking->status;
                $booking->status = 'Rejected';
                $booking->admin_remarks = 'Visitation on this date has been cancelled: '.$validated['reason'];
                $booking->save();

                $booking->statusHistory()->create([
                    'user_id' => $user->id,
                    'from_status' => $previousStatus,
                    'to_status' => 'Rejected',
                    'reason' => 'Date blocked by facility administrator. Reason: '.$validated['reason'],
                ]);

                if ($booking->visitor_email) {
                    try {
                        Mail::to($booking->visitor_email)->send(new BookingStatusChanged(
                            $booking,
                            $previousStatus,
                            'Visitation on this date has been cancelled: '.$validated['reason'],
                        ));
                    } catch (\Throwable $e) {
                        Log::error('Blocked-date rejection email failed', [
                            'reference_code' => $booking->reference_code,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return $blocked;
        });

        return redirect()->route('admin.blocked-dates.index')
            ->with('success', 'Date blocked successfully. All pending and approved bookings for that date have been rejected.');
    }

    public function destroy(Request $request, BlockedDate $blockedDate): RedirectResponse
    {
        $user = $request->user();

        // Facility admins can only unblock their own facility's dates.
        if (! $user->isSuperAdmin() && (int) $blockedDate->facility_id !== (int) $user->facility_id) {
            abort(403);
        }

        $blockedDate->delete();

        return redirect()->route('admin.blocked-dates.index')
            ->with('success', 'The date block has been removed. Visitors may now book on that date again.');
    }
}
