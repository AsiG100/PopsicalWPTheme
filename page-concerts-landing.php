<?php
/* Template Name: Concerts Landing Page */

require_once __DIR__ . '/main.php';

get_header();

// Path to CSS file
$css_file_path = get_stylesheet_directory_uri() . '/concerts-landing-style.css';

// Initialize variables for event data
$token = getenv('EVENTBRITE_TOKEN') ?: (isset($_ENV['EVENTBRITE_TOKEN']) ? $_ENV['EVENTBRITE_TOKEN'] : null);
$org_id = getenv('EVENTBRITE_ORG_ID') ?: (isset($_ENV['EVENTBRITE_ORG_ID']) ? $_ENV['EVENTBRITE_ORG_ID'] : null);

// Default values for the main displayed event
$current_event_name = "Popsical's Live Concert";
$current_event_date = "Date To Be Announced";
$current_event_time = "Time To Be Announced";
$current_event_location_name = "Venue To Be Announced";
$current_event_location_map_link = "#";
$current_event_ticket_link = "#eventbrite-widget-area"; // Default link to the widget area on the same page
$current_event_id_for_widget = null; // This will hold the ID of the event for the CTA button

$events_to_list_in_main_section = []; // Events for the "Upcoming Concerts in X" or "Details for Y" section
$other_concerts_to_list = []; // Events for the "More Concerts" section

$page_error_message = null;
$all_organization_events = []; // Define to avoid errors if API call fails early

if (!$token || !$org_id) {
    $page_error_message = 'Error: Eventbrite token or organization ID is not configured.';
} else {
    try {
        $sdk = new EventBriteSDK($token);
        $geo = new Geo();
        // Parameters for fetching events - expand venue and ticket_availability
        // 'status' => 'live' is often default in SDKs or can be added.
        // 'order_by' => 'start_asc' ensures chronological order.
        $params = ['expand' => 'venue,ticket_availability', 'status' => 'live', 'order_by' => 'start_asc'];

        $all_organization_events = $sdk->getEventsByOrganization($org_id, $params);
        // Ensure $all_organization_events is an array even if API returns null or error object
        if (!is_array($all_organization_events)) {
            $all_organization_events = [];
            // Potentially log an error here if $sdk->getEventsByOrganization was expected to throw on error
            // For now, we proceed with an empty array to prevent further PHP errors.
            if (empty($page_error_message)) { // Don't overwrite existing critical errors
                 // $page_error_message = "Could not retrieve events from Eventbrite."; // Optional: more specific error
            }
        }

        $events_by_counties = $geo->get_counties_per_events($all_organization_events);

        $utm_county = $_GET['utm_county'] ?? 'LA';

        $featured_event = $events_by_counties[$utm_county][0];
        $local_tz_string = get_option('timezone_string') ?: 'America/Los_Angeles';
        $local_tz = new DateTimeZone($local_tz_string);

        if ($featured_event) {
            $current_event_id_for_widget = $featured_event['id'];
            $current_event_name = $featured_event['name']['text'] ?? ($featured_event['name'] ?? $current_event_name);
            if (isset($featured_event['start']['utc'])) {
                $event_start_datetime = new DateTime($featured_event['start']['utc'], new DateTimeZone('UTC'));
                $event_start_datetime->setTimezone($local_tz);
                $current_event_date = $event_start_datetime->format('F j, Y');
                $current_event_time = $event_start_datetime->format('g:i A T');
            }
            $current_event_location_name = $featured_event['location']['name'] ?? '';
            $current_event_location_address = $featured_event['location']['address'] ?? '';

            if (isset($featured_event['venue']['latitude'], $featured_event['venue']['longitude']) && $featured_event['venue']['latitude'] && $featured_event['venue']['longitude']) {
                 $current_event_location_map_link = "https://www.google.com/maps/search/?api=1&query=" . $featured_event['venue']['latitude'] . "," . $featured_event['venue']['longitude'];
            } elseif ($current_event_location_address !== "Location To Be Announced" && $current_event_location_address !== 'Online Event or Address TBD') {
                 $current_event_location_map_link = "https://www.google.com/maps/search/?api=1&query=" . urlencode($current_event_location_name . ", " . $current_event_location_address);
            }
            $current_event_ticket_link = '#';
        }

        if ($utm_county && isset($events_by_counties[$utm_county])) {
            $events_to_list_in_main_section = $events_by_counties[$utm_county];
        } elseif ($featured_event) {
            $events_to_list_in_main_section = [$featured_event];
        } elseif (!empty($all_organization_events)) {
            $events_to_list_in_main_section = $all_organization_events;
        } else {
            $events_to_list_in_main_section = []; // Ensure it's an array
        }


        $temp_other_concerts = [];
        if(is_array($all_organization_events)){
            foreach ($all_organization_events as $event) {
                if (count($temp_other_concerts) >= 4) break;
                if ($featured_event && $event['id'] == $featured_event['id']) {
                    continue;
                }
                $temp_other_concerts[] = $event;
            }
        }
        $other_concerts_to_list = $temp_other_concerts;

    } catch (Exception $e) {
        $page_error_message = 'An unexpected error occurred: ' . esc_html($e->getMessage());
        // Ensure arrays are initialized to prevent PHP errors in the template part
        $events_to_list_in_main_section = [];
        $other_concerts_to_list = [];
    }
}

