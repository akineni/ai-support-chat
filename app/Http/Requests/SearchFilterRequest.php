<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Common search and filtering parameters.
 *
 * @queryParam search string Search term used to filter records. Example: john
 * @queryParam status string Filter records by status. Example: active
 * @queryParam start_date string Filter records created from this date (YYYY-MM-DD). Example: 2026-01-01
 * @queryParam end_date string Filter records created up to this date (YYYY-MM-DD). Example: 2026-01-31
 * @queryParam per_page int Number of items per page. Example: 15
 */
class SearchFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Return only the supported filter inputs.
     */
    public function filters(): array
    {
        return array_filter([
            'per_page' => $this->perPage(),
            'search' => $this->searchTerm(),
            'status' => $this->status(),
            'start_date' => $this->startDate(),
            'end_date' => $this->endDate(),
        ], fn ($value) => $value !== null);
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }

    public function searchTerm(): ?string
    {
        return $this->normalizeString($this->input('search'));
    }

    public function status(): ?string
    {
        return $this->normalizeString($this->input('status'));
    }

    public function startDate(): ?string
    {
        return $this->filled('start_date') ? $this->input('start_date') : null;
    }

    public function endDate(): ?string
    {
        return $this->filled('end_date') ? $this->input('end_date') : null;
    }

    protected function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}