<?php

namespace App\Http\Requests\Projects;

use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $client = $this->route('client');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Client::class)->ignore($client instanceof Client ? $client->id : null),
            ],
        ];
    }
}
