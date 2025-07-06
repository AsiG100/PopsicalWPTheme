<?php
/**
 * EventBrite SDK for PHP
 * 
 * This SDK provides a simple interface to interact with the EventBrite API.
 * It allows fetching events, event details, and attendees using an API token.
 */

class EventBriteSDK
{
    private $token;
    private $apiUrl = 'https://www.eventbriteapi.com/v3/';

    public function __construct($token)
    {
        $this->token = $token;
    }

    private function request($endpoint, $params = [])
    {
        $url = $this->apiUrl . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception('Request Error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($httpcode >= 400) {
            throw new Exception('API Error: ' . $response);
        }

        return json_decode($response, true);
    }

    // Fetch events by organization ID
    public function getEventsByOrganization($organizationId, $params = [])
    {
        $cacheKey = md5("org_{$organizationId}_" . serialize($params));
        $cacheFile = sys_get_temp_dir() . "/eventbrite_cache_{$cacheKey}.json";
        $cacheTtl = 300; // cache for 5 minutes

        if (file_exists($cacheFile) && (filemtime($cacheFile) + $cacheTtl > time())) {
            $data = file_get_contents($cacheFile);
            if ($data !== false) {
                return json_decode($data, true);
            }
        }
        try {
            $response = $this->request("organizations/{$organizationId}/events/", $params);
            file_put_contents($cacheFile, json_encode($response));
            return $response;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}