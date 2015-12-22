<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cheatin\' uh?' );
}

/*-----------------------------------------------------------------------------------*/
/* !FILTERS ======================================================================== */
/*-----------------------------------------------------------------------------------*/

// !For each post type listed in our settings, change the slugs and rules for the ones provided for the default language in the settings. ----------------------------------------------------------------------------------
//  This way, WP and other plugins that are usings this post type slugs/rules later will use the ones set for the default language.
//  (and I won't need to take care of it again later)

add_action( 'registered_post_type', 'sfput_registered_post_type', 1, 2 );

function sfput_registered_post_type( $post_type, $args ) {
	global $wp_post_types, $wp_rewrite, $polylang;

	add_filter( 'sfput_options_clear_post_types_cache', '__return_true' );
	$post_types		= sfput_get_post_types();

	if ( empty( $post_types[ $post_type ] ) ) {
		return;
	}

	$mode			= sfput_get_polylang_mode();

	// Language
	if ( $mode === 1 ) {
		$lang		= $polylang->options['default_lang'];
	}
	else {
		$lang		= pll_current_language();
	}

	// Save the original slugs somewhere safe, it could be usefull later (like in the settings page).
	$original_slugs	= array(
		'singular'	=> sfput_get_post_type_singular_slug( $post_type ),
		'archive'	=> sfput_get_post_type_archive_slug( $post_type ),
	);
	if ( ! sf_cache_data( 'sfput_original_slugs-pt-' . $post_type ) ) {
		sf_cache_data( 'sfput_original_slugs-pt-' . $post_type, $original_slugs );
	}

	// Settings
	$single_slugs	= sfput_get_option( 'post_types.single' );
	$archive_slugs	= sfput_get_option( 'post_types.archive' );
	$single_slug	= ! empty( $single_slugs[ $post_type ][ $lang ] )  ? $single_slugs[ $post_type ][ $lang ]  : $original_slugs['singular'];
	$archive_slug	= ! empty( $archive_slugs[ $post_type ][ $lang ] ) ? $archive_slugs[ $post_type ][ $lang ] : $original_slugs['archive'];

	if ( ! $single_slug && ! $archive_slug ) {
		return;
	}

	// Change the singular slug.
	if ( $single_slug && $original_slugs['singular'] && $original_slugs['singular'] !== $single_slug ) {

		if ( ! empty( $wp_rewrite->extra_permastructs[ $post_type ]['struct'] ) ) {
			// This is me being paranoïd: I want to make sure to replace "/my-slug/", and not "FOOmy-slugBAR".
			$has_slash = strpos( $wp_rewrite->extra_permastructs[ $post_type ]['struct'], '/' ) === 0;
			$wp_rewrite->extra_permastructs[ $post_type ]['struct'] = str_replace( '/' . $original_slugs['singular'] . '/', '/' . $single_slug . '/', '/' . $wp_rewrite->extra_permastructs[ $post_type ]['struct'] );
			$wp_rewrite->extra_permastructs[ $post_type ]['struct'] = ( $has_slash ? '/' : '' ) . ltrim( $wp_rewrite->extra_permastructs[ $post_type ]['struct'], '/' );
		}

		$wp_post_types[ $post_type ]->rewrite['slug'] = $single_slug;

	}

	// Change the archive slug.
	if ( $archive_slug && $original_slugs['archive'] && $original_slugs['archive'] !== $archive_slug ) {

		if ( ! empty( $wp_rewrite->extra_rules_top ) ) {
			$new_rules = array();

			foreach ( $wp_rewrite->extra_rules_top as $regex => $rule ) {
				if ( preg_match( '@[?|&]post_type=' . $post_type . '(?:&.*)?$@', $rule ) ) {
					// This is me being paranoïd again: I want to make sure to replace "/my-slug/", and not "FOOmy-slugBAR".
					$has_slash = strpos( $regex, '/' ) === 0;
					$new_regex = str_replace( '/' . $original_slugs['archive'] . '/', '/' . $archive_slug . '/', '/' . $regex );
					$new_regex = ( $has_slash ? '/' : '' ) . ltrim($new_regex, '/');
					$new_rules[ $new_regex ] = $rule;
				}
				else {
					$new_rules[ $regex ] = $rule;
				}
			}

			$wp_rewrite->extra_rules_top = $new_rules;
		}

		$wp_post_types[ $post_type ]->has_archive = $archive_slug;

	}

	if ( is_admin() && ! ( defined('DOING_AJAX') && DOING_AJAX ) && $mode === 1 ) {
		remove_filter( $post_type . '_rewrite_rules', 'sfput_extra_permastructs', 1000 );
		add_filter( $post_type . '_rewrite_rules', 'sfput_extra_permastructs', 1000 );
	}
}


