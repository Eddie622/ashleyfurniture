<?php

// Prohibit direct script loading
defined('ABSPATH') || die('No direct script access allowed!');

// Note: `wc_get_gallery_image_html` was added in WC 3.3.2 and did not exist prior. This check protects against theme overrides being used on older versions of WC.
if ( ! function_exists( 'wc_get_gallery_image_html' ) ) {
	return;
}

global $product;

$external_image = Woo_External_Image::instance();
$external_image_urls = $external_image->get_product_gallery_url( $product->get_id() );

foreach ( $external_image_urls as $external_image_url ) {
    if ( $external_image_url ) {
        echo $external_image->get_gallery_single_image( $external_image_url ); // phpcs:disable WordPress.XSS.EscapeOutput.OutputNotEscaped
    }
}
