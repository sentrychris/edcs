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

    /**
     * APIManager constructor.
     *
     * @param  string|null  $server
     */
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
     * @return array - the authorization details
     */
    public function getAuthorizationServerInformation(): array
    {
        // Generate the oauth code verifier, challenge and state parameter
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);
        $oauthState = Str::random(32);

        // Cache the code verifier for 1 minute, use the oauth state in the key
        // so that we can compare it with the oauth state we receive from the
        // Frontier callback request
        Cache::put("frontier_cv_{$oauthState}", $codeVerifier, 60);

        // Construct the oauth URL
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
     * Callback method for Frontier auth.
     *
     * This method is called when the Frontier auth server redirects back to the application,
     * it retrieves the authorization code and exchanges it for an access token.
     *
     * @param  Request  $request  - the request object
     * @return mixed - the response
     */
    public function authorize(Request $request): mixed
    {
        // Get the auth details from the request
        $code = $request->input('code');
        $oauthState = $request->input('state');

        // Retrieve the code verifier from the cache based on the oauth state
        $codeVerifier = Cache::get("frontier_cv_{$oauthState}");

        // Use it to obtain a valid access token
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
     * Decode the token to retrieve the user profile.
     *
     * @param  string  $token  - the access token
     * @return mixed - the user profile
     */
    public function decode(string $token)
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
     *  Confirm the user.
     *
     * @param  mixed  $frontierProfile  - the user details from the decoded token
     * @param  string  $accessToken  - the access token
     * @return User - the user model
     */
    public function confirmUser(mixed $frontierProfile, string $accessToken): User
    {
        $email = $frontierProfile->usr->customer_id.'@versyx.net';
        $user = User::whereEmail($email)->first();

        if (! $user) {
            // If the user does not exist, create a new user
            $user = User::create([
                'name' => $frontierProfile->usr->customer_id,
                'email' => $email,
                'password' => bcrypt(Str::random(32)),
            ]);

            // Create a new associated Frontier user
            $user->frontierUser()->create([
                'frontier_id' => $frontierProfile->usr->customer_id,
                'access_token' => $accessToken,
            ]);
        }

        if ($user->frontierUser) {
            // Update the Frontier user's access token
            $user->frontierUser()->update([
                'access_token' => $accessToken,
            ]);
        } else {
            // Just in case the user does exist but does not have an associated Frontier user
            $user->frontierUser()->create([
                'frontier_id' => $frontierProfile->usr->customer_id,
                'access_token' => $accessToken,
            ]);
        }

        Redis::set("user_{$user->id}_frontier_token", $accessToken, 'EX', 3600 * 3);

        return $user;
    }

    /**
     * Generate query string for oauth scopes.
     *
     * @param  array  $scopes  - the scopes to attach
     * @return string - the query string
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

    /**
     * Generate a secure random string for the code verifier.
     *
     * @return string - the code verifier
     */
    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Generate the code challenge from the code verifier.
     *
     * @param  string  $codeVerifier  - the code verifier
     * @return string - the code challenge
     */
    private function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
