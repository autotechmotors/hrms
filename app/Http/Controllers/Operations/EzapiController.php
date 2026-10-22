<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Devices\EzapiGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EzapiController extends Controller
{
    public function push(Request $request, string $action, EzapiGateway $gateway): Response
    {
        $result = $gateway->handle($action, $request);

        return response($result['body'], $result['status'], [
            'Content-Type' => 'application/json; charset=utf-8',
            'Connection' => 'keep-alive',
        ]);
    }
}
