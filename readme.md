# Tempus Fugit #
**Contributors:** [dshanske](https://profiles.wordpress.org/dshanske)  
**Tags:** time, archive, date, onthisday  
**Stable tag:** 1.3.0  
**Requires at least:** 6.2  
**Requires PHP:** 7.4  
**Tested up to:** 7.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

A Collection of Enhancements to Improve Time Handling on Your Site

## Description ##

This is a compilation of many tweaks to improve your site, including...

1. Date Based Archives will show up from oldest to newest, instead of newest first. When you are scrolling through memory lane, you want to do it in order.
2. Adds the %dayofyear% tag so you can have your permalinks as /%year%/%dayofyear% instead of month and day.
3. Adds On This Day URLs and Widgets /onthisday
4. Adds This Week URLs and Widgets /thisweek
5. Adds /updated, /random, /oldest as top level archives
6. Adds the %week% tag so you can have your permalinks include the year and adds the option for 2021/W21 to indicate Week 21 of the year.
7. Adds the [tempus_onthisday] and [tempus_thisweek] shortcodes, which show the same lists as the widgets on any page. They accept title, number, taxonomy, term, and nonefound attributes, for example [tempus_onthisday title="On This Day" taxonomy="category" term="travel"].
8. Adds a Random Memory widget and [tempus_random] shortcode that show a random post from all time, from this day in previous years, or from this week in previous years, optionally limited to a category, tag, or other term. For example [tempus_random period="week" taxonomy="post_tag" term="family"].
9. On This Day and This Week also work on category, tag, and other taxonomy archives, such as /category/travel/onthisday/ and /tag/family/thisweek/. The widgets and shortcodes can be limited to a term.
10. Adds template functions for date archive navigation that link to the nearest earlier and later dates with posts, on day, month, year, week, and day-of-year archives.
11. Adds a Minor update checkbox to the classic editor that saves a post without changing its last updated date.


## Installation ##

Install and activate. No configuration by default.

## Privacy and Data Storage Notice ##

This plugin stores no private data.

## Frequently Asked Questions ##

### Why did you create this? ###

I realized I was doing a lot of these little enhancements in other places, buried in my other plugins, where they were only tangentially related to what the plugin was for.
So I split all of these time based enhancements into their own thing.

## AI Assistance ##

Development of version 1.3.0 was assisted by Claude Code, an AI coding assistant from Anthropic. Claude reviewed the plugin and proposed bug fixes, security hardening, documentation, and an automated test suite. Each change was submitted as a separate pull request and was reviewed and merged by the plugin author, who remains responsible for the plugin and its support.

## Changelog ##

### Version 1.3.0 ( 2026-09-30 ) ###
* Requires WordPress 6.2 and PHP 7.4. Tested up to WordPress 7.1, and with ClassicPress 2.
* Fix: rewrite rules are registered correctly after activation, deactivation, and upgrades, so /onthisday, /thisweek, /updated, and similar URLs no longer return 404 until permalinks are saved again.
* Fix: the widget settings form no longer causes a fatal error.
* Fix: the On This Day and This Week widgets show previous years only again.
* Fix: %dayofyear% counts from 1 (January 1 is 001), matching the day-of-year archives. Post URLs that use %dayofyear% move forward by one day.
* Fix: weeks are ISO-8601 weeks everywhere. Week links near New Year use the ISO year, and week archives, This Week, and its widget no longer depend on the "Week starts on" setting.
* Fix: archive links follow filtered slugs, and use the requested site's permalink settings on multisite.
* Fix: date navigation no longer takes the day from today's date on month archives, applies its label arguments, and uses the site's timezone.
* Security: widget settings are sanitized, output is escaped, and plugin files can't be loaded directly.
* The sort and week query vars are renamed tempus_sort and tempus_week. Pretty URLs are unchanged.
* On This Day and This Week work on category, tag, and other taxonomy archives, with archive titles that include the term.
* The widgets can be limited to a category, tag, or other term, show new posts right away, and each keep their own cache.
* Date navigation skips dates without posts and works on week and day-of-year archives.
* /onthisday/MM/DD/ and /thisweek/NN/ list previous years only, like /onthisday and /thisweek.
* Added the [tempus_onthisday], [tempus_thisweek], and [tempus_random] shortcodes.
* Added the Random Memory widget.
* Added a Minor update checkbox to the classic editor.
* Deleting the plugin removes its data.
* Documentation follows the WordPress PHP documentation standards, and the plugin has an automated test suite.

### Version 1.2.0 ( 2024-05-12 ) ###
* Add functions for previous/next date archive that can be used in a theme

### Version 1.1.3 ( 2023-12-25 ) ###
* Remove extra output link due duplicate code

### Version 1.1.2 ( 2023-10-21 ) ###
* Fix issue with HTML escaping on widgets
* Add week of post link calculator so week widget will link to the week page


### Version 1.1.1 ( 2022-12-20 ) ###
* Load Series in ASC order

### Version 1.1.0 ( 2022-06-12 ) ###
* Fix issue where queries for special pages hid dynamic menus
* Adjust filter for this week to exclude previous 6 days, instead of calendar week due to inaccurate calculation


### Version 1.0.9 ( 2021-11-11 ) ###
* No longer show this week or this day in those archives. Only show previous years.
* Add in custom photos rewrite for On This Day and This Week features.

### Version 1.0.8 ( 2021-08-08 ) ###
* Updated Widget Title Filter

### Version 1.0.7 ( 2021-07-24 ) ###
* One final fix...should test better.

### Version 1.0.6 ( 2021-07-24 ) ###
* Oops

### Version 1.0.5 ( 2021-07-24 ) ###
* Adds week archives and week permalinks

### Version 1.0.4 ( 2021-04-04 ) ###
* Add rewrite functions to activation hook to avoid load order issue

### Version 1.0.3 ( 2021-03-19 ) ###
* Add Simple Location Map Rewrites
* Add This Week Widget and URLs

### Version 1.0.2 ( 2021-03-13 ) ###
* Link to day archive in On This Day widget
* On This Day Widget Title links to On This Day Archive

### Version 1.0.1 ( 2021-03-09 ) ###
* Fix Activation Only Issue

### Version 1.0 (2021-03-09) ###
* Initial Release

## Upgrade Notice ##

### 1.3.0 ###
Post URLs that use %dayofyear% move forward by one day, and %week% URLs for a few days around New Year now use the ISO year. Plain-permalink links using ?sort= or ?week= need ?tempus_sort= or ?tempus_week=. Requires WordPress 6.2 and PHP 7.4.
