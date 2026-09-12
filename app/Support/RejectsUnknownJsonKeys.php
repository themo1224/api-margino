<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

class RejectsUnknownJsonKeys
{
    /**
     * @param  list<string>  $allowed
     */
    public static function of(Request $request, array $allowed): callable
    {
        return function (Validator $validator) use ($request, $allowed): void {
            $payload = $request->isJson()
                ? $request->json()->all()
                : $request->all();

            if (array_is_list($payload) && $payload !== []) {
                $validator->errors()->add('body', 'The request body must be a JSON object.');

                return;
            }

            foreach (array_diff(array_keys($payload), $allowed) as $key) {
                $validator->errors()->add($key, 'The '.$key.' field is not allowed.');
            }
        };
    }
}
