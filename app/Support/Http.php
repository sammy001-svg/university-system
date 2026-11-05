<?php
declare(strict_types=1);

namespace App\Support;

/** A small cURL wrapper for calling payment and SMS gateways. */
final class Http
{
    /**
     * @param array<string,string> $headers
     * @param string|array|null    $body array = form-encoded, string = sent as is
     * @return array{status:int, body:string, json:mixed, error:?string}
     */
    public static function request(string $method, string $url, array $headers = [], string|array|null $body = null, int $timeout = 15): array
    {
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
        }
        $response = curl_exec($ch);
        $error    = $response === false ? curl_error($ch) : null;
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $text = is_string($response) ? $response : '';
        return ['status' => $status, 'body' => $text, 'json' => json_decode($text, true), 'error' => $error];
    }
}
