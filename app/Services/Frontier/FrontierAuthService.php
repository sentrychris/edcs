<?php

namespace App\Services\Frontier;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Frontier auth client.
 */
class FrontierAuthService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'headers' => [
                'User-Agent' => 'EDCS-v1.0.0',
            ],
            'base_uri' => config('elite.frontier.auth.url'),
        ]);
    }

    /**
     * Generate the authorization details for the Frontier auth server.
     *
     * @return array{authorization_url: string, code_verifier: string}
     */
    public function getAuthorizationServerInformation(): array
    {
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);
        $oauthState = Str::random(32);

        Cache::put("frontier_cv_{$oauthState}", $codeVerifier, 60);

        $url = config('elite.frontier.auth.url').'/auth';
        $url .= '?audience=frontier,steam,epic';
        $url .= '&response_type=code';
        $url .= '&client_id='.config('elite.frontier.auth.client_id');
        $url .= "&code_challenge={$codeChallenge}";
        $url .= '&code_challenge_method=S256';
        $url .= $this->attachAuthorizationScopes(config('elite.frontier.auth.scopes'));
        $url .= "&state={$oauthState}";
        $url .= '&redirect_uri='.route('frontier.auth.callback');

        return [
            'authorization_url' => $url,
            'code_verifier' => $codeVerifier,
        ];
    }

    /**
     * Exchange the authorization code for tokens.
     *
     * @param  Request  $request  - the callback request from Frontier
     * @return object{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function authorize(Request $request): object
    {
        $code = $request->input('code');
        $oauthState = $request->input('state');
        $codeVerifier = Cache::get("frontier_cv_{$oauthState}");

        $response = $this->client->request('POST', '/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => config('elite.frontier.auth.client_id'),
                'code_verifier' => $codeVerifier,
                'code' => $code,
                'redirect_uri' => route('frontier.auth.callback'),
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * Use a refresh token to obtain a new access token.
     *
     * Frontier returns a new access_token, refresh_token, and expires_in.
     * Updates the user's frontier_user record and Redis cache.
     *
     * @param  User  $user  - the user whose token should be refreshed
     * @return string - the new access token
     */
    public function refreshToken(User $user): string
    {
        $frontierUser = $user->frontierUser;

        $response = $this->client->request('POST', '/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'grant_type' => 'refresh_token',
                'client_id' => config('elite.frontier.auth.client_id'),
                'refresh_token' => $frontierUser->refresh_token,
            ],
        ]);

        $auth = json_decode($response->getBody()->getContents());

        $frontierUser->update([
            'access_token' => $auth->access_token,
            'refresh_token' => $auth->refresh_token,
            'token_expires_at' => now()->addSeconds($auth->expires_in),
        ]);

        $this->cacheToken($user->id, $auth->access_token, $auth->expires_in);

        return $auth->access_token;
    }

    /**
     * Decode the token to retrieve the user profile.
     *
     * @param  string  $token  - the access token
     */
    public function decode(string $token): object
    {
        $response = $this->client->request('GET', '/decode', [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * Confirm the user exists in our database, creating them if needed.
     *
     * Persists the access token, refresh token, and expiry from the full auth response.
     *
     * @param  mixed  $frontierProfile  - the decoded Frontier profile
     * @param  object  $auth  - the full token response from Frontier
     */
    public function confirmUser(mixed $frontierProfile, object $auth): User
    {
        $customerId = $frontierProfile->usr->customer_id;
        $email = "{$customerId}@versyx.net";

        $tokenData = [
            'access_token' => $auth->access_token,
            'refresh_token' => $auth->refresh_token,
            'token_expires_at' => now()->addSeconds($auth->expires_in),
        ];

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $customerId, 'password' => bcrypt(Str::random(32))]
        );

        $user->frontierUser()->updateOrCreate(
            ['frontier_id' => $customerId],
            $tokenData
        );

        $this->cacheToken($user->id, $auth->access_token, $auth->expires_in);

        return $user;
    }

    /**
     * Cache the access token in Redis with a TTL slightly shorter than its actual expiry.
     *
     * @param  int  $userId  - the user ID to scope the cache key
     * @param  string  $token  - the access token
     * @param  int  $expiresIn  - seconds until the token expires
     */
    public function cacheToken(int $userId, string $token, int $expiresIn): void
    {
        // Cache 5 minutes less than the real expiry so a Redis miss triggers
        // a DB check while the token is still technically valid.
        $ttl = max($expiresIn - 300, 60);
        Redis::set("user_{$userId}_frontier_token", $token, 'EX', $ttl);
    }

    /**
     * Generate query string for oauth scopes.
     */
    private function attachAuthorizationScopes(array $scopes): string
    {
        $query = '&scope=';
        $count = count($scopes);
        $delim = '%20';
        foreach ($scopes as $name => $key) {
            if (--$count <= 0) {
                $delim = null;
            }
            $query .= $key.$delim;
        }

        return $query;
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
