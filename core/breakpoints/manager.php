<?php
namespace Elementor\Core\Breakpoints;

use Elementor\Core\Base\Module;
use Elementor\Core\Kits\Documents\Tabs\Settings_Layout;
use Elementor\Core\Responsive\Files\Frontend;
use Elementor\Core\Responsive\Responsive;
use Elementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Manager extends Module {

	const BREAKPOINT_OPTION_PREFIX = 'viewport_';
	const BREAKPOINT_KEY_MOBILE = 'mobile';
	const BREAKPOINT_KEY_MOBILE_EXTRA = 'mobile_extra';
	const BREAKPOINT_KEY_TABLET = 'tablet';
	const BREAKPOINT_KEY_TABLET_EXTRA = 'tablet_extra';
	const BREAKPOINT_KEY_LAPTOP = 'laptop';
	const BREAKPOINT_KEY_WIDESCREEN = 'widescreen';

	private $config;
	private $breakpoints;

	public function get_name() {
		return 'breakpoints';
	}

	/**
	 * Get Breakpoints
	 *
	 * Retrieve the array of --enabled-- breakpoints, or a single breakpoint if a name is passed.
	 *
	 * @since 3.2.0
	 *
	 * @param $breakpoint_name
	 * @return mixed
	 */
	public function get_breakpoints( $breakpoint_name = null ) {
		if ( ! $this->breakpoints ) {
			$this->breakpoints = $this->init_breakpoints();
		}
		return self::get_items( $this->breakpoints, $breakpoint_name );
	}

	/**
	 * Get Active Breakpoints with Previous Values
	 *
	 * Returns an array of breakpoints with the breakpoint names as keys, and the previous breakpoint's value as their
	 * value. This is used by the CSS generation classes to get the minimum points of each breakpoint. The returned
	 * array includes all breakpoints EXCEPT for mobile, since mobile is the lowest breakpoint and as such, it has
	 * a minimum point of 0px.
	 *
	 * @since 3.2.0
	 *
	 * @return array
	 */
	public function get_active_breakpoints_with_previous_values() {
		$breakpoints = $this->get_config();
		$breakpoint_names = array_keys( $breakpoints );
		// An array to be populated with each breakpoint name (except mobile) as the key, with the previous
		// breakpoint's value as its value.
		$breakpoints_with_previous_values = [];

		foreach ( $breakpoint_names as $index => $breakpoint_name ) {
			// Skip mobile which has a minimum point of 0.
			if ( 0 === $index ) {
				continue;
			}

			$breakpoints_with_previous_values[ $breakpoint_name ] = $breakpoints[ $breakpoint_names[ $index - 1 ] ]['value'];
		}

		return $breakpoints_with_previous_values;
	}

	/**
	 * Init Breakpoints
	 *
	 * Creates the breakpoints array, containing instances of each breakpoint.
	 *
	 * @return array
	 */
	private function init_breakpoints() {
		$breakpoints = [];
		$active_breakpoint_keys = Plugin::$instance->kits_manager->get_current_settings( Settings_Layout::BREAKPOINTS_SELECT_CONTROL_ID );
		$default_config = self::get_default_config();

		foreach ( self::get_default_config() as $name => $config ) {
			$args = [ 'name' => $name ] + $config;

			// Make sure each breakpoint knows whether it is enabled.
			if ( ! $active_breakpoint_keys && ( self::BREAKPOINT_KEY_MOBILE === $name || self::BREAKPOINT_KEY_TABLET === $name ) ) {
				// For Backwards Compatibility, enable the three existing default breakpoints.
				//TODO: Remove the next line once the new breakpoint keys are implemented.
				$args['value'] = $default_config[ $name ]['default_value'];

				// Make sure the default Mobile and Tablet breakpoints are always enabled.
				$args['is_enabled'] = true;
			} elseif ( $active_breakpoint_keys ) {
				$args['is_enabled'] = in_array( $name, $active_breakpoint_keys['options'], true );
			} else {
				// TODO: Adjust this if/else statement once the Select2 control is implemented in Site Settings.
				$args['is_enabled'] = false;
			}

			$breakpoints[ $name ] = new Breakpoint( $args );
		}

		return $breakpoints;
	}

	/**
	 * Get Config
	 *
	 * Retrieve --Enabled-- Breakpoints Config
	 *
	 * @since 3.2.0
	 *
	 * @param string|null $breakpoint_name
	 * @return array All breakpoints config, or a specific breakpoint's config.
	 */
	public function get_config( $breakpoint_name = null ) {
		if ( ! $this->config ) {
			$this->config = $this->init_config();
		}

		return self::get_items( $this->config, $breakpoint_name );
	}

	/**
	 * Init Config
	 *
	 * Iterate over the breakpoint instances to create the breakpoints config array.
	 *
	 * @since 3.2.0
	 *
	 * @return array $config
	 */
	private function init_config() {
		$config = [];

		// Make sure breakpoint instances are initialized before fetching their config.
		$breakpoints = $this->get_breakpoints();

		// Iterate over the breakpoint instances and get each one's config.
		foreach ( $breakpoints as $name => $instance ) {
			/** @var Breakpoint $instance */
			$breakpoint_config = $instance->get_config();

			// Only add breakpoints to the config array if they are enabled.
			if ( $breakpoint_config['is_enabled'] ) {
				$config[ $name ] = $breakpoint_config;
			}
		}

		return $config;
	}

	/** Has Custom Breakpoints
	 *
	 * Checks whether there are currently custom breakpoints saved in the database.
	 * Returns true if there are breakpoint values saved in the active kit.
	 *
	 * @since 3.2.0
	 *
	 * @return boolean
	 */
	public function has_custom_breakpoints() {
		$breakpoints = $this->get_breakpoints();

		$has_custom_breakpoints = false;

		foreach ( $breakpoints as $breakpoint ) {
			/** @var Breakpoint $breakpoint */
			if ( $breakpoint->is_custom() ) {
				$has_custom_breakpoints = true;
				break;
			}
		}

		return $has_custom_breakpoints;
	}

	/**
	 * Get Stylesheet Templates Path
	 *
	 * @since 3.2.0
	 * @access public
	 * @static
	 */
	public static function get_stylesheet_templates_path() {
		return ELEMENTOR_ASSETS_PATH . 'css/templates/';
	}

	/**
	 * Compile Stylesheet Templates
	 *
	 * @since 3.2.0
	 * @access public
	 * @static
	 */
	public static function compile_stylesheet_templates() {
		foreach ( self::get_stylesheet_templates() as $file_name => $template_path ) {
			$file = new Frontend( $file_name, $template_path );

			$file->update();
		}
	}

	/**
	 * Get Stylesheet Templates
	 *
	 * @since 3.2.0
	 * @access private
	 * @static
	 */
	private static function get_stylesheet_templates() {
		$templates_paths = glob( self::get_stylesheet_templates_path() . '*.css' );

		$templates = [];

		foreach ( $templates_paths as $template_path ) {
			$file_name = 'custom-' . basename( $template_path );

			$templates[ $file_name ] = $template_path;
		}

		$deprecated_hook = 'elementor/core/responsive/get_stylesheet_templates';
		$replacement_hook = 'elementor/core/breakpoints/get_stylesheet_template';

		Plugin::$instance->modules_manager->get_modules( 'dev-tools' )->deprecation->deprecated_hook( $deprecated_hook, '3.2.0', $replacement_hook );

		// TODO: REMOVE THIS DEPRECATED HOOK IN ELEMENTOR v3.10.0/v4.0.0
		$templates = apply_filters( $deprecated_hook, $templates );

		return apply_filters( $replacement_hook, $templates );
	}

	/**
	 * Get Default Config
	 *
	 * Retrieve the default breakpoints config array.
	 *
	 * @return array
	 */
	public static function get_default_config() {
		return [
			self::BREAKPOINT_KEY_MOBILE => [
				'label' => 'Mobile',
				'default_value' => 767,
				'direction' => 'max',
			],
			self::BREAKPOINT_KEY_MOBILE_EXTRA => [
				'label' => 'Mobile Extra',
				'default_value' => 880,
				'direction' => 'max',
			],
			self::BREAKPOINT_KEY_TABLET => [
				'label' => 'Tablet',
				'default_value' => 1024,
				'direction' => 'max',
			],
			self::BREAKPOINT_KEY_TABLET_EXTRA => [
				'label' => 'Tablet Extra',
				'default_value' => 1366,
				'direction' => 'max',
			],
			self::BREAKPOINT_KEY_LAPTOP => [
				'label' => 'Laptop',
				'default_value' => 1620,
				'direction' => 'max',
			],
			self::BREAKPOINT_KEY_WIDESCREEN => [
				'label' => 'Widescreen',
				'default_value' => 2400,
				'direction' => 'min',
			],
		];
	}
}
