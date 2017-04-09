<?php

/**
 * Debug Bars Manager - All-in-One Dubug Bars Manager
 *
 * @category	WordPress_Plugin
 * @package     Debug Bars Manager
 * @author      Oleg Butuzov <butuzov@made.ua>
 * @link        https://github.com/butuzov/yojimbo/
 * @copyright   2017 Oleg Butuzov
 * @license     GPL v2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 *
 * Plugin Name: Debug Bars Manager
 * Plugin URI:  https://github.com/butuzov/Debug-Bars
 *
 * Description: Debug Bars Manager - All-in-One Dubug Bars Manager
 * Version:     0.1
 *
 * Author:      Oleg Butuzov
 * Author URI:  http://made.ua/
 *
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Avoid direct calls to this file.
if ( ! function_exists( 'add_action' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}


// ****************************************************************************
// Usefull Constants we defining beforehand.
// ****************************************************************************

if ( ! defined( 'DEBUG_BARS_DIR' ) ) {
	define( 'DEBUG_BARS_DIR' , WP_PLUGIN_DIR . '/' . basename( __DIR__ ) . '/' );
}

if ( ! defined( 'DEBUG_BARS_URL' ) ) {
	define( 'DEBUG_BARS_URL' , WP_PLUGIN_URL . '/' . basename( __DIR__ ) . '/' );
}

// Used to Get Memory Usage Via WP Admin Bar.
if ( ! defined( 'DEBUG_MEMORY' ) ) {
	define( 'DEBUG_MEMORY', memory_get_usage() );
}

// Used to Get Time Via WP Admin Bar.
if ( ! defined( 'DEBUG_MICROTIME' ) ) {
	define( 'DEBUG_MICROTIME',  microtime( true ) );
}

/**
 * Debug Bars Manager
 *
 * Allow admin user :
 * - install/remove other debug bars
 * - run contantly selected debug bar while development
 */
class Debug_Bars_Manager {

	const CSS = '1.0';
	const JS  = '1.0';

	const NAME = 'debug-bars-manager';


	/**
	 * Class Constructor.
	 */
	function __construct() {

		// This will add a functionality to plugin.
		add_action( 'init',	array( $this, 'init' ) );

		// For a reasons that Debug bars are actually standalone plugins
		// we also running them for a `plugins_loaded` action hook.
		add_action( 'plugins_loaded', array( $this, 'plugins_init' ) );

	}


