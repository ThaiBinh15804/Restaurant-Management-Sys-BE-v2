<?php

namespace App\Http\Requests\Stock;

use App\Models\StockExport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockExportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in([
                StockExport::STATUS_DRAFT,
                StockExport::STATUS_APPROVED,
                StockExport::STATUS_COMPLETED,
            ])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status field is required.',
            'status.in' => 'Invalid status value.',
        ];
    }
}
