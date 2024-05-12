=== Tempus Fugit ===
Contributors: dshanske
Tags: time, archive, date, onthisday
Stable tag: 1.2.0
Requires at least: 4.9.9
Requires PHP: 7.0
Tested up to: 6.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A Collection of Enhancements to Improve Time Handling on Your Site

== Description ==

This is a compilation of many tweaks to improve your site, including...

1. Date Based Archives will show up from oldest to newest, instead of newest first. When you are scrolling through memory lane, you want to do it in order.
2. Adds the %dayofyear% tag so you can have your permalinks as /%year%/%dayofyear% instead of month and day.
3. Adds On This Day URLs and Widgets /onthisday
4. Adds This Week URLs and Widgets /thisweek
5. Adds /updated, /random, /oldest as top level archives
6. Adds the %week% tag so you can have your permalinks include the year and adds the option for 2021/W21 to indicate Week 21 of the year.


== Installation ==

Install and activate. No configuration by default.

== Privacy and Data Storage Notice ==

This plugin stores no private data.

== Frequently Asked Questions ==

= Why did you create this? =

I realized I was doing a lot of these little enhancements in other places, buried in my other plugins, where they were only tangentially related to what the plugin was for.
So I split all of these time based enhancements into their own thing.

== Changelog ==

= Version 1.2.0 ( 2024-05-12 ) =
* Add functions for previous/next date archive that can be used in a theme

= Version 1.1.3 ( 2023-12-25 ) =
* Remove extra output link due duplicate code

= Version 1.1.2 ( 2023-10-21 ) =
* Fix issue with HTML escaping on widgets
* Add week of post link calculator so week widget will link to the week page


= Version 1.1.1 ( 2022-12-20 ) =
* Load Series in ASC order

= Version 1.1.0 ( 2022-06-12 ) =
* Fix issue where queries for special pages hid dynamic menus
* Adjust filter for this week to exclude previous 6 days, instead of calendar week due to inaccurate calculation


= Version 1.0.9 ( 2021-11-11 ) =
* No longer show this week or this day in those archives. Only show previous years.
* Add in custom photos rewrite for On This Day and This Week features.

= Version 1.0.8 ( 2021-08-08 ) =
* Updated Widget Title Filter

= Version 1.0.7 ( 2021-07-24 ) =
* One final fix...should test better.

= Version 1.0.6 ( 2021-07-24 ) =
* Oops

= Version 1.0.5 ( 2021-07-24 ) =
* Adds week archives and week permalinks

= Version 1.0.4 ( 2021-04-04 ) =
* Add rewrite functions to activation hook to avoid load order issue

= Version 1.0.3 ( 2021-03-19 ) =
* Add Simple Location Map Rewrites
* Add This Week Widget and URLs

= Version 1.0.2 ( 2021-03-13 ) =
* Link to day archive in On This Day widget
* On This Day Widget Title links to On This Day Archive

= Version 1.0.1 ( 2021-03-09 ) =
* Fix Activation Only Issue

= Version 1.0 (2021-03-09) =
* Initial Release