// !Same treatment for taxonomies. -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

add_action( 'registered_taxonomy', 'sfput_registered_taxonomy', 1, 3 );

function sfput_registered_taxonomy( $taxonomy, $object_type, $args ) {
	global $wp_taxonomies, $wp_rewrite, $polylang;

	add_filter( 'sfput_options_clear_taxonomies_cache', '__return_true' );
	$taxonomies		= sfput_get_taxonomies();

	if ( empty( $taxonomies[ $taxonomy ] ) ) {
		return;
	}

	$mode			= sfput_get_polylang_mode();

	// Language
	if ( $mode === 1 ) {
		$lang		= $polylang->options['default_lang'];
	}
	else {
		$lang		= pll_current_language();
	}

	// Save the original slug somewhere safe, it could be usefull later (like in the settings page).
	$original_slug = sfput_get_taxonomy_slug( $taxonomy );
	if ( ! sf_cache_data( 'sfput_original_slugs-taxo-' . $taxonomy ) ) {
		sf_cache_data( 'sfput_original_slugs-taxo-' . $taxonomy, $original_slug );
	}

	// Settings
	$slugs	= sfput_get_option( 'taxonomies' );
	$slug	= ! empty( $slugs[ $taxonomy ][ $lang ] ) ? $slugs[ $taxonomy ][ $lang ] : $original_slug;

	if ( ! $slug ) {
		return;
	}

	// Change the slug
	if ( $original_slug && $original_slug !== $slug ) {

		// This is me being paranoïd: I want to make sure to replace "/my-slug/", and not "FOOmy-slugBAR".
		$has_slash = strpos( $wp_rewrite->extra_permastructs[ $taxonomy ]['struct'], '/' ) === 0;
		$wp_rewrite->extra_permastructs[ $taxonomy ]['struct'] = str_replace( '/' . $original_slug . '/', '/' . $slug . '/', '/' . $wp_rewrite->extra_permastructs[ $taxonomy ]['struct'] );
		$wp_rewrite->extra_permastructs[ $taxonomy ]['struct'] = ( $has_slash ? '/' : '' ) . ltrim( $wp_rewrite->extra_permastructs[ $taxonomy ]['struct'], '/' );

		$wp_taxonomies[ $taxonomy ]->rewrite['slug'] = $slug;
	}

	if ( is_admin() && ! ( defined('DOING_AJAX') && DOING_AJAX ) && $mode === 1 ) {
		remove_filter( $taxonomy . '_rewrite_rules', 'sfput_extra_permastructs', 1000 );
		add_filter( $taxonomy . '_rewrite_rules', 'sfput_extra_permastructs', 1000 );
	}
}


// !Filter the links -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

if ( sfput_get_polylang_mode() === 1 ) {

	add_filter( 'post_type_link', 'sfput_post_type_link', 1000, 4 );
	add_filter( 'post_type_archive_link', 'sfput_post_type_archive_link', 1000, 2 );
	add_filter( 'term_link', 'sfput_term_link', 1000, 3 );

}

// !Post Type Single -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

function sfput_post_type_link( $post_link, $post, $leavename, $sample ) {
	static $links;
	global $polylang, $wp_rewrite;

	if ( isset( $links[ $post->ID ] ) ) {
		return $links[ $post->ID ];
	}

	$struct = $wp_rewrite->get_extra_permastruct( $post->post_type );
	$draft_or_pending = isset($post->post_status) && in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) );

	if ( empty( $struct ) || ! ( !$draft_or_pending || $sample ) ) {
		return ( $links[ $post->ID ] = $post_link );
	}

	$single_slugs = sfput_get_option( 'post_types.single' );

	if ( empty( $single_slugs[ $post->post_type ] ) ) {
		return ( $links[ $post->ID ] = $post_link );
	}

	$slugs = $single_slugs[ $post->post_type ];
	$lang  = $polylang->model->get_post_language( $post->ID );

	if ( empty( $lang ) ) {
		return ( $links[ $post->ID ] = $post_link );
	}

	$def_lang = $polylang->options['default_lang'];
	$lang     = $lang->slug;

	if ( empty( $slugs[ $lang ] ) || empty( $slugs[ $def_lang ] ) || $slugs[ $lang ] === $slugs[ $def_lang ] ) {
		return ( $links[ $post->ID ] = $post_link );
	}

	$old_slug = '/' . $slugs[ $def_lang ] . '/';
	$new_slug = '/' . $slugs[ $lang ] . '/';

	return ( $links[ $post->ID ] = str_replace_once( $old_slug, $new_slug, $post_link ) );
}


