<?php
/* Template Name: Concerts Landing Page */

require_once __DIR__ . '/main.php';

// get_header();

?>

<style>
    .concerts-page-container {
        max-width: 900px;
        margin: 20px auto;
        padding: 20px;
        font-family: Arial, sans-serif;
    }
    .concerts-title {
        text-align: center;
        margin-bottom: 30px;
    }
    .concerts-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .concert-item {
        border: 1px solid #ddd;
        padding: 15px;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .concert-item:hover {
        background-color: #f0f0f0;
    }
    .concert-item h2 {
        margin-top: 0;
        margin-bottom: 5px;
        font-size: 1.5em;
    }
    .concert-location {
        font-size: 0.9em;
        color: #555;
    }
    #eventbrite-widget-container {
        margin-top: 30px;
        border-top: 2px solid #eee;
        padding-top: 20px;
    }
    .hidden {
        display: none;
    }
</style>

<div class="concerts-page-container">
    <h1 class="concerts-title"><?php 
    'Upcoming Concerts'; 
    ?></h1>

    <div class="concerts-list" id="concerts-list">
        <?php
        $token = getenv('EVENTBRITE_TOKEN') ?: (isset($_ENV['EVENTBRITE_TOKEN']) ? $_ENV['EVENTBRITE_TOKEN'] : null);
        $org_id = getenv('EVENTBRITE_ORG_ID') ?: (isset($_ENV['EVENTBRITE_ORG_ID']) ? $_ENV['EVENTBRITE_ORG_ID'] : null);

        if (!$token || !$org_id) {
            echo '<p>Error: Eventbrite token or organization ID is not configured. Please set EVENTBRITE_TOKEN and EVENTBRITE_ORG_ID in your .env file or server environment.</p>';
        } else {
            try {
                $sdk = new EventBriteSDK($token);
                $geo = new Geo();
                // Parameters for fetching events (status and expand are handled by the SDK)
                $params = [];
                $events = $sdk->getEventsByOrganization($org_id, $params);
                $events_by_counties = $geo->assign_events_to_counties($events);
                
                // Pull the events from the county in the utm
                $utm_county = $_GET['utm_county'] ?? 'LA';
                if ($utm_county && isset($events_by_counties[$utm_county])) {
                    $events = $events_by_counties[$utm_county];
                }

                if (isset($response['error'])) {
                    echo '<p>Error fetching events: ' . esc_html($response['error']) . '</p>';
                } elseif (isset($events) && !empty($events)) {
                    foreach ($events as $event) {
                        $event_id = $event['id'];
                        $event_name = $event['name'];
                        $location = $event['location']['address'] ?? 'Location not available';

                        echo '<div class="concert-item" data-event-id="' . $event_id . '">';
                        echo '<h2>' . $event_name . '</h2>';
                        echo '<p class="concert-location">' . $location . '</p>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>No upcoming concerts found.</p>';
                }
            } catch (Exception $e) {
                echo '<p>An unexpected error occurred: ' . esc_html($e->getMessage()) . '</p>';
            }
        }
        ?>
    </div>

    <div id="eventbrite-widget-area" class="hidden">
        <!-- Eventbrite widget will be loaded here -->
        <div id="eventbrite-widget-container"></div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const concertItems = document.querySelectorAll('.concert-item');
    const widgetArea = document.getElementById('eventbrite-widget-area');
    const widgetContainer = document.getElementById('eventbrite-widget-container');

    // Load the Eventbrite widget script once
    var script = document.createElement('script');
    script.src = "https://www.eventbrite.com/static/widgets/eb_widgets.js";
    script.async = true;
    document.head.appendChild(script);

    script.onload = function() { // Ensure EBWidgets is available
        concertItems.forEach(item => {
            item.addEventListener('click', function() {
                const eventId = this.dataset.eventId;
                if (!eventId) {
                    console.error('Event ID not found for this item.');
                    return;
                }

                // Clear previous widget content if any
                widgetContainer.innerHTML = '';

                // Make sure the widget area is visible
                widgetArea.classList.remove('hidden');

                try {
                    window.EBWidgets.createWidget({
                        widgetType: 'checkout', // Using 'checkout' as it's a common type. For just details, a different approach might be needed or this is fine.
                        eventId: eventId,
                        iframeContainerId: 'eventbrite-widget-container', // The ID of the div where the widget should be loaded
                        iframeContainerHeight: 600, // Adjust height as needed
                        onOrderComplete: function() {
                            console.log('Order complete!'); // Optional callback
                        }
                    });
                } catch (e) {
                    console.error("Error creating Eventbrite widget: ", e);
                    widgetContainer.innerHTML = '<p>Error loading event details. Please try again.</p>';
                }

                // Scroll to the widget
                widgetContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    };
    script.onerror = function() {
        console.error("Failed to load Eventbrite widget script.");
        widgetArea.classList.remove('hidden');
        widgetContainer.innerHTML = '<p>Could not load Eventbrite widget script. Please check your internet connection or adblocker.</p>';
    };
});
</script>

<?php 
// get_footer(); 
?>