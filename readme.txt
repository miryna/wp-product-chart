
=== WP Product Chart ===

Contributors: miryna
Tags: sales, graphs, charts, google charts
Requires at least: 3.8
Tested up to: 4.4.2 
License: GPLv2 or later

The plugin displays daily sales information in groups of products in the form of a bar chart with Google Charts.


== Description ==

The plugin displays daily sales information in groups of products in the form of a bar chart with Google Charts. Data tables can be located in local or remote database. Grouping categories is implemented on the parent of the top-level groups.
The short code can be placed anywhere in the text of the post/page.

Note: Product Chart will not work in old versions of Internet Explorer. (IE8 and earlier versions don't support SVG, which Product Chart require.) 

For more details see the help pages of the Google Chart library.


== Installation ==

1. Upload the content of the ZIP archive to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use shortcodes in the text where you want the chart to appear


= Supported shortcode =

[product-chart] 

For more details on the supported options for each chart, refer to the google's developer page at https://google-developers.appspot.com/chart/interactive/docs/gallery


== Screenshots ==

1. screenshot-page-settings.JPG
2. screenshot-shortcode.JPG


== Changelog ==

= 0.8 =
* Initial Release


== Upgrade Notice ==

= 0.9 =
* Removed global variables in productchart-data.class
* Added standard comments