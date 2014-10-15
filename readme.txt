=== bbPress Topic Location ===
Contributors: G.Breant
Tags: bbpress,google maps,geolocation,geo-location
Requires at least: 3.3
Tested up to: 4
Stable tag: trunk
Donate link:http://bit.ly/gbreant

This plugin adds the ability to geo-locate topics in bbPress and to search topics by location from a new search widget.

== Description ==

This plugin adds the ability to geo-locate topics in bbPress and to search topics by location from a new search widget.
Originally developped for a classified ads forum (allowing ads to be geo-located).
View it in action and test it [here](http://bit.ly/wpbbptldemo)

= Features =

* Works both for frontend & backend.
* Powered by the w3c geolocation; which means that the plugin can guess your current location if your browser has this feature.
* Widget to search posts by location (and radius).
* Search results displays the distance between the input location and the posts located.
* Saves latitude & longitude separately in posts metas ('_bbptl_lat' and '_bbptl_lng').

= Contributors =
[Contributors are listed
here](https://github.com/gordielachance/bbpress-topic-location/contributors)

= Demo =
See it in action on [our forums](http://sandbox.pencil2d.org/forums).

= Notes =

For feature request and bug reports, [please use the
forums](http://wordpress.org/support/plugin/bbpress-topic-location#postform).

If you are a plugin developer, [we would like to hear from
you](https://github.com/gordielachance/bbpress-topic-location). Any contribution would be
very welcome.

== Installation ==

* Copy this plugin directory to `wp-content/plugins/`.
* Activate plugin.


== Frequently Asked Questions ==

**How can I customize the templates ?**

Plugin templates are loaded with the function bbptl_locate_template().
That function first checks if a template exists in your themes then load the default templates of the plugin.
So, if you want to have the plugin using your custom templates, just create a directory "bbpress-topic-location" under your current theme direction, 
copy the files from the bbpress-topic-location/theme in it, and edit those files.
Please be careful and don't remove hooks because this could break the plugin.

**How can I use functionnalities this plugin have elsewhere on my website ?**

See functions listed in the file bbptl-template-tags.php


== Screenshots ==

1. Topic edition (frontend)
2. Topic in topics list
3. Topic edition (backend)
4. Search results, with (bbPress) Geo Search Widget on the side

== Changelog ==
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