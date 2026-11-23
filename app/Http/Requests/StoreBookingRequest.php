<?php

namespace App\Http\Requests;

use App\Models\BlockedDate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public const PRIVACY_NOTICE_VERSION = '2026-09-28-v1';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'visitor_is_pwd' => $this->boolean('visitor_is_pwd'),
            'visitor_is_pregnant' => $this->boolean('visitor_is_pregnant'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'dormitory_id' => [
                'required',
                'integer',
                Rule::exists('dormitories', 'id')
                    ->where(fn ($query) => $query->where('facility_id', $this->input('facility_id'))),
            ],
            'pdl_name' => ['required', 'string', 'max:255'],
            'visitor_full_name' => ['required', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'visitor_contact' => ['required', 'string', 'max:20'],
            'visitor_sex' => ['required', 'string', 'in:Male,Female'],
            'visitor_age' => ['required', 'integer', 'min:1', 'max:120'],
            'visitor_is_pwd' => ['boolean'],
            'visitor_is_pregnant' => ['boolean', 'declined_if:visitor_sex,Male'],
            'visitor_id_type' => ['required', 'string', 'max:50'],
            'visitor_id_number' => ['required', 'string', 'max:50'],
            'visitor_id_photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'visitor_marriage_certificate' => ['required_if:relationship_to_pdl,Spouse', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'relationship_to_pdl' => ['required', 'string', 'max:100'],
            'visit_date' => [
                'required',
                'date',
                'after:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $facilityId = $this->input('facility_id');

                    if (! $facilityId || ! $value) {
                        return;
                    }

                    $isBlocked = BlockedDate::query()
                        ->where('facility_id', $facilityId)
                        ->whereDate('blocked_date', $value)
                        ->exists();

                    if ($isBlocked) {
                        $blocked = BlockedDate::query()
                            ->where('facility_id', $facilityId)
                            ->whereDate('blocked_date', $value)
                            ->first();

                        $dateLabel = date('M j, Y', strtotime($value));
                        $fail("The selected date, {$dateLabel}, has been blocked. Reason: ".($blocked?->reason ?? 'The facility has closed this day.'));
                    }
                },
            ],
            'time_slot' => ['required', 'string', 'max:50'],
            'terms_accepted' => ['required', 'accepted'],
            'privacy_consent' => ['required', 'accepted'],
            'accompanying_visitors' => ['nullable', 'array'],
            'accompanying_visitors.*' => ['required', 'array'],
            'accompanying_visitors.*.full_name' => ['required', 'string', 'max:255'],
            'accompanying_visitors.*.age' => ['required', 'integer', 'min:1', 'max:120'],
            'accompanying_visitors.*.sex' => ['required', 'string', 'in:Male,Female'],
            'accompanying_visitors.*.is_pwd' => ['sometimes', 'boolean'],
            'accompanying_visitors.*.is_pregnant' => ['sometimes', 'boolean', 'declined_if:accompanying_visitors.*.sex,Male'],
            'accompanying_visitors.*.id_type' => ['required', 'string', 'max:50'],
            'accompanying_visitors.*.id_number' => ['required', 'string', 'max:50'],
            'accompanying_visitors.*.id_photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'accompanying_visitors.*.marriage_certificate' => ['required_if:accompanying_visitors.*.relationship_to_pdl,Spouse', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'accompanying_visitors.*.relationship_to_pdl' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visitor_id_photo.uploaded' => 'The valid ID photo could not be uploaded. Please use a JPG, PNG, or WEBP image no larger than 2 MB.',
            'visitor_marriage_certificate.uploaded' => 'The marriage certificate could not be uploaded. Please use a JPG, PNG, or WEBP image no larger than 2 MB.',
            'accompanying_visitors.*.id_photo.uploaded' => 'Each accompanying visitor\'s ID photo could not be uploaded. Please use a JPG, PNG, or WEBP image no larger than 2 MB.',
            'accompanying_visitors.*.marriage_certificate.uploaded' => 'Each accompanying visitor\'s marriage certificate could not be uploaded. Please use a JPG, PNG, or WEBP image no larger than 2 MB.',
            'visitor_is_pregnant.declined_if' => 'Only female visitors can be declared as pregnant.',
            'accompanying_visitors.*.is_pregnant.declined_if' => 'Only female accompanying visitors can be declared as pregnant.',
        ];
    }
}
