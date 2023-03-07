<?php

// Prohibit direct script loading
defined('ABSPATH') || die('No direct script access allowed!');

/**
 * Lists all product attributes
 *
 * @return array
 */
function get_product_attributes() {
    return wc_get_attribute_taxonomies();
}

/**
 * Lists all product attribute terms (as strings)
 *
 * @return array
 */

function get_product_attribute_slugs() {
    // Get an array of product attribute taxonomies slugs
    $attributes_tax_slugs = array_keys( wc_get_attribute_taxonomy_labels() );

    // Get an array of product attribute taxonomies names (starting with "pa_")
    $attributes_tax_names = array_filter( array_map( 'wc_attribute_taxonomy_name', $attributes_tax_slugs ));

    return $attributes_tax_names;
}

/**
 * Lists all product attribute terms (as objects)
 *
 * @return array
 */
function get_product_attribute_terms() {
    $attribute_taxonomies = wc_get_attribute_taxonomies();
    $taxonomy_terms = array();

    if ($attribute_taxonomies):
        foreach ($attribute_taxonomies as $taxonomy):
            $taxonomy_name = wc_attribute_taxonomy_name($taxonomy->attribute_name);
            if ($taxonomy_name):
                $taxonomy_terms[$taxonomy->attribute_name] = get_terms(
                    array(
                        'taxonomy' => $taxonomy_name,
                        'orderby'    => 'name',
                        'hide_empty' => false,
                    )
                );
            endif;
        endforeach;
    endif;

    return $taxonomy_terms;
}

/**
 * Given a array of term ids/strings and taxonomy name, check if it already exists
 * and create terms as needed
 *
 * @param terms - array of term slugs
 * @param taxonomy - i.e product_cat, product_tag, pa_attribute_name
 * 
 * @return void | int - id
 */

function create_terms($terms, $taxonomy) {
    if(taxonomy_exists($taxonomy)) {
        foreach($terms as $term) {
            $parent_id = 0;
            // Check if part of a group of terms
            if(str_contains($term, ' - ')){
                $term_group = explode( ' - ', $term );

                // Last is true term, second to last is parent
                $term = end($term_group);
                $parent = prev($term_group);

                $parent_id = get_term_by('name', $parent, $taxonomy)->term_id;
            }
            if(!term_exists($term, $taxonomy)){
                $result = wp_insert_term(
                    $term,
                    $taxonomy,
                    array(
                        'parent' => $parent_id
                    ) 
                );
                if (is_wp_error($result)) {
                    $message = 'Error: ' . $result . PHP_EOL;
                    file_put_contents(ASHLEYFURNITURE_ERROR_LOG, $message, FILE_APPEND | LOCK_EX);
                }
            }
        }
    }
    else {
        $message = 'Error: Taxonomy not found - ' . $taxonomy . PHP_EOL;
        file_put_contents(ASHLEYFURNITURE_ERROR_LOG, $message, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Given a taxonomy, lists all term objects
 *
 * @return array
 */
function get_term_list($taxonomy) {
    $tags = get_terms(
        array(
            'taxonomy'   => $taxonomy,
            'orderby'    => 'name',
            'hide_empty' => false,
        )
    );
    return $tags;
}

/**
 * Given a name, create a woocommerce attribute
 *
 * @return int
 */
function create_global_attribute($name) {
    $taxonomy_name = wc_attribute_taxonomy_name( $name );

    if (taxonomy_exists($taxonomy_name)) {
        return wc_attribute_taxonomy_id_by_name($name);
    }

    $attribute_id = wc_create_attribute(array(
        'name'         => $name,
        'slug'         => sanitize_title($name),
        'type'         => 'select',
        'order_by'     => 'menu_order',
        'has_archives' => false,
    ));

    //Register it as a wordpress taxonomy for just this session. Later on this will be loaded from the woocommerce taxonomy table.
    register_taxonomy(
        $taxonomy_name,
        apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' )),
        apply_filters('woocommerce_taxonomy_args_' . $taxonomy_name, array(
            'labels'       => array(
                'name' => $name,
            ),
            'hierarchical' => true,
            'show_ui'      => false,
            'query_var'    => true,
            'rewrite'      => false,
        ))
    );

    //Clear caches
    delete_transient('wc_attribute_taxonomies');

    return $attribute_id;
}
