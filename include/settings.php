<?php

// Prohibit direct script loading
defined('ABSPATH') || die('No direct script access allowed!');

class Ashleyfurniture_Settings {
  /**
   * @var Ashleyfurniture_Settings
   */
  protected static $instance = null;

  /**
   * Constructor
   * 
   * @return  void
   */
  private function __construct() {
      add_action( 'admin_init', [$this, 'ashleyfurniture_register_settings'] );
  }

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

  public function ashleyfurniture_add_settings_page() {
    $settings = add_options_page(
      'Ashleyfurniture Settings',
      'Ashley Config',
      'manage_options',
      'ashleyfurniture.php',
      [$this, 'ashleyfurniture_settings_page']
    );
  }

  public function ashleyfurniture_settings_page(){
    include_once(ASHLEYFURNITURE_DIR . 'templates/settings-page.php');
  }

  function ashleyfurniture_register_settings() {
   register_setting(
      'ashleyfurniture_settings',
      'ashleyfurniture_settings',
      [$this, 'ashleyfurniture_validate_plugin_settings']
    );
    add_settings_section(
      'section_one',
      '',
      [$this, 'ashleyfurniture_render_section_one_text'],
      'ashleyfurniture'
    );
    add_settings_section(
      'section_two',
      '',
      [$this, 'ashleyfurniture_render_section_two_text'],
      'ashleyfurniture'
    );
    add_settings_field(
      'client_id',
      'Client ID',
      [$this, 'ashleyfurniture_render_client_id_field'],
      'ashleyfurniture',
      'section_one'
    );
    add_settings_field(
      'customer_id',
      'Customer ID',
      [$this, 'ashleyfurniture_render_customer_id_field'],
      'ashleyfurniture',
      'section_two'
    );
    add_settings_field(
      'shipto',
      'Shipto',
      [$this, 'ashleyfurniture_render_shipto_field'],
      'ashleyfurniture',
      'section_two'
    );
    add_settings_field(
      'limit',
      'Limit',
      [$this, 'ashleyfurniture_render_limit_field'],
      'ashleyfurniture',
      'section_two'
    );
    add_settings_field(
      'page',
      'Page',
      [$this, 'ashleyfurniture_render_page_field'],
      'ashleyfurniture',
      'section_two'
    );
  }

  /**
    * Sanitize each setting field as needed
    *
    * @param array $input Contains all settings fields as array keys
    */
  public function ashleyfurniture_validate_plugin_settings( $input ) {
    $output = array();

    if( isset( $input['client_id'] ) ) {
      $output['client_id'] = sanitize_text_field($input['client_id']);
    }
    if( isset( $input['customer_id'] ) ) {
      $output['customer_id'] = preg_replace('/[^0-9]/', '', $input['customer_id']);
    }
    if( isset( $input['shipto'] ) ) {
      $output['shipto'] = preg_replace('/[^0-9]/', '', $input['shipto']);
    }
    if( isset( $input['limit'] ) ) {
      $output['limit'] = preg_replace('/[^0-9]/', '', $input['limit']);
    }
    if( isset( $input['page'] ) ) {
      $output['page'] = preg_replace('/[^0-9]/', '', $input['page']);
    }

    return $output;
  }
    
  public function ashleyfurniture_render_section_one_text(){
    printf('<h2>Headers</h2>');
  }

  public function ashleyfurniture_render_section_two_text(){
    printf('<h2>Parameters</h2>');
  }
    
  public function ashleyfurniture_render_client_id_field() {
    $options = get_option( 'ashleyfurniture_settings' );
    printf(
      '<input type="text" name="%s" value="%s" />',
      esc_attr( 'ashleyfurniture_settings[client_id]' ),
      esc_attr( $options['client_id'] )
    );
  }
    
  public function ashleyfurniture_render_customer_id_field() {
    $options = get_option( 'ashleyfurniture_settings' );
    printf(
      '<input type="number" name="%s" value="%s" />',
      esc_attr( 'ashleyfurniture_settings[customer_id]' ),
      esc_attr( $options['customer_id'] )
    );
  }

  public function ashleyfurniture_render_shipto_field() {
    $options = get_option( 'ashleyfurniture_settings' );
    printf(
      '<input type="number" name="%s" value="%s" />',
      esc_attr( 'ashleyfurniture_settings[shipto]' ),
      esc_attr( $options['shipto'] )
    );
  }

  public function ashleyfurniture_render_limit_field() {
    $options = get_option( 'ashleyfurniture_settings' );
    printf(
      '<input type="number" name="%s" value="%s" />',
      esc_attr( 'ashleyfurniture_settings[limit]' ),
      esc_attr( $options['limit'] )
    );
  }

  public function ashleyfurniture_render_page_field() {
    $options = get_option( 'ashleyfurniture_settings' );
    printf(
      '<input type="number" name="%s" value="%s" />',
      esc_attr( 'ashleyfurniture_settings[page]' ),
      esc_attr( $options['page'] )
    );
  }
}

/**
 * Returns main instance of Settings
 * 
 * @return  Ashleyfurniture_Settings
 */
 
function Ashleyfurniture_Settings() {
	return Ashleyfurniture_Settings::instance();
}
