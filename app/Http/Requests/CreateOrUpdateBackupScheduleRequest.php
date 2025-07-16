<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\ScheduleFrequency;

class CreateOrUpdateBackupScheduleRequest extends FormRequest
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
            'db_connection_id' => 'required||exists:database_connections,id',
            'enabled'          => 'required|boolean',
            'frequency'        => ['required', 'in:' . implode(',', array_keys(ScheduleFrequency::OPTIONS))],
        ];
    }
}
