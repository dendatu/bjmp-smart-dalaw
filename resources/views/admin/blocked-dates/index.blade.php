@extends('layouts.admin')

@section('title', 'Blocked Dates | SmartDalaw')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase text-[#0A2472]">Facility management</p>
            <h1 class="mt-1 text-2xl font-bold text-[#102044]">Blocked dates</h1>
            <p class="mt-2 text-sm text-slate-600">Block specific dates to prevent new bookings. All pending and approved bookings on a blocked date are automatically rejected.</p>
        </div>
    </div>

    {{-- Block a new date --}}
    <section class="mt-7 rounded-lg border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="block-date-heading">
        <h2 id="block-date-heading" class="text-base font-semibold text-slate-900">Block a date</h2>
        <form action="{{ route('admin.blocked-dates.store') }}" method="POST" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf

            @if(auth()->user()->isSuperAdmin())
                <div>
                    <label for="facility_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Facility</label>
                    <select id="facility_id" name="facility_id" required
                            class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="" disabled selected>Select facility</option>
                        @foreach($facilities as $facility)
                            <option value="{{ $facility->id }}" @selected(old('facility_id') == $facility->id)>{{ $facility->name }}</option>
                        @endforeach
                    </select>
                    @error('facility_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="facility_id" value="{{ auth()->user()->facility_id }}">
            @endif

            <div>
                <label for="blocked_date" class="mb-1.5 block text-sm font-semibold text-slate-700">Date to block</label>
                <input type="date" id="blocked_date" name="blocked_date" value="{{ old('blocked_date') }}"
                       min="{{ today()->addDay()->toDateString() }}" required
                       class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                @error('blocked_date')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="{{ auth()->user()->isSuperAdmin() ? 'sm:col-span-2 lg:col-span-2' : 'sm:col-span-1 lg:col-span-2' }}">
                <label for="reason" class="mb-1.5 block text-sm font-semibold text-slate-700">Reason</label>
                <input type="text" id="reason" name="reason" value="{{ old('reason') }}"
                       maxlength="1000" required placeholder="e.g. Facility inspection, National holiday, Security lockdown"
                       class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                @error('reason')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

        <div class="mt-6 sm:col-span-2 lg:col-span-4">
            <button type="submit"
                    class="w-full min-h-11 rounded-md bg-red-700 px-4 py-3 text-sm font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                Block date &amp; reject bookings
            </button>
        </div>
    </form>
        <p class="mt-3 text-xs text-slate-500">Blocking a date will immediately reject all pending and approved bookings on that date and notify visitors by email.</p>
    </section>

    {{-- Existing blocked dates --}}
    <section class="mt-8" aria-labelledby="blocked-dates-heading">
        <h2 id="blocked-dates-heading" class="text-lg font-bold text-[#102044]">Blocked dates list</h2>

        <div class="mt-4 overflow-x-auto border-y border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        @if(auth()->user()->isSuperAdmin())
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Facility</th>
                        @endif
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Blocked date</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Reason</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Blocked by</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Date added</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($blockedDates as $blocked)
                        <tr>
                            @if(auth()->user()->isSuperAdmin())
                                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">{{ $blocked->facility->name }}</td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-4">
                                <span class="text-sm font-semibold text-red-700">{{ $blocked->blocked_date->format('M d, Y') }}</span>
                                @if($blocked->blocked_date->isPast())
                                    <span class="ml-1.5 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">Past</span>
                                @elseif($blocked->blocked_date->isToday())
                                    <span class="ml-1.5 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">Today</span>
                                @else
                                    <span class="ml-1.5 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">Upcoming</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-700">{{ $blocked->reason }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600">{{ $blocked->blockedBy->name }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-500">{{ $blocked->created_at->format('M d, Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <form action="{{ route('admin.blocked-dates.destroy', $blocked) }}" method="POST"
                                      onsubmit="return confirm('Remove this date block? Visitors will be able to book on {{ $blocked->blocked_date->format('M d, Y') }} again.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Remove block</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isSuperAdmin() ? 6 : 5 }}" class="px-4 py-10 text-center text-sm text-slate-500">
                                No dates are currently blocked.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($blockedDates->hasPages())
            <div class="mt-5">{{ $blockedDates->links() }}</div>
        @endif
    </section>
@endsection
