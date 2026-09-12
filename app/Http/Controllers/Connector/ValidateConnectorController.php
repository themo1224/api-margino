<?php

namespace App\Http\Controllers\Connector;

use App\Http\Controllers\Controller;
use App\Http\Requests\Connector\ValidateConnectorRequest;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

class ValidateConnectorController extends Controller
{
    public function __invoke(ValidateConnectorRequest $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');
        assert($shop instanceof Shop);

        $shop->loadMissing('plan');

        return response()->json([
            'ok' => true,
            'shop' => [
                'id' => $shop->public_id,
                'name' => $shop->name,
            ],
            'plan' => [
                'id' => $shop->plan->code,
                'status' => $shop->plan->status->value,
                'label' => $shop->plan->label,
            ],
        ]);
    }
}
