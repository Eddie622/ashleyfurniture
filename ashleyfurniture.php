<?php

/**
 *  Plugin Name:	    Ashley Furniture
 *  Description:	    Connects to the Ashley Furniture API and pulls product data for Woocommerce
 *  Version: 		    1.0.0
 *  Author: 		    Heriberto Torres
 *  Author URI: 	    https://heribertotorres.com
 *  Text Domain: 	    ashleyfurniture
 *  License:            GPL v2 or later
 *  License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 *  Update URI:         false
 *  GitHub Plugin URI:  Eddie622/ashleyfurniture
 *  GitHub Plugin URI:  https://github.com/Eddie622/ashleyfurniture
 */

// Prohibit direct script loading
defined('ABSPATH') || die('No direct script access allowed!');

// Plugin depends on Woocommerce
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( is_plugin_active( 'woocommerce/woocommerce.php') ) {
    register_activation_hook( __FILE__, ['Ashleyfurniture', 'ashleyfurniture_activation'] );
    register_deactivation_hook( __FILE__, ['Ashleyfurniture', 'ashleyfurniture_deactivation'] );
    
    add_action( 'plugins_loaded', ['Ashleyfurniture', 'instance'] );
}

final class Ashleyfurniture
{
    /**
     * @var Ashleyfurniture
     */
    protected static $instance = null;
    
    public $plugin_path;
    public $plugin_url;
    public $error_log;

    /**
     * Singleton instance
     *
     * @return  self
     */
	public static function instance() {
		if( null === self::$instance ) {
			self::$instance = new self();
        }
        return self::$instance;
	}

    /**
     * Constructor
     * 
     * @return  void
     */
    public function __construct() {
        $this->define_paths();
        $this->require_files();
        
        add_action('init', [$this, 'add_menus']);
        add_action('woocommerce_after_register_taxonomy', [$this, 'create_attributes']);
        add_action('ashleyfurniture_cron', [$this, 'run_process']);
        add_filter('http_request_timeout', ['Ashleyfurniture_API_Controller', 'custom_http_request_timeout']);
    }
    
    /**
     * Set paths
     * 
     * @return  void
     */
    
    private function define_paths() {
        $this->plugin_path = trailingslashit( plugin_dir_path( __FILE__ ) );
        $this->plugin_url  = trailingslashit( plugins_url( '/', __FILE__ ) );
        $this->error_log   = plugin_dir_path( __FILE__ ) . 'error_log.txt';
        define( 'ASHLEYFURNITURE_DIR', $this->plugin_path );
        define( 'ASHLEYFURNITURE_URL', $this->plugin_url );
        define( 'ASHLEYFURNITURE_ERROR_LOG', $this->error_log );
    }

    /**
     * Set required files
     * 
     * @return  void
     */
    
    private function require_files() {
        require_once(ABSPATH . 'wp-config.php'); 
        require_once(ABSPATH . 'wp-includes/wp-db.php'); 
        require_once(ABSPATH . 'wp-admin/includes/taxonomy.php'); 
        require_once(ASHLEYFURNITURE_DIR . 'api-controller.php');
        require_once(ASHLEYFURNITURE_DIR . 'include/settings.php');
        require_once(ASHLEYFURNITURE_DIR . 'include/helper-functions.php');
        require_once(ASHLEYFURNITURE_DIR . 'include/woo-external-image.php');
    }
    
    /**
     * Add Admin Menu Pages
     * 
     * @return  void
     */
    public function add_menus(){
        !current_user_can('manage_options')?: add_action( 'admin_menu', [ashleyfurniture_Settings(), 'ashleyfurniture_add_settings_page'] );
    }

    /**
     * Plugin Activation/Deactivation
     * 
     * @return  void
     */
    public function ashleyfurniture_activation() {
        // Schedule an action if it's not already scheduled
        if ( ! wp_next_scheduled( 'ashleyfurniture_cron' ) ) {
            wp_schedule_event( time(), 'weekly', 'ashleyfurniture_cron' );
        }
    }
    public function ashleyfurniture_deactivation() {
        wp_clear_scheduled_hook( 'ashleyfurniture_cron' );
    }

    /**
     * Register Product Attributes
     * 
     * @return  void
     */
    public function create_attributes() {
        create_global_attribute('brand');
        create_global_attribute('material');
        create_global_attribute('color');
    }

    /**
     * Full API process
     * 
     * @return  void
     */
    public function run_process() {
        Ashleyfurniture_API_Controller::process_data(Ashleyfurniture_API_Controller::extract_data(Ashleyfurniture_API_Controller::fetch_products()));
    }
}
