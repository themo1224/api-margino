<?php

namespace App\Http\Requests\Connector;

use App\Enums\AppliedSource;
use App\Support\RejectsUnknownJsonKeys;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcknowledgeAppliedPriceRequest extends FormRequest
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
        return [
            'applied_price' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'currency' => ['required', 'string', 'max:16'],
            'source' => ['required', 'string', Rule::enum(AppliedSource::class)],
            'applied_at' => ['required', 'string', 'date'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            RejectsUnknownJsonKeys::of($this, ['applied_price', 'currency', 'source', 'applied_at']),
        ];
    }
}