// !Post Type Archive. -----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

function sfput_post_type_archive_link( $link, $post_type ) {
	global $polylang, $wp_rewrite;
	if ( $link === false ) {
		return $link;
	}

	$post_type_obj = get_post_type_object( $post_type );

	if ( ! is_array( $post_type_obj->rewrite ) ) {
		return $link;
	}

	$archive_slugs = sfput_get_option( 'post_types.archive' );

	if ( empty( $archive_slugs[ $post_type ] ) ) {
		return $link;
	}

	$slugs    = $archive_slugs[ $post_type ];
	$def_lang = $polylang->options['default_lang'];
	$lang     = $polylang->links_model->get_language_from_url( $link );

	if ( empty( $lang ) ) {
		if ( $polylang->options['hide_default'] ) {
			$lang = $def_lang;
		}
		else {
			return $link;
		}
	}

	if ( empty( $slugs[ $lang ] ) || empty( $slugs[ $def_lang ] ) || $slugs[ $lang ] === $slugs[ $def_lang ] ) {
		return $link;
	}

	$old_slug = '/' . $slugs[ $def_lang ] . '/';
	$new_slug = '/' . $slugs[ $lang ] . '/';

	return str_replace_once( $old_slug, $new_slug, $link );
}


// !Taxonomy Archive. ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

function sfput_term_link( $termlink, $term, $taxonomy ) {
	static $links;
	global $polylang, $wp_rewrite;
	$term_key = $term->term_id . '|' . $taxonomy;

	if ( isset( $links[ $term_key ] ) ) {
		return $links[ $term_key ];
	}

	$struct = $wp_rewrite->get_extra_permastruct( $taxonomy );

	if ( empty( $struct ) ) {
		return ( $links[ $term_key ] = $termlink );
	}

	$slugs = sfput_get_option( 'taxonomies' );

	if ( empty( $slugs[ $taxonomy ] ) ) {
		return ( $links[ $term_key ] = $termlink );
	}

	$slugs = $slugs[ $taxonomy ];
	$lang  = $polylang->model->get_term_language($term->term_id);

	if ( empty( $lang ) ) {
		return ( $links[ $term_key ] = $termlink );
	}

	$def_lang = $polylang->options['default_lang'];
	$lang     = $lang->slug;

	if ( empty( $slugs[ $lang ] ) || empty( $slugs[ $def_lang ] ) || $slugs[ $lang ] === $slugs[ $def_lang ] ) {
		return ( $links[ $term_key ] = $termlink );
	}

	$old_slug = '/' . $slugs[ $def_lang ] . '/';
	$new_slug = '/' . $slugs[ $lang ] . '/';

	return ( $links[ $term_key ] = str_replace_once( $old_slug, $new_slug, $termlink ) );
}


// !We need one more filter for the get_translation_url() method in Polylang. ----------------------------------------------------------------------------------------------------------------------------------------------
// It is needed for the post types archive url.

add_filter( 'pll_translation_url', 'sfput_pll_translation_url', 10, 2 );

