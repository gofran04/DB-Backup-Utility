<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\ScheduleFrequency;
use App\Services\ConfigService;

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
            'db_connection_id' => ['nullable',' exists:database_connections,id'],
            'profile_name'     => ['nullable', 'string'],
            'frequency'        => ['required', 'in:' . implode(',', array_keys(ScheduleFrequency::OPTIONS))],
        ];

        if ($this->isMethod('post')) { // Only during store()
            $rules['enabled'] = ['sometimes', 'boolean'];
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $dbProfile = $this->input('profile_name');

            if (empty($this->profile_name) && empty($this->db_connection_id)) {
                $validator->errors()->add('db_connection_id', 'Either profile_name or db_connection_id is required.');
                $validator->errors()->add('profile_name', 'Either profile_name or db_connection_id is required.');
            }

            if (!empty($this->profile_name) && !empty($this->db_connection_id)) {
                $validator->errors()->add('db_connection_id', 'Provide either profile_name or db_connection_id, not both.');
                $validator->errors()->add('profile_name', 'Provide either profile_name or db_connection_id, not both.');
            }

            if (!empty($dbProfile)) {
                $configService = app(ConfigService::class);
                $profiles = $configService->loadProfiles();

                if (!isset($profiles[$dbProfile])) {
                    $validator->errors()->add('profile_name', "Profile '{$dbProfile}' does not exist in config.json.");
                }
            }
        });
    }
}
