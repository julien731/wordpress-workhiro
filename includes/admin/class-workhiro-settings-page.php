<?php
/**
 * Workhiro Settings Page.
 *
 * @package    Workhiro for WordPress
 * @author     Julien Liabeuf <julien@liabeuf.fr>
 * @license    GPL-2.0+
 * @link       https://julienliabeuf.com
 * @copyright  2016 Julien Liabeuf
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Workhiro_Settings_Page' ) ) :

	/**
	 * Workhiro Settings Page Class.
	 *
	 * This class registers a new settings page and its options.
	 *
	 * @since 0.1.0
	 */
	class Workhiro_Settings_Page {

		public function __construct( $settings_id  ) {

		}

		public function add_menu() {}
		public function add_section() {}
		public function add_field() {}

	}

endif;