<?php
/*
Plugin Name: FacetWP - Hierarchy Select
Description: A Hierarchy incrementing Select Facet
Version: 0.0.1
Author: David Cramer
Author URI: https://cramer.co.za
*/

defined( 'ABSPATH' ) or exit;
// setup constants

define( 'FWP_HIER_SELECT_PATH', plugin_dir_path( __FILE__ ) );
define( 'FWP_HIER_SELECT_URL', plugin_dir_url( __FILE__ ) );
define( 'FWP_HIER_SELECT_VER', '0.0.1' );
define( 'FWP_HIER_SELECT_BASENAME', plugin_basename( __FILE__ ) );


/**
 * Register facet types and init the Hierarchy Select
 *
 * @param $facet_types
 *
 * @return mixed
 */
function facetwp_init_hierachy_select( $facet_types ) {
	include_once FWP_HIER_SELECT_PATH . 'includes/class-hierarchy-select.php';
	$facet_types['hierarchy_select'] = new FacetWP_Facet_Hierarchy_Select();

	return $facet_types;
}

add_filter( 'facetwp_facet_types', 'facetwp_init_hierachy_select' );