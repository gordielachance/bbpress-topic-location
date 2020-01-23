=== bbPress Topic Location ===
Contributors: G.Breant
Donate link:http://bit.ly/gbreant
Tags: bbpress,open street map,geolocation,geocoding
Requires at least: 3.3
Tested up to: 5.3.2
Stable tag: trunk
License: GPL2+

This plugin brings topics geolocation to bbPress, and can filter topics by location and radius.

== Description ==

This plugin brings topics geolocation to bbPress, and can filter topics by location and radius.
It has been originally developped for a classified ads forum.

= Features =

* Works both for frontend & backend
* Users can set the location of a post manually or automatically (HTML Geolocation API)
* Geocoding using the [Nominatim API](https://nominatim.openstreetmap.org/) by [Open Street Maps](https://www.openstreetmap.org/)
* Search posts by location and radius

= Demo =

We don't have a running demo anymore.  If you use this plugin and would like to be featured here, please [contact us](https://github.com/gordielachance/bbpress-topic-location/issues/1).

= Donate =

Donations are needed to help maintain this plugin.  Please consider [supporting us](http://bit.ly/gbreant).
This would be very appreciated — Thanks !

= Bugs/Development =

For feature request and bug reports, please use the [Github Issues Tracker](https://github.com/gordielachance/bbpress-topic-location/issues).

If you are a plugin developer, [we would like to hear from you](https://github.com/gordielachance/bbpress-topic-location). Any contribution would be very welcome.

== Installation ==

* Copy this plugin directory to `wp-content/plugins/`.
* Activate plugin.


== Frequently Asked Questions ==

== Screenshots ==

1. Topic edition (frontend)
2. Topic in topics list
3. Topic edition (backend)
4. Search results, with (bbPress) Geo Search Widget on the side

== Changelog ==
= 1.0.9 =
* handle 'HTML Geolocation API' requires HTTPS better
= 1.0.8 =
* 5 years after, it's alive again !
* updated for WP 5.3.2
* code cleaned and improved
* SCSS
* geocoding using (Nominatim API)[https://nominatim.openstreetmap.org/]
* HTTPS notice
= 1.0.7 =
* Added admin settings
* New template functions bbptl_post_has_geo(), bbptl_post_address(), bbptl_get_post_address(), bbptl_post_latitude(), bbptl_get_post_latitude(), , bbptl_post_longitude(), bbptl_get_post_longitude()
* Added class 'has-location' for topics and replies having a location
* Various code improvements
= 1.0.6 =
* Minor
= 1.0.5 =
* Allow to get results without search terms set (see functions bbpress_add_dummy_keyword and bbpress_remove_dummy_keyword)
= 1.0.4 =
* improved location form input & javascript
* when geolocated posts are found, displays distance between origin point and post location
* bbPress custom search widget, with geolocation parameters
* ability to search forum posts by location (and radius)
* code refactoring
= 1.0.2 =
* 'Guess my location' now returns a readable address instead of coordinates
* 2 new files : bbptl-ajax.php & bbptl-template-tags.php
* Uses native bbpress functions
* Bugs fixed
* Clode cleanup
= 1.0.1 =
* Screenshots, readme update, css fixes.
= 1.0.0 =
* Initial launch
