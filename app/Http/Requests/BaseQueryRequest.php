<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseQueryRequest extends FormRequest
{
    /**
     * Default number of records per page when none is specified.
     */
    protected int $defaultPerPage = 15;

    /**
     * Maximum number of records allowed per page.
     */
    protected int $maxPerPage = 100;

    public function authorize(): bool
    {
        return true;
    }

    // protected function prepareForValidation(): void
    // {
    //     $this->merge([
    //         'page' => $this->normalizeInteger($this->input('page')),
    //         'per_page' => $this->normalizeInteger($this->input('per_page')),
    //     ]);
    // }

    public function rules(): array
    {
        return array_merge($this->baseRules(), $this->queryRules());
    }

    /**
     * Base pagination rules that every query request should follow.
     */
    protected function baseRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . $this->maxPerPage],
        ];
    }

    /**
     * Additional rules defined by child classes.
     */
    abstract protected function queryRules(): array;

    /**
     * Resolve per page value with defaults/limits applied.
     */
    public function perPage(): int
    {
        $perPage = $this->input('per_page', $this->defaultPerPage);

        if (!is_int($perPage)) {
            $perPage = (int) $perPage;
        }

        if ($perPage < 1) {
            $perPage = $this->defaultPerPage;
        }

        return $perPage > $this->maxPerPage
            ? $this->maxPerPage
            : $perPage;
    }

    /**
     * Get requested page number.
     */
    public function page(): int
    {
        return max(1, (int) $this->input('page', 1));
    }

    /**
     * Helper to safely normalize integers from query string.
     */
    protected function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
