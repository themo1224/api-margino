<?php

namespace App\Http\Requests\Connector;

use App\Support\RejectsUnknownJsonKeys;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidateConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            RejectsUnknownJsonKeys::of($this, []),
        ];
    }
}
