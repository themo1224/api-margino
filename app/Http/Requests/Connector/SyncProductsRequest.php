<?php

namespace App\Http\Requests\Connector;

use App\Support\RejectsUnknownJsonKeys;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncProductsRequest extends FormRequest
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
            'currency' => ['required', 'string', 'max:16'],
            'products' => ['present', 'array', 'max:'.(int) config('connector.sync_batch_max')],
            'products.*' => ['required', 'array'],
            'products.*.external_id' => ['required', 'string', 'max:64'],
            'products.*.sku' => ['nullable', 'string', 'max:191'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.price' => ['required', 'string'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            RejectsUnknownJsonKeys::of($this, ['currency', 'products']),
            function (Validator $validator): void {
                $products = $this->input('products');

                if (! is_array($products)) {
                    return;
                }

                foreach ($products as $index => $product) {
                    if (! is_array($product)) {
                        continue;
                    }

                    foreach (array_diff(array_keys($product), ['external_id', 'sku', 'name', 'price']) as $key) {
                        $validator->errors()->add(
                            'products.'.$index.'.'.$key,
                            'The '.$key.' field is not allowed.',
                        );
                    }
                }
            },
        ];
    }
}
