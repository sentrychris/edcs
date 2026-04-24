<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class ApiService
{
    protected array $config;

    protected string $category;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * Set API config
     */
    public function setConfig(array $config): ApiService
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Set API calling category
     */
    public function setCategory(string $category): ApiService
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Set API headers
     */
    public function setHeaders(array $headers): ApiService
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Set API Header
     */
    public function setAPIHeader(string $header, string $value): ApiService
    {
        $this->headers[$header] = $value;

        return $this;
    }

    /**
     * Make a GET request to a third-party API
     */
    public function get(string $key, ?string $subkey = null, ?array $params = null): mixed
    {
        $url = $this->config['base_url']
            .$this->resolveUri($this->category, $key, $subkey)
            .$this->buildQueryString($params);

        $response = Http::withHeaders($this->headers)->get($url);
        $status = $response->getStatusCode();

        if ($status !== 200) {
            Log::channel('thirdparty')->error('API call failed', [
                'status' => $status,
                'reason' => $response->getReasonPhrase(),
                'url' => $url,
                'config' => $this->config,
            ]);

            return false;
        }

        return $this->getContents($response, true);
    }

    /**
     * Get response content.
     */
    public function getContents($response, bool $decode = true): mixed
    {
        $content = $response->getBody()->getContents();

        return $decode ? json_decode($content) : $content;
    }

    /**
     * Get update time according to various 3rd party formats.
     */
    public function formatSystemUpdateTime($system): mixed
    {
        // Spansh dumps
        if (property_isset($system, 'updateTime')
            && is_string($system->updateTime)
            && $system->updateTime
        ) {
            if (str_contains($system->updateTime, '+')) {
                return substr($system->updateTime, 0, strpos($system->updateTime, '+'));
            }

            return $system->updateTime;
        }

        // EDSM dumps
        if (property_isset($system, 'updateTime')
            && is_object($system->updateTime)
            && $system->updateTime->information
        ) {
            return $system->updateTime->information;
        }

        return now();
    }

    /**
     * Resolve uri from config
     */
    protected function resolveUri(
        string $section,
        string $key,
        ?string $subKey = null
    ): string|false {
        $section = $this->config[$section];
        if ($section && $section[$key]) {

            if (is_array($section[$key]) && $subKey && $section[$key][$subKey]) {

                return $section[$key][$subKey];
            }

            return $section[$key];
        }

        return false;
    }

    /**
     * Build query string for request
     */
    protected function buildQueryString(?array $params = null): string
    {
        if (! $params) {
            return '';
        }

        $i = 0;
        $template = '';
        foreach ($params as $k => $v) {
            $template .= ($i === 0 ? '?' : '&').$k.'='.$v;
            $i++;
        }

        return $template;
    }
}