// Determine the page title based on context
$page_title = $current_event_name;
if ($utm_county && !$query_event_id) {
    $page_title = "Concerts in " . esc_html(strtoupper($utm_county));
}
$page_title .= " - Popsical";

// Placeholder image paths (replace with actual image paths or use WordPress functions if available)
$theme_img_path = function_exists('get_stylesheet_directory_uri') ? get_stylesheet_directory_uri() . '/images/' : './images/';
$placeholder_large = $theme_img_path . 'event-placeholder-large.jpg';
$gallery_placeholders = [
    $theme_img_path . 'gallery-image-1.jpg',
    $theme_img_path . 'gallery-image-2.jpg',
    $theme_img_path . 'gallery-image-3.jpg',
    $theme_img_path . 'gallery-image-4.jpg',
];

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">

    <title><?php echo esc_html($page_title); ?></title>

    <link rel="stylesheet" href="<?php echo esc_url($css_file_path); ?>" type="text/css" media="all">

    <?php wp_head(); ?>
</head>
<body <?php body_class('concerts-landing'); ?>>
<?php if(function_exists('wp_body_open')) wp_body_open(); ?>

<div class="concerts-page-container">

    <?php if ($page_error_message): ?>
        <div class="page-error-message">
            <p><?php echo $page_error_message; ?></p>
        </div>
    <?php endif; ?>

    <header class="concert-header">
        <h1 class="event-title"><?php echo esc_html($current_event_name); ?></h1>
        <p class="event-date-location">
            <?php echo esc_html($current_event_date); ?> - <?php echo esc_html($current_event_time); ?><br>
            <?php echo esc_html($current_event_location_name); ?>
            <?php if ($current_event_location_address): ?>
                | <?php echo esc_html($current_event_location_address); ?>
            <?php endif; ?>
            <br>
            <?php if ($current_event_location_map_link !== '#'): ?>
                <a href="<?php echo esc_url($current_event_location_map_link); ?>" target="_blank" rel="noopener">Google Map</a>
            <?php endif; ?>
        </p>

        <?php if ($current_event_id_for_widget): ?>
        <button class="cta-button trigger-eventbrite-widget" data-event-id="<?php echo esc_attr($current_event_id_for_widget); ?>">Reserve Your Tickets</button>
        <?php else: ?>
        <p class="cta-button disabled">Tickets Not Currently Available</p>
        <?php endif; ?>
    </header>

    <main class="main-content-area">
        <section class="section about-event">
            <div class="about-event-flex">
            <div class="about-event-text-col">
                <?php
                // Get the WordPress page content
                if (have_posts()) :
                    while (have_posts()) : the_post();
                        the_content();
                    endwhile;
                endif;
                ?>
            </div>    
            <div class="about-event-image-col">
                <img src="<?php echo esc_url($placeholder_large); ?>" alt="Popsical Event Highlight" class="event-highlight-image">
            </div>
            </div>
        </section>

        <section class="section special-features">
            <div class="special-features-video">
                <video controls autoplay muted loop class="special-features-video">
                    <source src="<?php echo esc_url($theme_img_path . 'testimonial-popsical-video.mp4'); ?>" type="video/mp4">
                    Your browser does not support the video tag.    
                </video>
            </div>
            <div class="special-features-text">
                <?php
                // Output the "special_features" custom field if available, otherwise fallback to default content
                $special_features = get_field('special_features');
                if ($special_features) {
                    echo $special_features;
                } 
                ?>
            </div>
        </section>

        <section class="section media-gallery-section">
            <div class="media-gallery">
                <?php foreach($gallery_placeholders as $index => $img_src): ?>
                <div class="media-item"><img src="<?php echo esc_url($img_src); ?>" alt="Gallery Image <?php echo $index + 1; ?>"></div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!$page_error_message && !empty($events_to_list_in_main_section)): ?>
            <section class="section concerts-list-section">
                <h2 class="concerts-list-title">
                    <?php
                    if ($query_event_id && $featured_event) {
                        echo 'Event Details';
                    } elseif ($utm_county) {
                        echo 'Upcoming Concerts in ' . esc_html(strtoupper($utm_county));
                    } else {
                        echo 'Upcoming Concerts';
                    }
                    ?>
                </h2>
                <div class="concerts-list" id="concerts-list">
                    <?php foreach ($events_to_list_in_main_section as $event_item): ?>
                        <?php
                        $item_id = $event_item['id'];
                        $item_name = $event_item['name']['text'] ?? ($event_item['name'] ?? 'Concert Name TBD');
                        $item_location_name = $event_item['venue']['name'] ?? '';
                        $item_location_address = $event_item['venue']['address']['localized_address_display'] ?? 'Location TBD';
                        $item_display_location = $item_location_name;
                        if ($item_location_name && $item_location_address !== 'Location TBD' && $item_location_address !== $item_location_name) {
                            $item_display_location .= ' | ' . $item_location_address;
                        } elseif (!$item_location_name) {
                            $item_display_location = $item_location_address;
                        }

                        $item_date_str = 'Date TBD';
                        if (isset($event_item['start']['utc'])) {
                            $item_dt = new DateTime($event_item['start']['utc'], new DateTimeZone('UTC'));
                            $item_dt->setTimezone($local_tz);
                            $item_date_str = $item_dt->format('M j, Y - g:i A');
                        }
                        ?>
                        <div class="concert-item trigger-eventbrite-widget" data-event-id="<?php echo esc_attr($item_id); ?>">
                            <h2><?php echo esc_html($item_name); ?></h2>
                            <p class="concert-location"><?php echo esc_html($item_display_location); ?></p>
                            <p class="concert-date-time"><?php echo esc_html($item_date_str); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php elseif (!$page_error_message && empty($all_organization_events)): // Check all_organization_events for a general "no events" message ?>
             <section class="section concerts-list-section">
                <p class="text-center">No upcoming concerts found at this time. Please check back soon!</p>
            </section>
        <?php elseif (!$page_error_message): // Specific query yielded no results, but there might be other events ?>
            <section class="section concerts-list-section">
                <p class="text-center">No upcoming concerts found for this specific selection. Check out other concerts below or view all.</p>
            </section>
        <?php endif; ?>

        <div id="eventbrite-widget-area" class="hidden">
            <div id="eventbrite-widget-container"></div>
        </div>

        <?php if (!$page_error_message && !empty($other_concerts_to_list)): ?>
        <section class="section other-concerts-section">
            <h2 class="other-concerts-title">More Concerts</h2>
            <div class="other-concerts-grid">
                <?php foreach ($other_concerts_to_list as $other_event): ?>
                    <?php
                    $other_event_id = $other_event['id'];
                    $other_event_name = $other_event['name']['text'] ?? ($other_event['name'] ?? 'Concert Name TBD');
                    $other_loc_name = $other_event['venue']['name'] ?? '';
                    $other_loc_addr = $other_event['venue']['address']['localized_address_display'] ?? 'Location TBD';
                    $other_display_loc = $other_loc_name;
                     if ($other_loc_name && $other_loc_addr !== 'Location TBD' && $other_loc_addr !== $other_loc_name) {
                        $other_display_loc .= ' | ' . $other_loc_addr;
                    } elseif (!$other_loc_name) {
                        $other_display_loc = $other_loc_addr;
                    }

                    $other_event_datetime_obj = null;
                    if (isset($other_event['start']['utc'])) {
                        $other_event_datetime_obj = new DateTime($other_event['start']['utc'], new DateTimeZone('UTC'));
                        $other_event_datetime_obj->setTimezone($local_tz);
                    }
                    $event_page_link = add_query_arg('event_id', $other_event_id, get_permalink(get_the_ID()));
                    ?>
                    <div class="other-concert-item">
                        <?php if ($other_event_datetime_obj): ?>
                        <div class="date-box">
                            <span class="date-day"><?php echo $other_event_datetime_obj->format('d'); ?></span>
                            <span class="date-month-year"><?php echo $other_event_datetime_obj->format('M Y'); ?></span>
                        </div>
                        <?php endif; ?>
                        <h3><?php echo esc_html($other_event_name); ?></h3>
                        <p>@ <?php echo esc_html($other_display_loc); ?></p>
                        <a href="<?php echo esc_url($event_page_link); ?>" class="details-button">View Details</a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            // Get the URL of the "Concerts" page by its slug
            $concerts_page = get_page_by_path('concerts');
            $concerts_page_url = $concerts_page ? get_permalink($concerts_page->ID) : '/concerts/';
            if (
                $concerts_page_url && is_array($all_organization_events) && 
                count($all_organization_events) > (count($other_concerts_to_list) + ($featured_event ? 1:0)) 
                ):
            ?>
                <div class="all-concerts-link-container">
                    <a href="<?php echo esc_url($concerts_page_url); ?>" class="all-concerts-link">All Concerts</a>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    </main>

