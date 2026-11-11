<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Client's name, when creating or renaming one.
 */
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
