<?php
/* Template Name: Concerts Landing Page */

require_once __DIR__ . '/main.php';

get_header();

// Path to files
$css_file_path = get_stylesheet_directory_uri() . '/styles/concerts-landing-style.css';
$css_countdown_path = get_stylesheet_directory_uri() . '/styles/countDown.min.css';
$css_gallery_path = get_stylesheet_directory_uri() . '/styles/galleryCarousel.css';
$script_countdown_path = get_stylesheet_directory_uri() . '/scripts/countDown.min.js';
$script_gallery_path = get_stylesheet_directory_uri() . '/scripts/galleryCarousel.js';

// Initialize variables for event data
$token = getenv('EVENTBRITE_TOKEN') ?: (isset($_ENV['EVENTBRITE_TOKEN']) ? $_ENV['EVENTBRITE_TOKEN'] : null);
$org_id = getenv('EVENTBRITE_ORG_ID') ?: (isset($_ENV['EVENTBRITE_ORG_ID']) ? $_ENV['EVENTBRITE_ORG_ID'] : null);

// Default values for the main displayed event
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

        // Find the first event in the county that is NOT sold out, otherwise fallback to the first event
        $featured_event = null;
        if (!empty($events_by_counties[$utm_county])) {
            foreach ($events_by_counties[$utm_county] as $event) {
            if (
                !isset($event['sales_status']) ||
                $event['sales_status'] === 'on_sale'
            ) {
                $featured_event = $event;
                break;
            }
            }
            // If all are sold out, just pick the first one
            if (!$featured_event) {
            $featured_event = $events_by_counties[$utm_county][0];
            }
        }

        $local_tz_string = get_option('timezone_string') ?: 'America/Los_Angeles';
        $local_tz = new DateTimeZone($local_tz_string);

        if ($featured_event) {
            $current_event_id_for_widget = $featured_event['id'];
            $current_event_name = $featured_event['name']['text'] ?? ($featured_event['name'] ?? "Popsical's Live Concert");
            $current_event_date = $featured_event['date']['local'] ?? ($featured_event['date']['local'] ?? "Date To Be Announced");
            $current_event_time = $featured_event['time']['text'] ?? ($featured_event['time']['text'] ?? "Time To Be Announced");
            $current_event_location_name = $featured_event['location']['name'] ?? "Venue To Be Announced";
            $current_event_location_address = $featured_event['location']['address'] ?? 'Location To Be Announced';

            if (isset($featured_event['date']['utc'])) {
                $event_start_datetime = new DateTime($featured_event['date']['utc']);
                $event_start_datetime->setTimezone($local_tz);
                $current_event_date = $event_start_datetime->format('F j, Y');
                $current_event_time = $event_start_datetime->format('g:i A T');
            }

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

       // First, add all current county's concerts (excluding featured_event if present)
        $temp_other_concerts = [];
        $added_ids = [];

        // Then, add all the rest (from all_organization_events), skipping already added events
        if (is_array($all_organization_events)) {
            foreach ($all_organization_events as $event) {
                if (isset($added_ids[$event['id']]) && $temp_other_concerts[$event['id']]['sales_status'] != 'unavailable') {
                    continue;
                }
                
                $temp_other_concerts[$event['id']] = $event;
                $added_ids[$event['id']] = true;
            }
        }

        // Limit to 4 "other" concerts
        $events_to_list_in_main_section = $other_concerts_to_list = array_slice($temp_other_concerts, 0, 4, true);

    } catch (Exception $e) {
        $page_error_message = 'An unexpected error occurred: ' . esc_html($e->getMessage());
        // Ensure arrays are initialized to prevent PHP errors in the template part
        $events_to_list_in_main_section = [];
        $other_concerts_to_list = [];
    }
}

// Determine the page title based on context
$page_title = $current_event_name;
if ($utm_county) {
    $page_title = "Concerts in " . esc_html(strtoupper($utm_county));
}
$page_title .= " - Popsical";

