<?php

// Prohibit direct script loading
defined('ABSPATH') || die('No direct script access allowed!');

/**
  * 
  * TODO: Prevent first loop by setting product data directly onto WC Product after taxonomy & terms are handled.
  * No need to create an entire array of new products. 
  * IDs can be added onto each product to maintain a single list of products.
  * This will improve the space complexity.
  * 
  */

class Ashleyfurniture_API_Controller {

    /**
     * @var Ashleyfurniture_API_Controller
     */
    protected static $instance = null;

    /**
     * Prevent Instance of Class
     */
    private function __construct() {}

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

    /* ==============================================================================
        API Configurations
      ==============================================================================  */
    
    /**
     * Set the API URL
     * 
     * @return string - the full URL to use
     */
    
    public function get_url($href = '') {
        if(empty($href)){
            $options = get_option( 'Ashleyfurniture_settings' );
            $limit = isset($_POST['fetch-products']) ? $options['limit'] : '1000';
            $href = 'products?Customer=' . $options['customer_id'] . '&Shipto=' . $options['shipto'] . '&Limit=' . $limit . '&Page=' . $options['page'];
        }
        return ('https://apigw3.ashleyfurniture.com/productinformation/' . $href);
    }
    

    /**
     * Set API configuration to send with HTTP request
     * 
     * @param data - required data to post to server (expects json)
     * 
     * @return array - request arguments to be set as second arg in wp_remote_get 
     * 
     * Reference: https://developer.wordpress.org/reference/functions/wp_remote_get/
     */
    
	
	public function api_options()
    {
        $USERNAME = 'REDACTED';
        $PASSWORD = 'REDACTED';
        $options = get_option( 'Ashleyfurniture_settings' );
            
        return [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic '. base64_encode("{$USERNAME}:{$PASSWORD}"),
                'X-API-Key' => $options['api_key'],
                'Accept-Encoding' => 'gzip,deflate',
                'Accept-Language' => 'en-us',
            ],
        ];
    }

    /**
     * Set timeout
     */
    public function custom_http_request_timeout() {
        return 30;
    }

    /* ==============================================================================
        CRUD Functions
        
        @return mixed (Returns the value encoded in json to appropriate PHP type)
        
        Reference: https://www.php.net/manual/en/function.json-decode.php
      ==============================================================================  */

    public function fetch_products() {
        $fetched_products = [];
        $response = wp_remote_get(self::get_url(), self::api_options());

        while(true){
            if (is_wp_error($response)) {
                $message = $response->get_error_message() . PHP_EOL;
                echo $message;
                file_put_contents(ASHLEYFURNITURE_ERROR_LOG, $message, FILE_APPEND | LOCK_EX);
                break;
            }
            else {
                $status = wp_remote_retrieve_response_code($response);
                
                if ($status != '200') {
                    $message = wp_remote_retrieve_response_message($response);
                    $message = 'Error: ' . $status . ' - ' . $message . PHP_EOL;
                    echo $message;
                    file_put_contents(ASHLEYFURNITURE_ERROR_LOG, $message, FILE_APPEND | LOCK_EX);
                    break;
                }
                else {
                    $paged_result = json_decode(wp_remote_retrieve_body($response)); 
                    $fetched_products = array_merge($fetched_products, $paged_result->entities);

                    // Do not full run if used on settings page
                    if(!isset($_POST['fetch-products'])){
                        foreach ($paged_result->links as $link) {
                            if ($link->rel == "Next") {
                                // Pause, Reset query to grab next page, and reset while loop
                                sleep(2);
                                $response = wp_remote_get(self::get_url($link->href), self::api_options());
                                continue 2;
                            }
                        }
                    }
                    break;
                }
            }
        }
        return $fetched_products;
    }

    /**
     * Given product details and term ids, add product through Woocommerce
     *
     * @param product - associative array of product information
     * @param terms - associative array of term assignments
     * 
     * @return void
    */

    public function manage_product($product) {

        $product_id = wc_get_product_id_by_sku($product['sku']);

        if(empty($product_id)){
            $managed_product = new WC_Product_Simple();
            $managed_product->set_sku($product['sku']);
        }
        else {
            $managed_product = wc_get_product($product_id);
        }

        $managed_product->set_name($product['name']);
        $managed_product->set_slug(sanitize_title($product['name']));
        $managed_product->set_regular_price($product['regular_price']); // in current shop currency
        $managed_product->set_description($product['description']);

        // Taxonomies
        if(!empty($product['category_ids'])) {
            $managed_product->set_category_ids( $product['category_ids'] );
        }

        // Tags
        if(!empty($product['tag_ids'])) {
            $managed_product->set_tag_ids( $product['tag_ids'] );
        }
        
        // Set Weight & Dimensions
        if(!empty($product['weight'])) {
            $managed_product->set_weight($product['weight']);
        }
        if(!empty($product['length'])) {
            $managed_product->set_length($product['length']);
        }
        if(!empty($product['width'])) {
            $managed_product->set_width($product['width']);
        }
        if(!empty($product['height'])) {
            $managed_product->set_height($product['height']);
        }

        // Finalize WC Product in Database
        $post_id = $managed_product->save();

        /**
         * TODO: Refactor Attribute handling into a function
         */
        // Set Attributes
        $taxonomies = ['pa_brand', 'pa_material', 'pa_color'];
        foreach($taxonomies as $taxonomy){
            $attributes = (array) $managed_product->get_attributes();
    
            // 1. If the product attribute is set for the product
            if( array_key_exists( $taxonomy, $attributes ) ) {
                foreach( $attributes as $key => $attribute ){
                    if( $key == $taxonomy ){
                        $options = (array) $attribute->get_options();
                        $options[] = $term_id;
                        $attribute->set_options($options);
                        $attributes[$key] = $attribute;
                        break;
                    }
                }
                $managed_product->set_attributes( $attributes );
            }
            // 2. The product attribute is not set for the product
            else {
                $attribute = new WC_Product_Attribute();
            
                $attribute->set_id( sizeof( $attributes) + 1 );
                $attribute->set_name( $taxonomy );
                $attribute->set_options( array( $term_id ) );
                $attribute->set_position( sizeof( $attributes) + 1 );
                $attribute->set_visible( true );
                $attribute->set_variation( false );
                $attributes[] = $attribute;
            
                $managed_product->set_attributes( $attributes );
            }
            //TODO: Remove pointless conditional checks when refactoring this as function
            if($taxonomy == 'pa_brand' && !empty($product['brand_id'])) {
                $term = get_term( $product['brand_id'], $taxonomy );
                if( ! has_term( $term->name, $taxonomy, $post_id )){
                    wp_set_object_terms($post_id, $term->slug, $taxonomy, true );
                }
            }
            if($taxonomy == 'pa_material' && !empty($product['material_ids'])) {
                foreach($product['material_ids'] as $term_id){
                    $term = get_term( $term_id, $taxonomy );
                    if( ! has_term( $term->name, $taxonomy, $post_id )){
                        wp_set_object_terms($post_id, $term->slug, $taxonomy, true );
                    }
                }
            }
            if($taxonomy == 'pa_color' && !empty($product['color_ids'])) {
                foreach($product['color_ids'] as $term_id){
                    $term = get_term( $term_id, $taxonomy );
                    if( ! has_term( $term->name, $taxonomy, $post_id )){
                        wp_set_object_terms($post_id, $term->slug, $taxonomy, true );
                    }
                }
            }
        }

        // Set External URLs 
        if(!empty($product['images'])) {
            update_post_meta( $post_id, '_woo_product_img_url', $product['images'][0] );
            if(count($product['images']) > 1) {
                update_post_meta( $post_id, '_woo_product_gallery_url', array_slice($product['images'], 1, 10) );
            }
        }
    }

    public function delete_product($sku) {
        $product_id = wc_get_product_id_by_sku($sku);
        if($product_id) {
            $product = wc_get_product($product_id);
            $product->delete(true); // Permanent Deletion
        }
    }

    /* ==============================================================================
        Data Processing
      ==============================================================================  */

    public function extract_data($fetched_products) {
        $data = [
            'product_collection' => [],
            'category_collection' => [],
            'tag_collection' => [],
            'brand_collection' => [],
            'material_collection' => [],
            'color_collection' => [],
            'navigable_category_collection' => []
        ];

        if (!empty($fetched_products)) {
            foreach ($fetched_products as $product) {
                // Check Product Status
                if($product->status == 'Discontinued'){
                    // Remove Product if found
                    self::delete_product($product->sku);
                    continue;
                }
                if($product->itemCode == 'Sectional'){
                    // Remove Product if found
                    self::delete_product($product->sku);
                    continue;
                }

                // Base data
                $single_product = [
                    'name' =>  $product->itemName,
                    'sku' => $product->sku,
                    'regular_price' => $product->fobBasePrice,
                    'description' => $product->detailedDescription,
                    'images' => [],
                ];
    
                // Loop Images and append
                if (isset($product->imageSet)) {
                    foreach ($product->imageSet as $image) {
                        $single_product['images'][] = $image->href;
                    }
                }
                else if (isset($product->largeImageUrl) && !str_contains($product->largeImageUrl, 'NOIMAGEAVAILABLE')) {
                    $single_product['images'][] = $product->largeImageUrl;
                }
                else if (isset($product->mediumImageUrl) && !str_contains($product->largeImageUrl, 'NOIMAGEAVAILABLE')) {
                    $single_product['images'][] = $product->mediumImageUrl;
                }
                else {
                    // Do not add products without images
                    continue;
                }

                // Set Navigable Categories
                if (isset($product->navigableCategories)) {
                    $single_product['navigable_categories'] = [];
                    foreach ($product->navigableCategories as $navigable_category) {
                        // Check and store in collection
                        if (!in_array($navigable_category, $data['navigable_category_collection'])) {
                            $data['navigable_category_collection'][] = $navigable_category;
                        }
                        if(str_contains($navigable_category, ' - ')){
                            $navigable_category = end(explode( ' - ', $navigable_category ));
                        }
                        $single_product['navigable_categories'][] = $navigable_category;
                    }
                }
                
                // Set Room Categories
                if (isset($product->intendedRooms)) {
                    $single_product['categories'] = [];
                    foreach ($product->intendedRooms as $category) {
                        $single_product['categories'][] = $category;
                        // Check and store in collection
                        if (!in_array($category, $data['category_collection'])) {
                            $data['category_collection'][] = $category;
                        }
                    }
                }
    
                // Weight & Dimensions
                if (isset($product->itemWeightKg)) {
                    $single_product['weight'] = $product->itemWeightKg;
                }
                if (isset($product->unitDepthInches)) {
                    $single_product['length'] = $product->unitDepthInches;
                }
                if (isset($product->unitWidthInches)) {
                    $single_product['width'] = $product->unitWidthInches;
                }
                if (isset($product->unitHeightInches)) {
                    $single_product['height'] = $product->unitHeightInches;
                }
    
                // Search for Attributes (Brand / Material / Color / Size)
                if (isset($product->brandName)) {
                    $single_product['brand'] = $product->brandName;
                    if (!in_array($category, $data['brand_collection'])) {
                        $data['brand_collection'][] = $product->brandName;
                    }
                }
                if (isset($product->material)) {
                    $single_product['material'] = [];
                    foreach ($product->material as $material) {
                        $single_product['material'][] = $material;
                        if (!in_array($category, $data['material_collection'])) {
                            $data['material_collection'][] = $material;
                        }
                    }
                }
                if (isset($product->generalColor)) {
                    $single_product['color'] = [];
                    foreach ($product->generalColor as $color) {
                        $single_product['color'][] = $color;
                        if (!in_array($category, $data['color_collection'])) {
                            $data['color_collection'][] = $color;
                        }
                    }
                }
    
                // Search for Tags (Bed Sizes)
                if (isset($product->bedSize)) {
                    $single_product['size'] = $product->bedSize;
                    if (!in_array($category, $data['tag_collection'])) {
                        $data['tag_collection'][] = $product->bedSize;
                    }
                }
    
                // Add to collection
                $data['product_collection'][] = $single_product;
            }
        }
        return $data;
    }

    public function process_data($data) {
        // Categories
        if(!empty($data['navigable_category_collection'])) {
            create_terms($data['navigable_category_collection'], 'product_cat');
        }
        if(!empty($data['category_collection'])) {
            create_terms($data['category_collection'], 'product_cat');
        }

        // Tags
        if(!empty($data['tag_collection'])) {
            create_terms($data['tag_collection'], 'product_tag');
        }

        // Attributes
        if(!empty($data['brand_collection'])) {
            create_terms($data['brand_collection'], 'pa_brand');
        }
        if(!empty($data['material_collection'])) {
            create_terms($data['material_collection'], 'pa_material');
        }
        if(!empty($data['color_collection'])) {
            create_terms($data['color_collection'], 'pa_color');
        }

        // Grab relevant categories/tags/attributes to reduce database queries
        $taxonomy_collection = [
            'category_terms' => get_term_list('product_cat'),
            'tag_terms' => get_term_list('product_tag'),
            'brand_terms' => get_term_list('pa_brand'),
            'material_terms' => get_term_list('pa_material'),
            'color_terms' => get_term_list('pa_color')
        ];

        // Iterate through products and map appropriate taxonomy terms
        foreach($data['product_collection'] as $product) {     
            if(!empty($product['categories'])) {
                foreach($taxonomy_collection['category_terms'] as $term) {
                    if(in_array($term->name, $product['categories'])){
                        $product['category_ids'][] = $term->term_id;
                    }
                }
            }
            if(!empty($product['navigable_categories'])) {
                foreach($taxonomy_collection['category_terms'] as $term) {
                    if(in_array($term->name, $product['navigable_categories'])){
                        $product['category_ids'][] = $term->term_id;
                    }
                }
            }
            if(!empty($product['size'])) {
                foreach($taxonomy_collection['tag_terms'] as $term) {
                    if(str_contains($term->name, $product['size'])){
                        $product['tag_ids'][] = $term->term_id;
                    }
                }
            }
            if(!empty($product['brand'])) {
                foreach($taxonomy_collection['brand_terms'] as $term) {
                    if($term->name == $product['brand']){
                        $product['brand_id'] = $term->term_id;
                    }
                }
            }
            if(!empty($product['material'])) {
                foreach($taxonomy_collection['material_terms'] as $term) {
                    if(in_array($term->name, $product['material'])){
                        $product['material_ids'][] = $term->term_id;
                    }
                }
            }
            if(!empty($product['color'])) {
                foreach($taxonomy_collection['color_terms'] as $term) {
                    if(in_array($term->name, $product['color'])){
                        $product['color_ids'][] = $term->term_id;
                    }
                }
            }

            self::manage_product($product);
        }
    }
}

/**
 * Returns main instance of Ashleyfurniture_API_Controller
 * 
 * @return Ashleyfurniture_API_Controller
 */
 
function Ashleyfurniture_API_Controller() {
	return Ashleyfurniture_API_Controller::instance();
}
