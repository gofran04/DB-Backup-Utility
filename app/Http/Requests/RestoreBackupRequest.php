<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
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
            'db_id'      => ['nullable', 'exists:database_connections,id'],
            'db_profile' => ['nullable', 'string'],
            'file'       => ['required','string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (empty($this->db_profile) && empty($this->db_id)) {
                $validator->errors()->add('id_profile', 'Either db_profile or db_id is required.');
            }

            if (!empty($this->db_profile) && !empty($this->db_id)) {
                $validator->errors()->add('id_profile', 'Provide either db_profile or db_id, not both.');
            }
        });
    }
}
