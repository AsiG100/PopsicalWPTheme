<?php

class Geo
{
    // Define a constant array of counties with name and polygon (GeoJSON format)
    public const COUNTIES = [
        [
            'name' => 'Los Angeles County',
            'code' => 'LA',
            'polygon' => [
                [
                    [-118.7200586, 34.5837014],
                    [-118.6684040, 33.7036520],
                    [-117.7494458, 33.7043755],
                    [-117.7419012, 34.3114455],
                    [-118.7200586, 34.5837014]
                ]
            ]
        ],
        [
            'name' => 'Orange County',
            'code'=> 'OC',
            'polygon' => [
                [
                    [-117.941447, 33.926867],
                    [-117.522212, 33.926867],
                    [-117.522212, 33.538652],
                    [-117.941447, 33.538652],
                    [-117.941447, 33.926867]
                ]
            ]
        ]
    ];

    /**
     * Assigns events to counties based on their latitude and longitude.
     *
     * @param array $events Array of events. Each event should have a 'location' attribute with 'lat' and 'lng'.
     * @return array Map of county names to arrays of events inside them.
     */
    public static function get_counties_per_events(array $events)
    {
        $county_map = [];

        foreach (self::COUNTIES as $county) {
            $county_map[$county['code']] = [];
        }

        foreach ($events as $event) {
            if (!isset($event['location']['latitude'], $event['location']['longitude'])) {
                continue;
            }

            // Prevent duplicate events by using a unique key (e.g., event ID or serialized event)
            $event_key = isset($event['id']) ? $event['id'] : md5(serialize($event));
            static $assigned_events = [];
            if (isset($assigned_events[$event_key])) {
                continue;
            }
            $assigned_events[$event_key] = true;

            $lat = $event['location']['latitude'];
            $lng = $event['location']['longitude'];

            foreach (self::COUNTIES as $county) {
                // Each county polygon is an array of polygons (GeoJSON MultiPolygon)
                foreach ($county['polygon'] as $polygon) {
                    if (self::point_in_polygon($lat, $lng, $polygon)) {
                        $county_map[$county['code']][] = $event;
                        break 2; // Assign to the first matching county
                    }
                }
            }
        }

        return $county_map;
    }

    /**
     * Determines if a point is inside a polygon using the ray-casting algorithm.
     *
     * @param float $lat Latitude of the point.
     * @param float $lng Longitude of the point.
     * @param array $polygon Array of [lng, lat] points defining the polygon.
     * @return bool True if the point is inside the polygon.
     */
    public static function point_in_polygon($lat, $lng, $polygon)
    {
        $inside = false;
        $n = count($polygon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            $intersect = (($yi > $lat) != ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-7) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }
        return $inside;
    }
}