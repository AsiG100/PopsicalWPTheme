<?php
/**
 * Template Name: Concerts Landing Page
 *
 * This page template is used to display active concerts from Eventbrite.
 */

// Ensure the SDK is loaded (it should be via functions.php)
if (!class_exists('EventBriteSDK')) {
    echo '<p>Error: EventBriteSDK not found. Please ensure it is included.</p>';
    return;
}

// --- Configuration - Replace with your actual data or retrieve dynamically ---
// It's recommended to store sensitive data like API tokens securely,
// e.g., using WordPress options, environment variables, or a constants file.
$eventbrite_token = defined('EVENTBRITE_API_TOKEN') ? EVENTBRITE_API_TOKEN : 'YOUR_EVENTBRITE_TOKEN';
$organization_id = defined('EVENTBRITE_ORGANIZATION_ID') ? EVENTBRITE_ORGANIZATION_ID : 'YOUR_ORGANIZATION_ID';
// --- End Configuration ---

if ($eventbrite_token === 'YOUR_EVENTBRITE_TOKEN' || $organization_id === 'YOUR_ORGANIZATION_ID') {
    echo '<p>Please configure your Eventbrite API Token and Organization ID.</p>';
    // You might want to hide this message on a live site or provide instructions.
}

$sdk = new EventBriteSDK($eventbrite_token);

// Define parameters for the API call
// To get only specific fields, Eventbrite API typically uses 'expand' or a similar mechanism.
// Since I cannot verify the exact parameter name, I will fetch all event data first,
// and then extract the required fields (id, name, location).
// Common status for live/active events is 'live'.
$params = [
    'order_by' => 'start_asc', // Order by start date
];
$status = 'live'; // Fetch only live events

echo "<h1>Active Concerts</h1>";

try {
    $response = $sdk->getEventsByOrganization($organization_id, $params, $status);

    if (isset($response['error'])) {
        echo "<p>Error fetching events: " . htmlspecialchars($response['error']) . "</p>";
    } elseif (empty($response['events'])) {
        echo "<p>No active concerts found.</p>";
    } else {
        echo "<ul>";
        foreach ($response['events'] as $event) {
            $event_id = isset($event['id']) ? htmlspecialchars($event['id']) : 'N/A';
            $event_name = isset($event['name']['text']) ? htmlspecialchars($event['name']['text']) : (isset($event['name']) && is_string($event['name']) ? htmlspecialchars($event['name']) : 'N/A');

            $location_display = 'N/A';
            if (isset($event['venue'])) { // Eventbrite often has venue details in a 'venue' object
                $venue = $event['venue'];
                if (isset($venue['name']) && isset($venue['address']['localized_address_display'])) {
                    $location_display = htmlspecialchars($venue['name'] . ' - ' . $venue['address']['localized_address_display']);
                } elseif (isset($venue['name'])) {
                    $location_display = htmlspecialchars($venue['name']);
                } elseif (isset($venue['address']['localized_address_display'])) {
                    $location_display = htmlspecialchars($venue['address']['localized_address_display']);
                } else {
                    // Fallback if specific venue fields are not available
                    $location_parts = [];
                    if(!empty($venue['address']['address_1'])) $location_parts[] = $venue['address']['address_1'];
                    if(!empty($venue['address']['city'])) $location_parts[] = $venue['address']['city'];
                    if(!empty($venue['address']['country'])) $location_parts[] = $venue['address']['country'];
                    if(!empty($location_parts)) $location_display = htmlspecialchars(implode(', ', $location_parts));
                }
            } elseif (isset($event['location']['address'])) { // A more generic location field
                 $location_display = htmlspecialchars($event['location']['address']);
            }


            echo "<li>";
            echo "<strong>ID:</strong> " . $event_id . "<br>";
            echo "<strong>Name:</strong> " . $event_name . "<br>";
            echo "<strong>Location:</strong> " . $location_display . "<br>";
            echo "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p>An unexpected error occurred: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>
