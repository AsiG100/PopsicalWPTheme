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
        // Ensure 'status' is 'live' to fetch active concerts
        $params['status'] = 'live';
        // Ensure 'expand' includes 'venue' to get location details
        if (isset($params['expand'])) {
            if (strpos($params['expand'], 'venue') === false) {
                $params['expand'] .= ',venue';
            }
        } else {
            $params['expand'] = 'venue';
        }

        $cacheKey = md5("org_{$organizationId}_" . serialize($params));
        $cacheFile = "/eventbrite_cache_{$cacheKey}.json";
        $cacheTtl = 300; // cache for 5 minutes

        if (file_exists($cacheFile) && (filemtime($cacheFile) + $cacheTtl > time())) {
            $data = file_get_contents($cacheFile);
            if ($data !== false) {
                $decoded_data = json_decode($data, true);
                // Ensure the cached data is not an error response from a previous failed attempt
                if (isset($decoded_data['events'])) {
                    return $decoded_data;
                }
            }
        }
        try {
            // The endpoint already returns id and name by default.
            // Location is included via venue expansion.
            $response = $this->request("organizations/{$organizationId}/events/", $params);

            // Filter and structure the events data if necessary
            // For now, we assume the API returns id, name, and venue (for location)
            // in a usable format within the 'events' array.
            // If specific fields are needed, further processing can be added here.
            // Example:
            // $filteredEvents = [];
            // if (isset($response['events'])) {
            //     foreach ($response['events'] as $event) {
            //         $filteredEvents[] = [
            //             'id' => $event['id'],
            //             'name' => $event['name']['text'], // Assuming name is in text field
            //             'location' => isset($event['venue']['address']['localized_address_display']) ? $event['venue']['address']['localized_address_display'] : 'N/A',
            //         ];
            //     }
            //     $response['events'] = $filteredEvents; // Replace events with filtered ones
            // }

            file_put_contents($cacheFile, json_encode($response));
            return $response;
        } catch (Exception $e) {
            // Cache the error response to avoid repeated failing calls for a short period
            $errorResponse = ['error' => $e->getMessage()];
            file_put_contents($cacheFile, json_encode($errorResponse)); // Cache error for a shorter time or handle differently
            return $errorResponse;
        }
    }
}
// Looing for .env at the root directory
$token = 'B3VSAB7PW6JDAA7PVPPN'; // Replace with your Eventbrite API token
$org_id = '2600432847471';
$sdk = new EventBriteSDK($token);
// Parameters for fetching events (status and expand are handled by the SDK)
$params = [];
$response = $sdk->getEventsByOrganization($org_id, $params);