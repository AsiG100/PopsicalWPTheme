<?php
/* Template Name: Concerts Landing Page */

get_header(); ?>

<div class="concerts-page-container" style="max-width: 900px; margin: 0 auto; padding: 40px 0;">
    <h1 class="concerts-title"><?php the_title(); ?></h1>
    <div class="concerts-list">
        <?php
        // Query all 'concerts' from eventbrite throuh the sdk in EV-SDK.php
        $token = getenv('TOKEN');
        $org_id = getenv('ORG_ID');
        if (!$token || !$org_id) {
            echo '<p>Error: Missing Eventbrite token or organization ID.</p>';
            return;
        }

        $sdk = new EventBriteSDK($token);
        $events = $sdk->getEventsByOrganization($org_id);
        print_r($events);

        // Check if events are returned and display them
        if (isset($events['error'])) {
            echo '<p>Error fetching events: ' . esc_html($events['error']) . '</p>';
            return;
        }
        
        // Display events
        if (isset($events['events']) && is_array($events['events'])) {
            $events = $events; // Assign the events array for further processing
        } else {
            echo '<p>No events found.</p>';
            return;
        }
        // Check if events are available    

        if (!empty($events) && isset($events['events']) && count($events['events']) > 0) :
            foreach ($events['events'] as $event) : ?>
            <div class="concert-item" style="display: flex; align-items: flex-end; margin-bottom: 40px;">
                <?php if (!empty($event['logo']['url'])) : ?>
                <div class="concert-image" style="flex: 0 0 250px; margin-right: 30px;">
                    <img src="<?php echo esc_url($event['logo']['url']); ?>" alt="<?php echo esc_attr($event['name']['text']); ?>" style="width:100%;height:auto;border-radius:8px;">
                </div>
                <?php endif; ?>
                <div class="concert-info" style="flex: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                <div style="background: #f7f7f7; padding: 20px; border-radius: 8px;">
                    <h2 style="margin-top:0;"><?php echo esc_html($event['name']['text']); ?></h2>
                    <div class="concert-meta" style="font-size: 0.95em; color: #666;">
                    <?php
                    $date = !empty($event['start']['local']) ? date('F j, Y, g:i a', strtotime($event['start']['local'])) : '';
                    $venue = !empty($event['venue']['address']['localized_address_display']) ? $event['venue']['address']['localized_address_display'] : '';
                    if ($date) echo '<div><strong>Date:</strong> ' . esc_html($date) . '</div>';
                    if ($venue) echo '<div><strong>Location:</strong> ' . esc_html($venue) . '</div>';
                    ?>
                    </div>
                    <div class="concert-description" style="margin-top: 10px;">
                    <?php echo !empty($event['description']['text']) ? esc_html($event['description']['text']) : ''; ?>
                    </div>
                </div>
                </div>
            </div>
            <?php endforeach;
        else : ?>
            <p>No concerts found.</p>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>