// Placeholder image paths (replace with actual image paths or use WordPress functions if available)
$theme_img_path = function_exists('get_stylesheet_directory_uri') ? get_stylesheet_directory_uri() . '/images/' : './images/';
$placeholder_large = $theme_img_path . 'event-placeholder-large.jpg';
$gallery_placeholders = [];
for ($i = 1; $i <= 12; $i++) {
    $img_path = $theme_img_path . "gallery-image-$i.jpg";
    $img_file = get_stylesheet_directory() . "/images/gallery-image-$i.jpg";
    if (file_exists($img_file)) {
        $gallery_placeholders[] = $img_path;
    }else{
        break;
    }
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">

    <title><?php echo esc_html($page_title); ?></title>

    <link rel="stylesheet" href="<?php echo esc_url($css_file_path).'?v=' . $v; ?>" type="text/css" media="all">
    <link rel="stylesheet" href="<?php echo esc_url($css_countdown_path).'?v=' . $v; ?>" type="text/css" media="all">
    <link rel="stylesheet" href="<?php echo esc_url($css_gallery_path).'?v=' . $v; ?>" type="text/css" media="all">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

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
        <?php if ($current_event_date && $current_event_date !== "Date To Be Announced"): ?>
            <div class="date-box"><?php echo esc_html($current_event_date); ?></div>
        <?php endif; ?>
        <p class="event-date-location">
            <?php echo esc_html($current_event_location_name); ?>
            <?php if ($current_event_location_address): ?>
                | <?php echo esc_html($current_event_location_address); ?>
            <?php endif; ?>
            <br>
            <?php if ($current_event_location_map_link !== '#'): ?>
                <a href="<?php echo esc_url($current_event_location_map_link); ?>" target="_blank" rel="noopener">📍Google Map</a>
            <?php endif; ?>
        </p>

        <?php if ($current_event_id_for_widget): ?>
        <button class="cta-button trigger-eventbrite-widget" data-event-id="<?php echo esc_attr($current_event_id_for_widget); ?>">Reserve Your Tickets</button>
        <?php else: ?>
        <p class="cta-button disabled">Tickets Not Currently Available</p>
        <?php endif; ?>
        
        <div class="countdown-container"></div>
        
    </header>

    <main class="main-content-area">

        <?php if (!$page_error_message && !empty($events_to_list_in_main_section)): ?>
            <section class="section concerts-list-section">
                <h2 class="concerts-list-title">
                    <?php
                    if ($featured_event) {
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
                        $item_location_name = $event_item['location']['name'] ?? '';
                        $item_location_address = $event_item['location']['address'] ?? 'Location TBD';
                        $item_display_location = $item_location_name;
                        if ($item_location_name && $item_location_address !== 'Location TBD' && $item_location_address !== $item_location_name) {
                            $item_display_location .= ' | ' . $item_location_address;
                        } elseif (!$item_location_name) {
                            $item_display_location = $item_location_address;
                        }

                        $item_date_str = 'Date TBD';
                        if (isset($event_item['date']['utc'])) {
                            $item_dt = new DateTime($event_item['date']['utc'], new DateTimeZone('UTC'));
                            $item_dt->setTimezone($local_tz);
                            $item_date_str = $item_dt->format('M j, Y - g:i A');
                        }
                        $event_datetime_obj = null;
                        if (isset($event_item['date']['utc'])) {
                            $event_datetime_obj = new DateTime($event_item['date']['utc'], new DateTimeZone('UTC'));
                            $event_datetime_obj->setTimezone($local_tz);
                        }
                        $event_page_link = add_query_arg('event_id', $event_item['id'], get_permalink(get_the_ID()));
                        ?>
                        
                        <div class="concert-item">
                            <?php if ($event_datetime_obj): ?>
                            <div class="date-box">
                                <span class="date-day"><?php echo $event_datetime_obj->format('d'); ?></span>
                                <span class="date-month-year"><?php echo $event_datetime_obj->format('M Y'); ?></span>
                            </div>
                            <?php endif; ?>
                            <h2><?php echo esc_html($item_name); ?></h2>
                            <?php
                            // Build Google Maps link if possible
                            $map_link = '#';
                            if (isset($event_item['venue']['latitude'], $event_item['venue']['longitude']) && $event_item['venue']['latitude'] && $event_item['venue']['longitude']) {
                                $map_link = "https://www.google.com/maps/search/?api=1&query=" . $event_item['venue']['latitude'] . "," . $event_item['venue']['longitude'];
                            } elseif ($item_location_address !== "Location TBD" && $item_location_address !== 'Online Event or Address TBD') {
                                $map_link = "https://www.google.com/maps/search/?api=1&query=" . urlencode($item_display_location);
                            }
                            ?>
                            <a href="<?php echo esc_url($map_link); ?>" class="concert-location" target="_blank" rel="noopener">
                                <?php echo esc_html($item_display_location); ?>
                            </a>
                            <?php
                            $sales_status = $event_item['sales_status'];
                            $is_available = ($sales_status === 'on_sale');
                            $is_sold_out = ($sales_status === 'unavailable');
                            ?>
                            <?php if ($is_available): ?>
                                <a href="<?php echo esc_url($event_page_link); ?>" class="details-button trigger-eventbrite-widget" data-event-id="<?php echo esc_attr($item_id); ?>">Reserve Your Spot</a>
                            <?php elseif ($is_sold_out): ?>
                                <span class="details-button disabled sold-out">Sold Out</span>
                            <?php else: ?>
                                <span class="details-button disabled unavailable">Unavailable</span>
                            <?php endif; ?>
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

        <div id="eventbrite-widget-area" class="hidden">
            <div id="eventbrite-widget-container"></div>
        </div>

        <section class="section media-gallery-section">
            <div class="media-gallery">
                <?php foreach($gallery_placeholders as $index => $img_src): ?>
                <div class="media-item"><img src="<?php echo esc_url($img_src); ?>" alt="Gallery Image <?php echo $index + 1; ?>"></div>
                <?php endforeach; ?>
            </div>
        </section>


        <?php if (!$page_error_message && !empty($other_concerts_to_list)): ?>
        <section class="section other-concerts-section">
            <h2 class="other-concerts-title">More Concerts</h2>
            <div class="other-concerts-grid">
                <?php foreach ($other_concerts_to_list as $other_event): ?>
                    <?php
                    $other_event_id = $other_event['id'];
                    $other_event_name = $other_event['name']['text'] ?? ($other_event['name'] ?? 'Concert Name TBD');
                    $other_loc_name = $other_event['location']['name'] ?? '';
                    $other_loc_addr = $other_event['location']['address'] ?? 'Location TBD';
                    $other_display_loc = $other_loc_name;
                     if ($other_loc_name && $other_loc_addr !== 'Location TBD' && $other_loc_addr !== $other_loc_name) {
                        $other_display_loc .= ' | ' . $other_loc_addr;
                    } elseif (!$other_loc_name) {
                        $other_display_loc = $other_loc_addr;
                    }

                    $other_event_datetime_obj = null;
                    if (isset($other_event['date']['utc'])) {
                        $other_event_datetime_obj = new DateTime($other_event['date']['utc'], new DateTimeZone('UTC'));
                        $other_event_datetime_obj->setTimezone($local_tz);
                    }
                    $event_page_link = add_query_arg('event_id', $other_event_id, get_permalink(get_the_ID()));
                    ?>
                    <div class="other-concert-item " data-event-id="<?php echo esc_attr($other_event_id); ?>">
                        <?php if ($other_event_datetime_obj): ?>
                        <div class="date-box">
                            <span class="date-day"><?php echo $other_event_datetime_obj->format('d'); ?></span>
                            <span class="date-month-year"><?php echo $other_event_datetime_obj->format('M Y'); ?></span>
                        </div>
                        <?php endif; ?>
                        <h3><?php echo esc_html($other_event_name); ?></h3>
                        <?php
                        // Build Google Maps link if possible
                        $map_link = '#';
                        if (isset($other_event['location']['latitude'], $other_event['location']['longitude']) && $other_event['location']['latitude'] && $other_event['location']['longitude']) {
                            $map_link = "https://www.google.com/maps/search/?api=1&query=" . $other_event['location']['latitude'] . "," . $other_event['location']['longitude'];
                        } elseif ($other_loc_addr !== "Location TBD" && $other_loc_addr !== 'Online Event or Address TBD') {
                            $map_link = "https://www.google.com/maps/search/?api=1&query=" . urlencode($other_display_loc);
                        }
                        ?>
                        <a href="<?php echo esc_url($map_link); ?>" class="concert-location" target="_blank" rel="noopener">
                            @ <?php echo esc_html($other_display_loc); ?>
                        </a>
                        <?php
                        $sales_status = $other_event['sales_status'];
                        $is_available = ($sales_status === 'on_sale');
                        $is_sold_out = ($sales_status === 'unavailable');
                        ?>
                        <?php if ($is_available): ?>
                            <a href="<?php echo esc_url($event_page_link); ?>" class="details-button trigger-eventbrite-widget" data-event-id="<?php echo esc_attr($other_event_id); ?>">Reserve Your Spot</a>
                        <?php elseif ($is_sold_out): ?>
                            <span class="details-button disabled sold-out">Sold Out</span>
                        <?php else: ?>
                            <span class="details-button disabled unavailable">Unavailable</span>
                        <?php endif; ?>
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

<script src="<?php echo esc_url($script_countdown_path); ?>"></script>
<script src="<?php echo esc_url($script_gallery_path); ?>"></script>
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

var endDate = '<?php echo isset($featured_event['date']['local']) ? $featured_event['date']['local'] : 'null'; ?>';

var cd = new Countdown({
    cont: document.querySelector('.countdown-container'),
    date: new Date(endDate).getTime(), // Ensure endDate is a valid timestamp
    outputTranslation: {
        day: 'Days',
        hour: 'Hours',
        minute: 'Minutes',
    },
    endCallback: null,
    outputFormat: 'day|hour|minute',
});
cd.start();
</script>

<?php 
get_footer()
?>
</body>
</html>