</div><!-- .concerts-page-container -->

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const clickableItems = document.querySelectorAll('.trigger-eventbrite-widget');
    const widgetArea = document.getElementById('eventbrite-widget-area');
    const widgetContainer = document.getElementById('eventbrite-widget-container');
    let eventbriteScriptLoaded = false;
    let ebWidgetScriptTag = null; // Keep track of the script tag itself

    function loadEventbriteScript(callback) {
        if (typeof window.EBWidgets !== 'undefined') { // Check if already loaded
            eventbriteScriptLoaded = true; // Ensure flag is set
            if (callback) callback();
            return;
        }
        if (ebWidgetScriptTag && !eventbriteScriptLoaded) { // Script is loading, add to onload
            ebWidgetScriptTag.addEventListener('load', function() {
                 if(!eventbriteScriptLoaded) { // check again in case of race condition
                    eventbriteScriptLoaded = true;
                    if (callback) callback();
                 }
            });
            ebWidgetScriptTag.addEventListener('error', handleScriptError);
            return;
        }
        if(ebWidgetScriptTag && eventbriteScriptLoaded){ // Already loaded via this tag
             if (callback) callback();
             return;
        }


        ebWidgetScriptTag = document.createElement('script');
        ebWidgetScriptTag.src = "https://www.eventbrite.com/static/widgets/eb_widgets.js";
        ebWidgetScriptTag.async = true;

        ebWidgetScriptTag.onload = function() {
            eventbriteScriptLoaded = true;
            if (callback) callback();
        };
        ebWidgetScriptTag.onerror = handleScriptError;
        document.head.appendChild(ebWidgetScriptTag);
    }

    function handleScriptError() {
        console.error("Failed to load Eventbrite widget script.");
        if (widgetArea && widgetContainer) {
            widgetArea.classList.remove('hidden');
            widgetContainer.innerHTML = '<p>Could not load Eventbrite widget script. Please check your internet connection or adblocker.</p>';
        }
    }

    clickableItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default action if it's a link/button
            const eventId = this.dataset.eventId;
            if (!eventId) {
                console.error('Event ID not found for this item.');
                if(widgetContainer) widgetContainer.innerHTML = '<p>Event ID missing. Cannot load widget.</p>';
                if(widgetArea) widgetArea.classList.remove('hidden');
                return;
            }

            if (!widgetArea || !widgetContainer) {
                console.error('Eventbrite widget area or container not found in the DOM.');
                return;
            }

            loadEventbriteScript(function() {
                if (typeof window.EBWidgets === 'undefined') {
                    console.error("Eventbrite EBWidgets object not available even after script load attempt.");
                    widgetContainer.innerHTML = '<p>Error initializing Eventbrite widget. EBWidgets not found.</p>';
                    widgetArea.classList.remove('hidden');
                    widgetContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }
                widgetContainer.innerHTML = '';
                widgetArea.classList.remove('hidden');

                try {
                    window.EBWidgets.createWidget({
                        widgetType: 'checkout',
                        eventId: eventId,
                        iframeContainerId: 'eventbrite-widget-container',
                        iframeContainerHeight: 700,
                        onOrderComplete: function() {
                            console.log('Order complete for event ' + eventId);
                        }
                    });
                } catch (err) {
                    console.error("Error creating Eventbrite widget: ", err);
                    widgetContainer.innerHTML = '<p>Error loading event details. Please try again.</p>';
                }

                setTimeout(() => {
                    const targetElement = document.getElementById('eventbrite-widget-container');
                    if (targetElement) {
                         targetElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }, 200); // Increased delay slightly
            });
        });
    });
});
</script>

<?php 
get_footer()
?>
</body>
</html>