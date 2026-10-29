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
        $body = $result['body'];

        // Dahua terminals parse Content-Length keep-alive replies (Go demo style).
        // Chunked / gzip responses often leave the device stuck on /push/init.
        return response($body, $result['status'], [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Length' => (string) strlen($body),
            'Connection' => 'keep-alive',
            'Cache-Control' => 'no-store, no-transform',
        ]);
    }
}