	/**
	 * Can We Run This Plugin?
	 *
	 * For a reason wordpress initiate functionaliry, debug bar start to
	 * collect data, and a nature of this particular debug bars manager
	 * realization: we will run this plugin only in next cases.
	 * - Debug Bar, not exists in standalone mode.
	 * - User can and have all rights to run plugin (read only admin user)
	 *
	 * @return bool
	 */
	private function can_we_run_this_plugin(){
		// We using only one capability check for admin users instead of manage_options
		// due a reason in large systems we ca get a different roles/capability system.
		if ( is_user_logged_in() ) {
			// It has nested loop not for no reason, but for a better readability
			// and due a line length limit.
			if ( is_multisite() ? is_super_admin() : current_user_can( 'administrator' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * WordPress `init` action hook callback.
	 *
	 * @return void
	 */
	function init() {

		if ( $this->can_we_run_this_plugin() ) {

			// Call ajax action to save pannels.
			add_action( 'wp_ajax_debug_bars_manager_toogle_panels',
				array( $this, 'wp_ajax_toogle_panels' ));

			// Call ajax action to save pannels status.
			add_action( 'wp_ajax_debug_bars_manager_save_state',
				array( $this, 'wp_ajax_save_state' ) );

			add_action( 'admin_bar_menu',
				array( $this, 'debug_admin_bar_menu' ), 1001 );

			// Layout descriptions.
			$header_hook = is_admin() ? 'admin_enqueue_scripts' :'wp_head' ;
			add_action( $header_hook, array( $this, 'assets' ) );

			if ( ! class_exists( 'Debug_Bar_Slow_Actions' ) ) {
				$footer_hook = is_admin() ? 'admin_footer' :'wp_footer' ;
				add_action( $footer_hook, array( $this, 'footer_scripts' ), PHP_INT_MAX );
			}
		}

	}

	/**
	 * Web Assets Management.
	 *
	 * Including css and js assets to every page
	 * in backend and frontend.
	 *
	 * @return void
	 */
	public function assets() {

		// Common Suffix we going to use, should be min for minified version and debug off.
		$suffix = ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min' );

		// Assets ID based on filename.
		$name = basename( __FILE__, '.php' );

		// Including Style.
		$style_url = plugins_url( 'css/' . $name . $suffix . '.css', __FILE__ );
		wp_enqueue_style( $name , $style_url, array(), false, 'all' );

		// Registering Script (include in footer).
		$script_url = plugins_url( 'js/' . $name . $suffix . '.js', __FILE__ );
		wp_register_script( $name , $script_url, array( 'jquery', 'underscore' ), false, true );
	}


	/**
	 * Return Stat Data
	 *
	 * - RAM Used.
	 * - TIME RUnnned.
	 * - DB Calles done.
	 *
	 * @return void
	 */
	function footer_scripts() {

		// Generting Debug Data.
		$debugProfilingResults = array(
			// Memory.
			size_format( memory_get_usage() - DEBUG_MEMORY ),

			// Page Generation Time.
			number_format( microtime( true ) - DEBUG_MICROTIME, 2 ) . ' sec',
		);

		// Adding Number of DB Queries.
		if ( ! empty( $GLOBALS['wpdb']->queries ) ) {
			array_unshift( $debugProfilingResults ,
				sprintf( '%d db calls', $GLOBALS['wpdb']->queries ) );
		}

		// Assets ID based on filename.
		$name = basename( __FILE__, '.php' );

		// Current User ID.
		$current_user_id = get_current_user_id();

		$active_panel = get_user_meta( $current_user_id, 'debug_bars_current', true );
		$body_classes = get_user_meta( $current_user_id, 'debug_bars_classes', true );

		wp_localize_script( $name, 'debugBarsManagerData', array(
			'nonce' => wp_create_nonce( 'debug-bars-manager' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'stat' => implode( ' / ', $debugProfilingResults ),
			'bar' => $active_panel,
			'css' => $body_classes,
		) );

		wp_enqueue_script( $name );
	}


	/**
	 * Scan Debug Bar Directory and Returns list of available plugins.
	 *
	 * @param  string $plugin_dir Debug Bars  plugin home directory.
	 * @return array              Array of available plugins.
	 */
	function debug_plugins_get( $plugin_dir ) {

		// DB Plugins storage.
		$plugins = array();

		// Current Directory Resourcse.
		$plugin_dir_handler = @ opendir( $plugin_dir );

		// PHP Scripts Fo=unt In Directory.
		$files = array();

		if ( is_dir( $plugin_dir ) ) {

			while ( false !== ( $plugin_sub_dir = readdir( $plugin_dir_handler ) ) ) {

				$info = pathinfo( $plugin_sub_dir );
				// Ignore all . starting files/directories.
				if ( substr( $info['basename'], 0, 1 ) == '.' ) {
					continue;
				}

				if ( is_dir( $plugin_dir . '/' . $plugin_sub_dir ) ) {

					// trying to open sub directory.
					$plugin_sub_dir_handler = @ opendir( $plugin_dir . '/' . $plugin_sub_dir );

					while ( false !== ( $file = readdir( $plugin_sub_dir_handler ) ) ) {
						$info = pathinfo( $file );
						// Ignore all . starting files/directories, again.
						if ( substr( $info['basename'], 0, 1 ) == '.' ) {
							continue;
						} elseif ( 'php' === $info['extension'] ) {
							$files[] = sprintf( '%s/%s', $plugin_sub_dir, $file );
						}
					}
					// Close Sub Directory.
					closedir( $plugin_sub_dir_handler );
				}
			}
			// Close Plugin Directory.
			closedir( $plugin_dir_handler );
		}

		// Going through the list of possible debug bars.
		foreach ( $files as $file ) {

			$data = get_file_data( $plugin_dir . $file , array(
				'Name' => 'Plugin Name',
			) );

			if ( ! empty( $data['Name'] ) && 'Debug Bars Manager' !== $data['Name'] ) {
				$plugins[ $data['Name'] ] = $directory . $file;
			}
		}

		return $plugins;
	}

	/**
	 * [plugins_init description]
	 *
	 * @return void
	 */
	function plugins_init() {

		if ( ! $this->can_we_run_this_plugin() ) {
			return;
		}

		$mods = get_user_meta( get_current_user_id(), 'debug_bars_enabled', true );

		if ( ! empty( $mods ) ) {

			$debug_plugins = $this->debug_plugins_get( DEBUG_BARS_DIR );

			if ( ! empty( $debug_plugins ) ) {
				foreach ( $debug_plugins as $name => $plugin ) {
					if ( in_array( $name, $mods, true ) ) {
						// We can't try catch here due a multiple
						// reasons it can go fatal error.
						//
						// @todo - implemtn error catching.
						include_once $plugin;

					}
				}
			}
		}
	}

	/**
	 * Helper method -> wraps panel to checkbox (so it can be used in menu)
	 *
	 * @param  string $id       Panel ID.
	 * @param  string $name     md5 hash of panel.
	 * @param  bool   $selected Is this Panel enabled.
	 * @return string           HTML of checkbox and lable.
	 */
	private function wrapcheckbox( $id, $name, $selected ) {
		return '<input type="checkbox" value="' . $id . '" '
		. ($selected ? 'checked' : '')
		. ' name="mod[]" id="' . md5( $name ) . '" /><label for="' . md5( $name ) . '"> ' . $name . '</label>';
	}


	/**
	 * Adds Additional Menus to debug bar
	 *
	 * @return void
	 */
	function debug_admin_bar_menu() {
		global $wp_admin_bar;

		// Parent Node.
		$parent = 'debug-bar';

		// Enabled Panels.
		$enabled = get_user_meta( get_current_user_id(), 'debug_bars_enabled', true );
		$enabled = ! is_array( $enabled ) ? array() : $enabled;

		// Available Panels.
		$debug_plugins = $this->debug_plugins_get( DEBUG_BARS_DIR );
		uksort( $debug_plugins, 'strcasecmp' );

		foreach ( array_keys( $debug_plugins ) as $item ) {
			$default = 'Debug Bar';
			$name = $default === $item
				? $default : rtrim( str_replace( $default, '', $item ), '-' );

			// Adding Node.
			$wp_admin_bar->add_menu( array(
				'parent' => $parent,
				'id' => 'db_' . sanitize_title( $item ),
				'title' => $this->wrapcheckbox( $item, $name, in_array( $item, $enabled, true ) ),
			) );

			// If this is Debug Bar adding special group and continue adding items to this group.
			if ( 'ab-' . sanitize_title( $item ) == 'ab-debug-bar' ) {

				$wp_admin_bar->add_group( array(
					'parent' => $parent,
					'id'     => 'debug-bars-list',
					'meta'   => array(
						'class' => 'ab-sub-secondary',
					),
				) );

				$parent = 'debug-bars-list';
			}
		}

	}

	/**
	 * Save State of Open Debug Bar.
	 *
	 * @return void
	 */
	function wp_ajax_save_state() {
		$nonce  = filter_input( INPUT_POST, 'nonce', FILTER_DEFAULT );
		if ( $this->can_we_run_this_plugin() && wp_verify_nonce( $nonce, 'debug-bars-manager' ) ) {

			// Current User ID.
			$current_user_id = get_current_user_id();

			// States We going to Check.
			$is_partial = filter_input( INPUT_POST, 'partial', FILTER_VALIDATE_BOOLEAN );
			$is_maximized = filter_input( INPUT_POST, 'maximized', FILTER_VALIDATE_BOOLEAN );
			$is_visible = filter_input( INPUT_POST, 'visible', FILTER_VALIDATE_BOOLEAN );

			// Updating Set of Current CSS Classes of Debug Bar.
			delete_user_meta( $current_user_id, 'debug_bars_classes' );

			if ( $is_visible && $is_maximized ) {

				update_user_meta( $current_user_id, 'debug_bars_classes', array(
					'debug-bar-visible',
					'debug-bar-maximized',
				) );

			} elseif ( $is_visible && $is_partial ) {

				update_user_meta( $current_user_id, 'debug_bars_classes', array(
					'debug-bar-visible',
					'debug-bar-partial',
				) );

			}

			$active_bar = trim( filter_input( INPUT_POST, 'bar', FILTER_SANITIZE_STRING ) );

			if ( ! empty( $active_bar ) ) {
				update_user_meta( $current_user_id, 'debug_bars_current', $active_bar );
			} else {
				delete_user_meta( $current_user_id, 'debug_bars_current' );
			}

			die('1');
		}
	}

	/**
	 * Ajax Callback for toogling Panels on/off
	 *
	 * Note - no we do not checking if Debug Bar enabled itself.
	 *
	 * @return void
	 */
	function wp_ajax_toogle_panels() {

		$nonce  = filter_input( INPUT_POST, 'nonce', FILTER_DEFAULT );

		if ( $this->can_we_run_this_plugin() && wp_verify_nonce( $nonce, 'debug-bars-manager' ) ) {

			$current_user_id = get_current_user_id();
			parse_str( filter_input( INPUT_POST, 'debug', FILTER_DEFAULT ), $vars );

			if ( empty( $vars ) ) {
				delete_user_meta( $current_user_id, 'debug_bars_enabled' );
			} else {
				update_user_meta( $current_user_id, 'debug_bars_enabled', $vars['mod'] );
			}

			die( 1 );
		}
	}
}

new Debug_Bars_Manager;
