<?php
/*
 * Plugin Name: Polylang URLs Translation
 * Description: Allow you to translate your custom post types and taxonomies URLs with Polylang.
 * Version: 1.0
 * Author: Grégory Viguier
 * License: GPLv3
 * License URI: http://www.screenfeed.fr/gpl-v3.txt
 * Text Domain: sf-polylang-urls-translation
 * Domain Path: /languages/
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cheatin\' uh?' );
}

define( 'SFPUT_VERSION',			'1.0' );
define( 'SFPUT_FILE',				__FILE__ );
define( 'SFPUT_PLUGIN_BASEDIR',		basename( dirname( SFPUT_FILE ) ) );
define( 'SFPUT_PLUGIN_BASENAME',	plugin_basename( SFPUT_FILE ) );
define( 'SFPUT_PLUGIN_DIR',			plugin_dir_path( SFPUT_FILE ) );


/*-----------------------------------------------------------------------------------*/
/* !BYE BYE ======================================================================== */
/*-----------------------------------------------------------------------------------*/

register_deactivation_hook( SFPUT_FILE, 'flush_rewrite_rules' );


function sfput_uninstall() {
	delete_option( 'sfput_options' );
}

register_uninstall_hook( SFPUT_FILE, 'sfput_uninstall' );


/*-----------------------------------------------------------------------------------*/
/* !INCLUDES ======================================================================= */
/*-----------------------------------------------------------------------------------*/

add_action( 'plugins_loaded', 'sfput_include' );

function sfput_include() {
	global $pagenow;

	$is_admin = is_admin() && ! ( defined('DOING_AJAX') && DOING_AJAX );

	if ( ! ($is_admin || '' != get_option( 'permalink_structure' )) ) {
		return;
	}

	// Pas de bras, pas de chocolat.
	if ( ! sf_can_use_polylang( '1.5.0.8' ) || ! sfput_get_polylang_mode() || is_plugin_deactivation( SFPUT_FILE ) ) {
		return;
	}

	include( SFPUT_PLUGIN_DIR . 'inc/settings.php' );
	include( SFPUT_PLUGIN_DIR . 'inc/main.php' );

	if ( $is_admin ) {

		include( SFPUT_PLUGIN_DIR . 'inc/admin.php' );

		// We're visiting the Polylang settings page.
		if ( 'options-general.php' === $pagenow && ! empty( $_GET['page'] ) && 'mlang' === $_GET['page'] ) {
			include( SFPUT_PLUGIN_DIR . 'inc/settings-page.php' );
		}

	}
}


/*-----------------------------------------------------------------------------------*/
/* !TOOLS ========================================================================== */
/*-----------------------------------------------------------------------------------*/

if ( !function_exists('sf_can_use_polylang') ):
function sf_can_use_polylang( $version = '100' ) {
	global $polylang;
	return defined('POLYLANG_VERSION') && version_compare( POLYLANG_VERSION, $version ) >= 0 && ! empty( $polylang ) && ! empty( $polylang->options['default_lang'] ) && $polylang->model->get_languages_list();
}
endif;


// !"force_lang" option in Polylang: 0 (none), 1 (directory), 2 (sub-domain), 3 (domain).

function sfput_get_polylang_mode() {
	global $polylang;
	if ( ! empty( $polylang->options['force_lang'] ) && $polylang->options['force_lang'] >= 1 && $polylang->options['force_lang'] <= 3 ) {
		return (int) $polylang->options['force_lang'];
	}
	return 0;
}


/*
 * Tell if a plugin is being deactivated.
 * @param (string) $file: the file path (absolute).
 * @return (bool) true if we're deactivating the plugin.
 */

if ( !function_exists('is_plugin_deactivation') ):
function is_plugin_deactivation( $file ) {
	if ( ! is_admin() || empty( $GLOBALS['pagenow'] ) || $GLOBALS['pagenow'] !== 'plugins.php' || empty( $_REQUEST['_wpnonce'] ) || ! current_user_can('activate_plugins') ) {
		return false;
	}

	$file = plugin_basename( $file );

	if ( isset( $_GET['action'], $_GET['plugin'] ) && $_GET['action'] == 'deactivate' && $_GET['plugin'] == $file ) {
		return wp_verify_nonce( $_REQUEST['_wpnonce'], 'deactivate-plugin_' . $file );
	}

	if ( ( ( isset( $_POST['action'] ) && $_POST['action'] == 'deactivate-selected' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'deactivate-selected' ) ) && ! empty( $_POST['checked'] ) ) {
		$_POST['checked'] = (array) $_POST['checked'];

		if ( in_array( $file, $_POST['checked'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-plugins' ) ) {
			if ( is_network_admin() ) {
				if ( sf_is_plugin_active_for_network( $file ) ) {
					return true;
				}
			}
			elseif ( is_plugin_active( $file ) && ! sf_is_plugin_active_for_network( $file ) ) {
				return true;
			}
		}
	}
	return false;
}
endif;


// !Never trigger "Fatal error: Call to undefined function is_plugin_active_for_network()" ever again!

if ( !function_exists('sf_is_plugin_active_for_network') ):
function sf_is_plugin_active_for_network( $plugin ) {
	if ( function_exists('is_plugin_active_for_network') ) {
		return is_plugin_active_for_network( $plugin );
	}

	if ( ! is_multisite() ) {
		return false;
	}

	$plugins = get_site_option( 'active_sitewide_plugins');
	if ( isset( $plugins[ $plugin ] ) ) {
		return true;
	}

	return false;
}
endif;


/**/