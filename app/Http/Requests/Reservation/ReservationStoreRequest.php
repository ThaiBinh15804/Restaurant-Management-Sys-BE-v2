<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class ReservationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number_of_people' => 'required|integer|min:1|max:50',
            'notes'             => 'nullable|string|max:500',
            'reserved_at'      => 'required|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'number_of_people.required' => 'Vui lòng nhập số lượng khách',
            'number_of_people.min'      => 'Số lượng khách phải ít nhất là 1',
            'number_of_people.max'      => 'Số lượng khách không được vượt quá 50',
            'reserved_at.required'      => 'Vui lòng chọn thời gian đặt bàn',
            'reserved_at.after'         => 'Thời gian đặt bàn phải sau thời điểm hiện tại',
        ];
    }
}