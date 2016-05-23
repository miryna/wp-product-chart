<?php
/*
Plugin Name: WP Product Chart
Version: 0.8.0
Author: miryna
Plugin URI: https://github.com/miryna/wp_product_chart
Description: The plugin displays daily sales information in groups of products in the form of a bar chart with Google Charts.
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

Copyright 2016
*/

	// Exit if accessed directly
	if ( ! defined( 'ABSPATH' ) ) {
		exit; 
	}

	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	include_once( 'productchart-data.class.php' );
	$productchart_data = new ProductChart_Data();

	define( 'PCH__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'PCH__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );	
	define( 'PCH__SLUG', 'wp-product-chart' );	

	
	/**
	 * Register an activation hook for the plugin
	 */
	register_activation_hook( __FILE__,  'install_productchart' );

	
	/**
	 * Runs when the plugin is activated
	 */  
	function install_productchart() {
		// do not generate any output here
	}
 
 
	/**
	 * Runs when the plugin is initialized
	 */
	add_action( 'init',  'init_productchart' );
	
	function init_productchart() {
		
		// Setup localization
		load_plugin_textdomain(  PCH__SLUG, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
	}

	
	/**
	 * Registers and enqueues stylesheets for the administration panel and the
	 * public facing site.
	 */
	add_action( 'init',  'register_scripts_and_styles' );	 
	 
	function register_scripts_and_styles() {
	    wp_register_script(  PCH__SLUG . '-jsapi-script', 'https://www.google.com/jsapi');
	    wp_enqueue_script(  PCH__SLUG . '-jsapi-script');
		
		wp_register_script( 'settings_load',  plugins_url('/js/settings_load.js', __FILE__), array('jquery') );	
		
		if ( is_admin() ) {
			//this will run when on the backend
		} else {
			//this will run when on the frontend
		}		
	} 

	
	/**
	*	Register the shortcode [product-chart]
	*/	
	add_shortcode( 'product-chart', 'productchart_callback');	

	/**
	*	The source data for the chart
	*/
	$pch_data_string = $productchart_data->data();
	
	function productchart_callback( $atts, $content="" ) {
		
		global $pch_data_string;
		$str = "";
	
		if($pch_data_string){	
			$str = <<<TTT
			<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
			
			<script type="text/javascript">
			  google.charts.load('current', {'packages':['corechart']});
			  google.charts.setOnLoadCallback(drawChart);

			  function drawChart() {
				var data = google.visualization.arrayToDataTable([
					$pch_data_string	   
				]);

				var options = {
					width: 700,
					height: 550,
					legend: { position: 'top', maxLines: 3 },
					bar: { groupWidth: '75%' },
					isStacked: true
				};
				var chart = new google.visualization.BarChart(document.getElementById('chart_div'));
				chart.draw(data, options);
			  }
			</script>
			<h3>Product chart</h3>
			<div id="chart_div"></div>
TTT;
		}

	return $str;
	}	
	
	
	/**
	*	Register the 'Charts' admin menu page.
	*/
	add_action( 'admin_menu', 'register_charts_menu_page' );
	
	function register_charts_menu_page(){
	
		add_menu_page( 'Googlechart menu',
                       'Charts',
                       'manage_options',
                       'wp-product-chart/admin/charts.php',
                       '',
                       'dashicons-chart-area',
                       81 );
    }	

	
	/**
	*	Register the 'View Charts' admin submenu page.
	*/
	add_action( 'admin_menu', 'register_viewcharts_submenu_page' );
	
	function register_viewcharts_submenu_page(){
	
       add_submenu_page( 'wp-product-chart/admin/charts.php',
                   'Charts',
                   'View Charts',
                   'manage_options',
                   'wp-product-chart/admin/charts.php' );
    }

	
	/**
	*	Register the 'Ajax'  page.
	*/
	add_action( 'admin_menu', 'register_settings_ajax_page' );
	
	function register_settings_ajax_page(){
	
       add_submenu_page( NULL,
                   '',
                   '',
                   'manage_options',
                   'settings_ajax.php',
				  'ajax_submenu_page_callback' );
    }

	
	/**
	*	Register the 'Settings' admin submenu page.
	*/
	add_action( 'admin_menu', 'register_chartsettings_submenu_page' );	
	
	function register_chartsettings_submenu_page(){

        $ppp = add_submenu_page( 'wp-product-chart/admin/charts.php',
            'Charts',
            'Settings',
            'manage_options',
            'wp-product-chart/admin/settings.php',
			'chartsettings_submenu_page_callback' );
			
		// Use the registered page to load script 
		add_action( 'admin_print_scripts-' . $ppp, 'load_scripts_for_chartsettings' );
		
		//call register settings function
		add_action( 'admin_init', 'register_chartsettings' );
    }	

	
	/*
	 * This function is called only one the page, connect our script
	 */
	function load_scripts_for_chartsettings() {	 
		wp_enqueue_script('settings_load');
	}

	
	/*
	*	Register our settings
	*/
	function register_chartsettings() {

		register_setting( 'product_chart', 'pch_connect' );
		register_setting( 'product_chart', 'pch_host' );
		register_setting( 'product_chart', 'pch_db' );
		register_setting( 'product_chart', 'pch_user' );
		register_setting( 'product_chart', 'pch_pass' );
	}
	
	
	$pch_info = $productchart_data->info();
	
	/*
	 * HTML template of the 'Settings' page 
	 */	
	function chartsettings_submenu_page_callback(){
			
		$pch_connect = get_option( 'pch_connect');
		global $pch_info;
		
		if( isset($pch_connect) && $pch_connect == 'remote'){
			$local = "";
			$remote = "checked";
			
		}else{
			$local = "checked";
			$remote = "";		
		}
		?>
		
		<div class="wrap">
			<h2>Settings</h2>
			<h3>Connection Type</h3>

			<form id="wp_product_chart" action="options.php" method="POST">
			
				<? wp_nonce_field( 'update-options');?>
				<p>
					<label>
						<input  type="radio" id="connect_local" name="pch_connect" value="local" <?=$local ?> >Local 
					</label><br>
						
					<label>
						<input type="radio" id="connect_remote" name="pch_connect" value="remote" <?=$remote ?> >Remote
						
						<span id="pch_show" style="display: none; text-decoration: underline;">Show remote connection settings</span>
					</label>
				</p>
					
				<div id="settings_ajax"></div>
					
				<p> 
					<input type="hidden" name="action" value="update" />
				
					<input type="hidden" name="page_options" value="pch_connect, pch_host, pch_db, pch_user, pch_pass" />
					
					<input type="submit" name="update" value="<?php _e('Update settings') ?>">	
				</p> 

			</form>
			
			<p> <?php echo $pch_info; ?> </p>
			
		</div>
		<?php
	}

	
	/*
	 * HTML template to the additional part to the 'Settings' page
	 */	
	function ajax_submenu_page_callback(){
		
		?>	
		<div id='ajax_submenu_page_callback'>

		<p>
			<label for="host" class="">Host: </label><br>
			<input type="text" size="70" name="pch_host"  value="<?php echo get_option('pch_host') ?>" />
		</p>
		<p>
			<label for="db" class="">Database: </label><br>
			<input type="text" size="70" name="pch_db"  value="<?php echo get_option('pch_db') ?>" />
		</p>
		<p>
			<label for="user" class="">User: </label><br>
			<input type="text" size="70" name="pch_user"  value="<?php echo get_option('pch_user') ?>" />
		</p>
		<p>
			<label for="pass" class="">Password: </label><br>
			<input type="text" size="70" name="pch_pass"  value="<?php echo get_option('pch_pass') ?>" />
		</p>
		<p>
			<input type="hidden" name="action" value="update" />
		</p>

		</div>	
		<?php		
	}	

	
	/**
	*	Register filters for shortcodes
	*/
	add_filter( 'no_texturize_shortcodes', 'shortcodes_to_exempt_from_wptexturize' );

	function shortcodes_to_exempt_from_wptexturize($shortcodes){
		$shortcodes[] = 'product-chart';
		return $shortcodes;
	}
	

/* 
// add more buttons to the html editor
function productchart_add_quicktags() {
    if (wp_script_is('quicktags')){
?>
    <script type="text/javascript">
	
		QTags.addButton( 'eg_productchart', 'product-chart', '[product-chart]', 'j', 'Line Chart', 300 );
	
    </script>
<?php
    }
}
add_action( 'admin_print_footer_scripts', 'productchart_add_quicktags' );
*/