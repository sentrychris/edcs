<?php

namespace App\Services\Frontier;

use App\Models\System;
use App\Models\User;
use App\Services\Edsm\EdsmSystemService;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Frontier CAPI client.
 */
class FrontierCApiService
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
            'base_uri' => config('elite.frontier.capi.url'),
        ]);
    }

    /**
     * Get the commander's profile information.
     *
     * @param  string  $token  - the access token
     * @return mixed - the user profile
     */
    public function getCommanderProfile(User $user)
    {
        try {
            $response = $this->client->request('GET', '/profile', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getFrontierToken($user),
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return null;
        }
    }

    /**
     * Confirm the user's commander profile.
     *
     * @param  User  $user  - the user model
     */
    public function confirmCommander(User $user): mixed
    {
        // Get the commander profile
        $profile = $this->getCommanderProfile($user);
        if (! property_isset($profile, 'commander')) {
            throw new Exception('Commander profile not found.');
        }

        // Update or create the user's commander profile
        $commander = $profile->commander;
        $user->commander()->updateOrCreate([
            'cmdr_name' => $commander->name,
        ], [
            'cmdr_name' => $commander->name,
            'credits' => $commander->credits,
            'debt' => $commander->debt,
            'alive' => $commander->alive,
            'docked' => $commander->docked,
            'onfoot' => $commander->onfoot,
            'rank' => json_encode($commander->rank),
        ]);

        // Check the commander's last system and add to our records if it does not exist
        if (property_isset($profile, 'lastSystem')) {
            $lastSystem = $profile->lastSystem;
            $system = System::whereId64($lastSystem->id)
                ->whereName($lastSystem->name)
                ->first();

            if (! $system) {
                // Resolve EDSM API service from the container to update the system data
                $system = app(EdsmSystemService::class)->updateSystem($lastSystem->name);
            }

            // Update the commander's last system
            $user->commander()->update([
                'last_system_id64' => $system->id64,
            ]);
        }

        return $profile;
    }

    /**
     * Get the user's journal logs from CAPI.
     *
     * @return array
     */
    public function getJournal(User $user, mixed $year = '', mixed $month = '', mixed $day = '')
    {
        try {
            $uri = '/journal'
                .($year ? "/{$year}" : '')
                .($month ? "/{$month}" : '')
                .($day ? "/{$day}" : '');

            $response = $this->client->request('GET', $uri, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getFrontierToken($user),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $content = $response->getBody()->getContents();

            // Split the string by newlines (or any other delimiter)
            $jsonObjects = preg_split('/\r\n|\r|\n/', $content);

            // Decode each JSON object separately
            $decoded = [];
            foreach ($jsonObjects as $json) {
                if (! empty(trim($json))) {  // Make sure to skip empty lines
                    $decoded[] = json_decode($json, true);
                }
            }

            return $decoded;
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return [];
        }
    }
    
    /**
     * Get the user's active community goals.
     * 
     * @param User $user
     * @return array
     */
    public function getCommunityGoals(User $user)
    {
        try {
            $uri = '/communitygoals';

            $response = $this->client->request('GET', $uri, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->getFrontierToken($user),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $content = $response->getBody()->getContents();

            return $content ? json_decode($content) : [];
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return [];
        }
    }

    /**
     * Get the user's Frontier token.
     *
     * We use BFF to proxy auth between the frontend and Frontier, through the backend. So we
     * need to get the sanctum-authenticated user's Frontier token to make requests to CAPI.
     */
    private function getFrontierToken(User $user): string
    {
        return Redis::get("user_{$user->id}_frontier_token");
    }
}
