<?php

use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('private');
    Mail::fake();
});

function blockedFacility(string $name = 'Test Jail'): Facility
{
    $facility = Facility::create([
        'name' => $name,
        'max_visitors_per_hour' => 20,
    ]);

    $facility->dormitories()->create([
        'name' => 'Male Dormitory',
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
    ]);

    return $facility;
}

function blockedFacilityAdmin(Facility $facility): User
{
    return User::factory()->create([
        'role' => User::ROLE_FACILITY_ADMIN,
        'facility_id' => $facility->id,
    ]);
}

function blockedSuperAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
}

function pendingBookingOn(Facility $facility, string $date, string $status = 'Pending'): Booking
{
    return Booking::create([
        'reference_code' => 'BJMP-'.strtoupper(substr(md5($date.$status.rand()), 0, 6)),
        'facility_id' => $facility->id,
        'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
        'booking_channel' => Booking::CHANNEL_ONLINE,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Test Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => $date,
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => $status,
    ]);
}

// --- Admin can block a date and see it listed ---

test('admin can block a future date with a reason', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);
    $date = today()->addDays(5)->toDateString();

    $this->actingAs($admin)
        ->post(route('admin.blocked-dates.store'), [
            'facility_id' => $facility->id,
            'blocked_date' => $date,
            'reason' => 'Facility inspection',
        ])
        ->assertRedirect(route('admin.blocked-dates.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('blocked_dates', [
        'facility_id' => $facility->id,
        'blocked_date' => $date.' 00:00:00',
        'reason' => 'Facility inspection',
        'blocked_by' => $admin->id,
    ]);
});

test('blocked dates index lists existing blocks', function () {
    $facility = blockedFacility();
    $admin = blockedSuperAdmin();
    $date = today()->addDays(3)->toDateString();

    BlockedDate::create([
        'facility_id' => $facility->id,
        'blocked_by' => $admin->id,
        'blocked_date' => $date,
        'reason' => 'National holiday',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.blocked-dates.index'))
        ->assertOk()
        ->assertSee('National holiday')
        ->assertSee($facility->name);
});

// --- Existing bookings are auto-rejected when a date is blocked ---

test('pending bookings on a blocked date are automatically rejected', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);
    $date = today()->addDays(7)->toDateString();

    $booking = pendingBookingOn($facility, $date, 'Pending');

    $this->actingAs($admin)
        ->post(route('admin.blocked-dates.store'), [
            'facility_id' => $facility->id,
            'blocked_date' => $date,
            'reason' => 'Security lockdown',
        ]);

    expect($booking->fresh()->status)->toBe('Rejected');
});

test('approved bookings on a blocked date are automatically rejected', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);
    $date = today()->addDays(7)->toDateString();

    $booking = pendingBookingOn($facility, $date, 'Approved');

    $this->actingAs($admin)
        ->post(route('admin.blocked-dates.store'), [
            'facility_id' => $facility->id,
            'blocked_date' => $date,
            'reason' => 'Security lockdown',
        ]);

    expect($booking->fresh()->status)->toBe('Rejected');
});

test('a status history entry is recorded for each rejected booking when date is blocked', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);
    $date = today()->addDays(7)->toDateString();

    $booking = pendingBookingOn($facility, $date, 'Pending');

    $this->actingAs($admin)
        ->post(route('admin.blocked-dates.store'), [
            'facility_id' => $facility->id,
            'blocked_date' => $date,
            'reason' => 'Inspection day',
        ]);

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'from_status' => 'Pending',
        'to_status' => 'Rejected',
    ]);
});

test('already rejected bookings are not affected when a date is blocked', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);
    $date = today()->addDays(7)->toDateString();

    $booking = pendingBookingOn($facility, $date, 'Rejected');
    $originalUpdatedAt = $booking->updated_at;

    $this->actingAs($admin)
        ->post(route('admin.blocked-dates.store'), [
            'facility_id' => $facility->id,
            'blocked_date' => $date,
            'reason' => 'Holiday',
        ]);

    // Status unchanged; still Rejected
    expect($booking->fresh()->status)->toBe('Rejected');
});

