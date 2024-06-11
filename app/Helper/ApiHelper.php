<?php

use Illuminate\Support\Facades\Log;

if (!function_exists('callExternalPostApi')) {
    function callExternalPostApi(string $api, array $headers = [], array $body = [], array $params = [], $method = 'post'): array
    {
        $params = [
            'query' => http_build_query($params)
        ];

        $response = Http::withHeaders($headers)
            ->withOptions($params)
            ->{$method}($api, $body);

        Log::info('call external post api log');
        Log::info($response);

        return formatApiResponse($response->status(), $response->reason(), $response->json());
    }
}

if (!function_exists('formatApiResponse')) {
    function formatApiResponse(int $status_code = 200, string $message = 'Operation Successful', $data = []): array
    {
        return [
            'success'       => $status_code >= 200 && $status_code < 300,
            'status_code'   => $status_code,
            'message'       => $message,
            'data'          => $data
        ];
    }
}
?>
