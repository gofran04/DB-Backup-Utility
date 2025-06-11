<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatabaseConnectionRequest extends FormRequest
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
            'connection_name' => 'required|string',
            'type'            => ['required',Rule::in(['mysql', 'postgresql'])],
            'host'            => 'required|string',
            'port'            => 'required|integer',
            'db_name'         => 'required|string',
            'username'        => 'required|string',
            'password'        => 'required|string',
        ];
    }
}