function sfput_pll_translation_url( $url, $lang ) {
	global $polylang, $wp_rewrite;
	if ( ! $url || ! is_post_type_archive() ) {
		return $url;
	}

	// Current language
	$def_lang = pll_current_language();
	if ( $def_lang === $lang ) {
		return $url;
	}

	$post_type = get_query_var( 'post_type' );
	if ( empty( $post_type ) ) {	// Uh? oO
		return $url;
	}

	$archive_slugs = sfput_get_option( 'post_types.archive' );
	if ( empty( $archive_slugs[ $post_type ] ) ) {
		return $url;
	}

	$slugs = $archive_slugs[ $post_type ];

	if ( empty( $slugs[ $lang ] ) || empty( $slugs[ $def_lang ] ) || $slugs[ $lang ] === $slugs[ $def_lang ] ) {
		return $url;
	}

	$old_slug = '/' . $slugs[ $def_lang ] . '/';
	$new_slug = '/' . $slugs[ $lang ] . '/';

	return str_replace_once( $old_slug, $new_slug, $url );
}


/*-----------------------------------------------------------------------------------*/
/* !TOOLS ========================================================================== */
/*-----------------------------------------------------------------------------------*/

if ( !function_exists('sf_cache_data') ):
function sf_cache_data( $key, $data = 'trolilol' ) {
	static $datas = array();
	if ( $data !== 'trolilol' ) {
		$datas[$key] = $data;
	}
	return isset($datas[$key]) ? $datas[$key] : null;
}
endif;


if ( !function_exists('str_replace_once') ):
function str_replace_once( $search, $replace, $subject, $rev = false ) {
	if ( ! $rev ) {
		if ( false !== ( $pos = strpos( $subject, $search ) ) ) {
			return substr_replace( $subject, $replace, $pos, strlen( $search ) );
		}
	}
	else {
		if ( false !== ( $pos = strpos( strrev( $subject ), strrev( $search ) ) ) ) {
			return strrev( substr_replace( strrev( $subject ), strrev( $replace ), $pos, strlen( $search ) ) );
		}
	}
	return $subject;
}
endif;


// !Post types -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

// !Get a post type current archive slug.

function sfput_get_post_type_archive_slug( $post_type ) {
	if ( is_string( $post_type ) ) {
		$post_type = get_post_type_object( $post_type );
	}

	if ( ! $post_type || ! is_object( $post_type ) ) {
		return false;
	}

	if ( $post_type->has_archive ) {
		$slug = ( $post_type->has_archive === true ) ? $post_type->rewrite['slug'] : $post_type->has_archive;
		return is_string( $slug ) ? trim( $slug, '/' ) : $slug;
	}
	return false;
}


// !Get a post type current single slug.

function sfput_get_post_type_singular_slug( $post_type ) {
	if ( is_string( $post_type ) ) {
		$post_type = get_post_type_object( $post_type );
	}

	if ( ! $post_type || ! is_object( $post_type ) ) {
		return false;
	}

	return trim( $post_type->rewrite['slug'], '/' );
}


// !Get the original post type archive slug.

function sfput_default_post_type_archive_slug( $post_type ) {
	if ( is_object( $post_type ) ) {
		$post_type = $post_type->name;
	}

	if ( ! $post_type || ! is_string( $post_type ) ) {
		return false;
	}

	$slug = sf_cache_data( 'sfput_original_slugs-pt-' . $post_type );
	// Return original || current.
	return $slug ? $slug['archive'] : sfput_get_post_type_archive_slug( $post_type );
}


// !Get the original post type single slug.

function sfput_default_post_type_singular_slug( $post_type ) {
	if ( is_object( $post_type ) ) {
		$post_type = $post_type->name;
	}

	if ( ! $post_type || ! is_string( $post_type ) ) {
		return false;
	}

	$slug = sf_cache_data( 'sfput_original_slugs-pt-' . $post_type );
	// Return original || current.
	return $slug ? $slug['singular'] : sfput_get_post_type_singular_slug( $post_type );
}


// !Tell if a post type has single or archive post type.

function sfput_post_type_has_rewrite( $post_type ) {
	return sfput_default_post_type_archive_slug( $post_type ) || sfput_default_post_type_singular_slug( $post_type );
}


// !Get the translated "not-built-in" post types from Polylang. Not to use before init (that's where post types are registered).

function sfput_available_post_types() {
	static $post_types;
	global $polylang;

	if ( ! is_array( $post_types ) ) {

		$post_types	= $polylang->model->get_translated_post_types();

		if ( ! empty( $post_types ) ) {
			$post_types	= array_diff( $post_types, array(
				'post'		=> 'post',
				'page'		=> 'page',
				'attachment'=> 'attachment',
			) );
			$post_types	= array_filter( $post_types, 'sfput_post_type_has_rewrite' );
		}

		if ( ! empty( $post_types ) ) {
			$post_types	= array_combine( $post_types, $post_types );
		}

	}

	return $post_types;
}