// --- New bookings cannot be made on blocked dates ---

test('visitors cannot book on a blocked date', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);
    $dormitory = $facility->dormitories()->firstOrFail();
    $date = today()->addDays(5)->toDateString();

    BlockedDate::create([
        'facility_id' => $facility->id,
        'blocked_by' => $admin->id,
        'blocked_date' => $date,
        'reason' => 'Inspection',
    ]);

    $this->post(route('bookings.store'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'New Visitor',
        'visitor_sex' => 'Female',
        'visitor_age' => 25,
        'visitor_contact' => '09987654321',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P7654321',
        'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => $date,
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ])->assertSessionHasErrors([
        'visit_date' => 'The selected date, '.date('M j, Y', strtotime($date)).', has been blocked. Reason: Inspection',
    ]);

    expect(Booking::count())->toBe(0);
});

test('slot availability API reports date as blocked', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);
    $dormitory = $facility->dormitories()->firstOrFail();
    $date = today()->addDays(5)->toDateString();

    BlockedDate::create([
        'facility_id' => $facility->id,
        'blocked_by' => $admin->id,
        'blocked_date' => $date,
        'reason' => 'Inspection',
    ]);

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'visit_date' => $date,
    ])->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('date_blocked', true)
        ->assertJsonPath('date_block_reason', 'Inspection');
});

// --- Admin can remove a block ---

test('admin can remove a blocked date', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);

    $blocked = BlockedDate::create([
        'facility_id' => $facility->id,
        'blocked_by' => $admin->id,
        'blocked_date' => today()->addDays(5)->toDateString(),
        'reason' => 'Inspection',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.blocked-dates.destroy', $blocked))
        ->assertRedirect(route('admin.blocked-dates.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('blocked_dates', ['id' => $blocked->id]);
});

// --- Facility admin isolation ---

test('facility admin cannot block dates for another facility', function () {
    $facilityA = blockedFacility('Facility A');
    $facilityB = blockedFacility('Facility B');
    $adminA = blockedFacilityAdmin($facilityA);

    $this->actingAs($adminA)
        ->post(route('admin.blocked-dates.store'), [
            'facility_id' => $facilityB->id,
            'blocked_date' => today()->addDays(5)->toDateString(),
            'reason' => 'Unauthorized block',
        ])
        ->assertSessionHasErrors('facility_id');

    $this->assertDatabaseEmpty('blocked_dates');
});

test('facility admin cannot remove another facility blocked date', function () {
    $facilityA = blockedFacility('Facility A');
    $facilityB = blockedFacility('Facility B');
    $adminA = blockedFacilityAdmin($facilityA);
    $adminB = blockedFacilityAdmin($facilityB);

    $blocked = BlockedDate::create([
        'facility_id' => $facilityB->id,
        'blocked_by' => $adminB->id,
        'blocked_date' => today()->addDays(5)->toDateString(),
        'reason' => 'Inspection',
    ]);

    $this->actingAs($adminA)
        ->delete(route('admin.blocked-dates.destroy', $blocked))
        ->assertForbidden();

    $this->assertDatabaseHas('blocked_dates', ['id' => $blocked->id]);
});

// --- Validation ---

test('blocking a past or today date is rejected', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);

    $this->actingAs($admin)
        ->post(route('admin.blocked-dates.store'), [
            'facility_id' => $facility->id,
            'blocked_date' => today()->toDateString(),
            'reason' => 'Too late',
        ])
        ->assertSessionHasErrors('blocked_date');
});

test('a reason is required when blocking a date', function () {
    $facility = blockedFacility();
    $admin = blockedFacilityAdmin($facility);

    $this->actingAs($admin)
        ->post(route('admin.blocked-dates.store'), [
            'facility_id' => $facility->id,
            'blocked_date' => today()->addDays(5)->toDateString(),
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');
});

test('guests cannot access blocked dates management', function () {
    $this->get(route('admin.blocked-dates.index'))->assertRedirect(route('login'));
    $this->post(route('admin.blocked-dates.store'))->assertRedirect(route('login'));
});
