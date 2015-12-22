<?php
if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );


/*-----------------------------------------------------------------------------------*/
/* !I18n =========================================================================== */
/*-----------------------------------------------------------------------------------*/

add_action( 'init', 'sfput_lang_init', 0 );

function sfput_lang_init() {
	load_plugin_textdomain( 'sf-polylang-urls-translation', false, SFPUT_PLUGIN_BASEDIR . '/languages/' );
}


/*-----------------------------------------------------------------------------------*/
/* !SETTINGS LINK ================================================================== */
/*-----------------------------------------------------------------------------------*/

add_filter( (is_multisite() && is_network_admin() ? 'network_admin_' : '') . 'plugin_action_links_' . SFPUT_PLUGIN_BASENAME, 'sfput_settings_link', 0 );

function sfput_settings_link( $links ) {
	$links['settings'] = '<a href="' . admin_url( 'options-general.php?page=mlang&tab=url-translation' ) . '">' . __("Settings") . '</a>';
	return $links;
}


/*-----------------------------------------------------------------------------------*/
/* !REPLACE REWRITE RULES (FOR MODE 1) ============================================= */
/*-----------------------------------------------------------------------------------*/

// !Post types (single) and Taxonomies.

function sfput_extra_permastructs( $rules ) {
	global $wp_rewrite, $polylang;

	if ( empty( $rules ) ) {	// Uh? oO
		return $rules;
	}

	$languages = $polylang->model->get_languages_list( array( 'fields' => 'slug' ) );

	// If Polylang doesn't add the /{default_lang}/ in the URL, no need to modify the rules for this lang, sfput_registered_post_type() took care of it earlier.
	if ( $polylang->options['hide_default'] ) {
		$languages = array_diff( $languages, array( $polylang->options['default_lang'] ) );
	}

	if ( empty( $languages ) ) {
		return $rules;
	}

	// The way Polylang built its regex.
	$imploded_langs	= '(' . implode( '|', $languages ) . ')/';
	$poly_slug		= $wp_rewrite->root . ( $polylang->options['rewrite'] ? '' : 'language/' ) . $imploded_langs;

	// So, why are we here?
	$filter		= str_replace( '_rewrite_rules', '', current_filter() );
	$post_types	= sfput_get_post_types();
	$taxonomies	= sfput_get_taxonomies();

	// Post type, single
	if ( ! empty( $post_types[ $filter ] ) ) {

		$slugs	= sfput_get_option( 'post_types.single' );
		$slug	= sfput_get_post_type_singular_slug( $filter );

		if ( empty( $slugs[ $filter ] ) || empty( $slug ) ) {	// Uh? oO
			return $rules;
		}

	}
	elseif ( ! empty( $taxonomies[ $filter ] ) ) {

		$slugs	= sfput_get_option( 'taxonomies' );
		$slug	= sfput_get_taxonomy_slug( $filter );

		if ( empty( $slugs[ $filter ] ) || empty( $slug ) ) {	// Uh? oO
			return $rules;
		}

	}
	else {	// Uh? oO
		return $rules;
	}

	// Replacements
	$new_rules	= array();
	$slugs		= $slugs[ $filter ];

	foreach ( $rules as $regex => $rule ) {
		if ( strpos( $regex, $poly_slug ) !== 0 ) {
			$new_rules[ $regex ] = $rule;
			continue;
		}
		foreach ( $languages as $language ) {
			$new_regex = str_replace_once( $imploded_langs, '(' . $language . ')/', $regex );
			$new_regex = str_replace_once( '/' . $slug . '/', '/' . $slugs[ $language ] . '/', '/' . $new_regex );
			$new_regex = ltrim( $new_regex, '/' );
			$new_rules[ $new_regex ] = $rule;
		}
	}

	return $new_rules;
}


// Post types (archive)

if ( sfput_get_polylang_mode() === 1 ) {
	add_filter( 'rewrite_rules_array', 'sfput_rewrite_rules', 1000 );
}

function sfput_rewrite_rules( $rules ) {
	global $wp_rewrite, $polylang;

	if ( empty( $rules ) ) {	// Uh? oO
		return $rules;
	}

	$languages = $polylang->model->get_languages_list( array( 'fields' => 'slug' ) );

	// If Polylang doesn't add the /{default_lang}/ in the URL, no need to modify the rules for this lang, sfput_registered_post_type() took care of it earlier.
	if ( $polylang->options['hide_default'] ) {
		$languages = array_diff( $languages, array( $polylang->options['default_lang'] ) );
	}

	if ( empty( $languages ) ) {
		return $rules;
	}

	// The way Polylang built its regex.
	$imploded_langs	= '(' . implode( '|', $languages ) . ')/';
	$poly_slug		= $wp_rewrite->root . ( $polylang->options['rewrite'] ? '' : 'language/' ) . $imploded_langs;

	// Post types slugs
	$slugs	= sfput_get_option( 'post_types.archive' );
	$slugs	= array_filter( $slugs );

	if ( empty( $slugs ) ) {
		return $rules;
	}

	$post_types	= array_keys( $slugs );
	$post_types	= array_combine( $post_types, $post_types );
	$old_slugs	= array_map( 'sfput_get_post_type_archive_slug', $post_types );
	$post_types	= implode( '|', $post_types );
	$new_rules	= array();

	foreach ( $rules as $regex => $rule ) {
		// Not a Polylang rule
		if ( strpos( $regex, $poly_slug ) !== 0 ) {
			$new_rules[ $regex ] = $rule;
		}
		// Archive
		elseif ( preg_match( '@[?|&]post_type=(' . $post_types . ')(?:&.*)?$@', $rule, $matches ) ) {
			$post_type = $matches[1];
			foreach ( $languages as $language ) {
				$new_regex = str_replace_once( $imploded_langs, '(' . $language . ')/', $regex );
				$new_regex = str_replace_once( '/' . $old_slugs[ $post_type ] . '/', '/' . $slugs[ $post_type ][ $language ] . '/', '/' . $new_regex );
				$new_regex = ltrim( $new_regex, '/' );
				$new_rules[ $new_regex ] = $rule;
			}
		}
		else {
			$new_rules[ $regex ] = $rule;
		}
	}

	return $new_rules;
}

/**/