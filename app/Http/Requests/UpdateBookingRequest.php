<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'visitor_id_photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'visitor_marriage_certificate' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'relationship_to_pdl' => ['required', 'string', 'max:100'],
            'visit_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'string', 'max:50'],
            'accompanying_visitors' => ['nullable', 'array'],
            'accompanying_visitors.*.id' => ['nullable', 'integer'],
            'accompanying_visitors.*.full_name' => ['required', 'string', 'max:255'],
            'accompanying_visitors.*.age' => ['required', 'integer', 'min:1', 'max:120'],
            'accompanying_visitors.*.sex' => ['required', 'string', 'in:Male,Female'],
            'accompanying_visitors.*.is_pwd' => ['sometimes', 'boolean'],
            'accompanying_visitors.*.is_pregnant' => ['sometimes', 'boolean', 'declined_if:accompanying_visitors.*.sex,Male'],
            'accompanying_visitors.*.id_type' => ['required', 'string', 'max:50'],
            'accompanying_visitors.*.id_number' => ['required', 'string', 'max:50'],
            'accompanying_visitors.*.id_photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'accompanying_visitors.*.marriage_certificate' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'accompanying_visitors.*.relationship_to_pdl' => ['required', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $booking = $this->route('booking');

            if ($this->input('relationship_to_pdl') === 'Spouse'
                && ! $this->hasFile('visitor_marriage_certificate')
                && ! $booking?->visitor_marriage_certificate_path) {
                $validator->errors()->add('visitor_marriage_certificate', 'A marriage certificate is required for a spouse visitor.');
            }

            foreach ($this->input('accompanying_visitors', []) as $index => $visitor) {
                if (($visitor['relationship_to_pdl'] ?? null) !== 'Spouse'
                    || $this->hasFile("accompanying_visitors.{$index}.marriage_certificate")) {
                    continue;
                }

                $hasExistingCertificate = isset($visitor['id'])
                    && $booking?->accompanyingVisitors()
                        ->whereKey($visitor['id'])
                        ->whereNotNull('marriage_certificate_path')
                        ->exists();

                if (! $hasExistingCertificate) {
                    $validator->errors()->add("accompanying_visitors.{$index}.marriage_certificate", 'A marriage certificate is required for each spouse visitor.');
                }
            }
        }];
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
