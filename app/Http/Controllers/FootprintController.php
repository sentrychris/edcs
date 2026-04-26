<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FootprintController extends Controller
{
    public function index(): View
    {
        return view('footprint.index');
    }

    public function log(Request $request): JsonResponse
    {
        $ip = $this->resolveClientIp($request);

        Log::channel('footprint')->info('footprint', [
            'timestamp' => now()->toIso8601String(),
            'ip' => $ip,
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'client_payload' => $request->all(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function echo(Request $request): JsonResponse
    {
        $ip = $this->resolveClientIp($request);

        return response()->json([
            'timestamp' => [
                'unix' => now()->unix(),
                'iso' => now()->toIso8601String(),
                'timezone' => config('app.timezone'),
            ],
            'ip' => $this->ipInfo($request, $ip),
            'geo' => $this->fetchGeo($ip),
            'request' => [
                'method' => $request->method(),
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'uri' => $request->getRequestUri(),
                'path' => $request->path(),
                'query_string' => $request->getQueryString(),
                'query_params' => $request->query(),
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'body_json' => $request->json()->all(),
            ],
            'client_headers' => [
                'user_agent' => $request->userAgent(),
                'accept' => $request->header('Accept'),
                'accept_language' => $request->header('Accept-Language'),
                'accept_encoding' => $request->header('Accept-Encoding'),
                'referer' => $request->header('Referer'),
                'origin' => $request->header('Origin'),
                'dnt' => $request->header('DNT'),
                'sec_ch_ua' => $request->header('Sec-CH-UA'),
                'sec_ch_ua_platform' => $request->header('Sec-CH-UA-Platform'),
                'sec_ch_ua_mobile' => $request->header('Sec-CH-UA-Mobile'),
            ],
            'connection' => [
                'server_protocol' => $request->server('SERVER_PROTOCOL'),
                'server_port' => $request->server('SERVER_PORT'),
                'https' => $request->secure(),
                'request_time' => $request->server('REQUEST_TIME'),
                'request_time_float' => $request->server('REQUEST_TIME_FLOAT'),
            ],
            'server' => [
                'server_name' => $request->server('SERVER_NAME'),
                'server_addr' => $request->server('SERVER_ADDR'),
                'server_software' => $request->server('SERVER_SOFTWARE'),
                'php_version' => PHP_VERSION,
            ],
        ]);
    }

    private function resolveClientIp(Request $request): string
    {
        return $request->header('CF-Connecting-IP')
            ?? (explode(',', $request->header('X-Forwarded-For', ''))[0] ?: null)
            ?? $request->ip();
    }

    private function ipInfo(Request $request, string $ip): array
    {
        $xff = $request->header('X-Forwarded-For');

        return [
            'address' => $ip,
            'remote_addr' => $request->server('REMOTE_ADDR'),
            'remote_port' => $request->server('REMOTE_PORT'),
            'x_forwarded_for' => $xff,
            'x_forwarded_chain' => $xff ? array_map('trim', explode(',', $xff)) : [],
            'x_real_ip' => $request->header('X-Real-IP'),
            'client_ip_header' => $request->header('Client-IP'),
            'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
            'cf_ipcountry' => $request->header('CF-IPCountry'),
            'true_client_ip' => $request->header('True-Client-IP'),
            'forwarded' => $request->header('Forwarded'),
        ];
    }

    private function fetchGeo(string $ip): ?array
    {
        if (in_array($ip, ['127.0.0.1', '::1', ''])) {
            return null;
        }

        $response = Http::timeout(2)->get("https://ipwho.is/{$ip}");

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (! ($data['success'] ?? false)) {
            return null;
        }

        return [
            'country' => $data['country'] ?? null,
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'timezone' => $data['timezone']['id'] ?? null,
            'connection' => [
                'asn' => $data['connection']['asn'] ?? null,
                'isp' => $data['connection']['isp'] ?? null,
                'org' => $data['connection']['org'] ?? null,
            ],
            'security' => [
                'proxy' => $data['security']['proxy'] ?? null,
                'vpn' => $data['security']['vpn'] ?? null,
                'tor' => $data['security']['tor'] ?? null,
            ],
        ];
    }
}