// !Get the post types set in this plugin settings mixed with the ones from Polylang.

function sfput_get_post_types() {
	static $post_types;
	global $polylang;

	if ( ! is_array( $post_types ) || apply_filters( 'sfput_options_clear_post_types_cache', false ) ) {

		remove_all_filters( 'sfput_options_clear_post_types_cache' );
		$slugs	= sfput_get_option( 'post_types' );

		if ( is_array( $slugs ) && ! empty( $slugs ) ) {
			$post_types	= array_keys( array_merge( $slugs['archive'], $slugs['single'] ) );
			$types_poly	= $polylang->model->get_translated_post_types();

			if ( ! empty( $types_poly ) ) {
				$types_poly	= array_diff( $types_poly, array(
					'post'		=> 'post',
					'page'		=> 'page',
					'attachment'=> 'attachment',
				) );
				$types_poly	= array_filter( $types_poly, 'sfput_post_type_has_rewrite' );
				$post_types	= array_intersect( $post_types, $types_poly );
			}

			if ( ! empty( $post_types ) ) {
				$post_types	= array_combine( $post_types, $post_types );
			}
		}
		else {
			$post_types	= array();
		}

	}

	return $post_types;
}

// !Taxonomies -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

// !Get a taxonomy current slug.

function sfput_get_taxonomy_slug( $taxonomy ) {
	if ( is_string( $taxonomy ) ) {
		$taxonomy = get_taxonomy( $taxonomy );
	}

	if ( ! $taxonomy || ! is_object( $taxonomy ) ) {
		return false;
	}

	return ! empty( $taxonomy->rewrite['slug'] ) ? $taxonomy->rewrite['slug'] : false;
}


// !Get the original taxonomy slug.

function sfput_default_taxonomy_slug( $taxonomy ) {
	if ( is_object( $taxonomy ) ) {
		$taxonomy = $taxonomy->name;
	}

	if ( ! $taxonomy || ! is_string( $taxonomy ) ) {
		return false;
	}

	$slug = sf_cache_data( 'sfput_original_slugs-taxo-' . $taxonomy );
	return $slug ? $slug : sfput_get_taxonomy_slug( $taxonomy );
}


// !Get the translated taxonomies from Polylang. Not to use before init (that's where taxonomies are registered).

function sfput_available_taxonomies() {
	static $taxonomies;
	global $polylang;

	if ( ! is_array( $taxonomies ) ) {

		$taxonomies	= $polylang->model->get_translated_taxonomies();

		if ( ! empty( $taxonomies ) ) {
			//$taxonomies	= array_diff( $taxonomies, array( 'post_format' => 'post_format' ) );
			$taxonomies	= array_filter( $taxonomies, 'sfput_default_taxonomy_slug' );	// Remove the ones without rewrite slug.
		}

		if ( ! empty( $taxonomies ) ) {
			$taxonomies	= array_combine( $taxonomies, $taxonomies );
		}

	}

	return $taxonomies;
}


// !Get the taxonomies set in this plugin settings mixed with the ones from Polylang.

function sfput_get_taxonomies() {
	static $taxonomies;
	global $polylang;

	if ( ! is_array( $taxonomies ) || apply_filters( 'sfput_options_clear_taxonomies_cache', false ) ) {

		$slugs = sfput_get_option( 'taxonomies' );

		if ( is_array( $slugs ) && ! empty( $slugs ) ) {
			$taxonomies	= array_keys( $slugs );
			$taxos_poly	= $polylang->model->get_translated_taxonomies();

			if ( ! empty( $taxos_poly ) ) {
				//$taxos_poly	= array_diff( $taxos_poly, array( 'post_format' ) );
				$taxos_poly	= array_filter( $taxos_poly, 'sfput_default_taxonomy_slug' );	// Remove the ones without rewrite slug.
				$taxonomies	= array_intersect( $taxonomies, $taxos_poly );
			}

			if ( ! empty( $taxonomies ) ) {
				$taxonomies	= array_combine( $taxonomies, $taxonomies );
			}
		}
		else {
			$taxonomies	= array();
		}

	}

	return $taxonomies;
}

/**/