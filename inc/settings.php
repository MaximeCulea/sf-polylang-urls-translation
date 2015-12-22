<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cheatin\' uh?' );
}

/*-----------------------------------------------------------------------------------*/
/* !TEMPLATE TAGS ================================================================== */
/*-----------------------------------------------------------------------------------*/

// !Get all options.
// Do not use before init (post types and taxonomies don't exist yet).

function sfput_get_options() {
	$options = get_option( 'sfput_options' );
	return ! empty( $options ) && is_array( $options ) ? $options : sfput_default_options();
}


// !Get one option.

function sfput_get_option( $option = false ) {
	return sf_get_sub_options( $option, sfput_get_options() );
}


/*-----------------------------------------------------------------------------------*/
/* !TOOLS ========================================================================== */
/*-----------------------------------------------------------------------------------*/

if ( !function_exists( 'sf_get_sub_options' ) ):
function sf_get_sub_options( $name = false, $options = array() ) {
	if ( empty( $options ) || !$name ) {
		return array();
	}
	$options = (array) $options;

	if ( isset( $options[ $name ] ) ) {
		return $options[ $name ];
	}

	$group	= array();
	$name	= rtrim( $name, '.' ) . '.';
	foreach ( $options as $k => $v ) {
		if ( strpos( $k, $name ) === 0 ) {
			$group[ substr( $k, strlen( $name ) ) ] = $v;
		}
	}
	return ! empty( $group ) ? $group : null;
}
endif;


// ! register_setting() is not always defined...

if ( !function_exists( 'sf_register_setting' ) ):
function sf_register_setting( $option_group, $option_name, $sanitize_callback = '' ) {
	global $new_whitelist_options;

	if ( function_exists( 'register_setting' ) ) {
		register_setting( $option_group, $option_name, $sanitize_callback );
		return;
	}

	$new_whitelist_options = isset( $new_whitelist_options ) && is_array( $new_whitelist_options ) ? $new_whitelist_options : array();
	$new_whitelist_options[ $option_group ] = isset( $new_whitelist_options[ $option_group ] ) && is_array( $new_whitelist_options[ $option_group ] ) ? $new_whitelist_options[ $option_group ] : array();
	$new_whitelist_options[ $option_group ][] = $option_name;

	if ( $sanitize_callback != '' ) {
		add_filter( "sanitize_option_{$option_name}", $sanitize_callback );
	}
}
endif;


/*-----------------------------------------------------------------------------------*/
/* !SETTINGS ======================================================================= */
/*-----------------------------------------------------------------------------------*/

// !Default options.

function sfput_default_options() {
	$defaults = array(
		'post_types.archive'	=> array(),
		'post_types.single'		=> array(),
		'taxonomies'			=> array(),
	);
	return apply_filters( 'sfput_default_options', $defaults );
}


// !Sanitize settings on retrieve and cache values.

add_filter( 'option_sfput_options', 'sfput_sanitize_settings_and_cache' );

function sfput_sanitize_settings_and_cache( $settings = array() ) {
	static $done = false;

	if ( ! $done || apply_filters( 'sfput_options_clear_options_cache', false ) ) {
		$settings = sfput_sanitize_settings( $settings );

		wp_cache_set( 'sfput_options', maybe_serialize( $settings ), 'options' );

		$done = true;
		remove_all_filters( 'sfput_options_clear_options_cache' );
	}

	return $settings;
}

/*
function sfput_sanitize_settings_and_cache( $settings = array() ) {
	static $sanitized_settings = null;

	if ( is_null( $sanitized_settings ) || apply_filters( 'sfput_options_clear_options_cache', false ) ) {
		$sanitized_settings = sfput_sanitize_settings( $settings );
		remove_all_filters( 'sfput_options_clear_options_cache' );
	}

	return $sanitized_settings;
}*/


// !Flush rewrite rules and clear options cache after adding/updating/deleting options.

add_action( 'add_option_sfput_options',    'sfput_flush_rewrite_rules_and_options_cache' );
add_action( 'update_option_sfput_options', 'sfput_flush_rewrite_rules_and_options_cache' );
add_action( 'delete_option_sfput_options', 'sfput_flush_rewrite_rules_and_options_cache' );

function sfput_flush_rewrite_rules_and_options_cache() {

	// Flush rewrite rules.
	flush_rewrite_rules();

	// Flush settings cache
	add_filter( 'sfput_options_clear_options_cache', '__return_true' );
}


// !Sanitize settings.

function sfput_sanitize_settings( $raw_settings = array() ) {
	$defaults = sfput_default_options();
	$settings = is_array( $raw_settings ) ? $raw_settings : array();
	$settings = array_merge( $defaults, $settings );
	$settings = array_intersect_key( $settings, $defaults );

	$settings['post_types.archive'] = sftps_map_sanitize_slugs( $settings['post_types.archive'], 'archive' );
	$settings['post_types.single']  = sftps_map_sanitize_slugs( $settings['post_types.single'], 'single' );
	$settings['taxonomies']         = sftps_map_sanitize_slugs( $settings['taxonomies'], 'taxonomy' );

	return apply_filters( 'sfput_sanitize_settings', $settings, $raw_settings );
}


// !Sanitization function.

function sftps_map_sanitize_slugs( $slugs, $what ) {
	global $polylang;
	static $languages;
	$out = array();

	if ( ! is_array( $slugs ) || empty( $slugs ) ) {
		return $out;
	}

	// Empty fields?
	$slugs = array_filter( $slugs );
	if ( empty( $slugs ) ) {
		return $out;
	}

	if ( ! is_array( $languages ) ) {
		$languages = $polylang->model->get_languages_list( array( 'fields' => 'slug' ) );
	}

	if ( empty( $languages ) ) {
		return $out;
	}

	foreach ( $slugs as $post_type => $slugs_list ) {	// Actually, $post_type can be a taxonomy, but $post_type_or_taxonomy is a bit too long, isn't it? ;)
		if ( ! is_array( $slugs_list ) || empty( $slugs_list ) ) {
			continue;
		}
		// Empty filelds?
		$slugs_list = array_filter( $slugs_list );
		if ( empty( $slugs_list ) ) {
			continue;
		}

		$func = ( $what === 'taxonomy' ) ? 'sanitize_title_with_dashes' : 'sanitize_key';

		$out[ $post_type ] = array();

		foreach ( $languages as $language ) {
			$out[ $post_type ][ $language ] = ! empty( $slugs_list[ $language ] ) ? call_user_func( $func, $slugs_list[ $language ] ) : '';		// An empty string means "default slug".
		}
	}

	return $out;
}


// !Register settings.

sf_register_setting( 'sfput_settings', 'sfput_options', 'sfput_sanitize_settings' );

/**/