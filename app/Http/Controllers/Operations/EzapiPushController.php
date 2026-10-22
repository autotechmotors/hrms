<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Devices\EzapiGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EzapiPushController extends Controller
{
    public function handle(Request $request, string $action, EzapiGateway $gateway): Response
    {
        return $gateway->handle($request, $action);
    }
}
