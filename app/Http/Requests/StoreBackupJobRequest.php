<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\ConfigService;

class StoreBackupJobRequest extends FormRequest
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
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $dbProfile = $this->input('db_profile');

            if (empty($this->db_profile) && empty($this->db_id)) {
                $validator->errors()->add('db_id', 'Either db_profile or db_id is required.');
                $validator->errors()->add('db_profile', 'Either db_profile or db_id is required.');
            }

            if (!empty($this->db_profile) && !empty($this->db_id)) {
                $validator->errors()->add('id_profile', 'Provide either db_profile or db_id, not both.');
            }

            if (!empty($dbProfile)) {
                $configService = app(ConfigService::class);
                $profiles = $configService->loadProfiles();

                if (!isset($profiles[$dbProfile])) {
                    $validator->errors()->add('db_profile', "Profile '{$dbProfile}' does not exist in config.json.");
                }
            }
        });
    }
}
