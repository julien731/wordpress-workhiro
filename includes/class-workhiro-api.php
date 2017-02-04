<?php
/**
 * Workhiro API Wrapper.
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

if ( ! class_exists( 'Workhiro_API' ) ) :

	/**
	 * Workhiro API Wrapper Class.
	 *
	 * This wrapper takes advantage of the username and password authentication method. It requests an access token using the user's regular credentials.
	 *
	 * @since 0.1.0
	 */
	final class Workhiro_API {

		/**
		 * Workhiro backends URLs.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		public $backends = array( 'production' => 'https://workhiro.com', 'staging' => 'https://staging.workhiro.com' );

		/**
		 * The API endpoint to be used.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		protected $url;

		/**
		 * The Workhiro API route.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		protected $api_route = '/api/v1';

		/**
		 * Workhiro username.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		protected $username;

		/**
		 * Workhiro password.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		protected $password;

		/**
		 * Access token given by Workhiro.
		 *
		 * @since 0.1.0
		 * @var array
		 */
		protected $token;

		/**
		 * Workhiro_API constructor.
		 *
		 * @param string $username Workhiro username (email, actually).
		 * @param string $password Account password.
		 */
		public function __construct( $username, $password ) {

			$this->username = sanitize_text_field( trim( $username ) );
			$this->password = sanitize_text_field( trim( $password ) );
			$this->url = $this->backends['production'];

		}

		/**
		 * Get the base query arguments.
		 *
		 * @since 0.1.0
		 *
		 * @param string $method The request method to be used for the request.
		 * @param array  $body   The request body.
		 *
		 * @return array
		 */
		protected function build_query_args( $method = 'GET', $body ) {

			$args = array(
				'method'      => in_array( $method, array( 'POST', 'GET' ), true ) ? $method : 'GET',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'        => wp_json_encode( $body ),
				'cookies'     => array(),
			);

			if ( ! empty( $this->token ) && isset( $this->token['access_token'] ) ) {
				$args['headers']['Authorization'] = 'Bearer ' . trim( $this->token['access_token'] );
			}

			return $args;

		}

		/**
		 * Send a request to Workhiro's API.
		 *
		 * @since 0.1.0
		 *
		 * @param string $method   Request method.
		 * @param string $endpoint API route to query.
		 * @param array  $body     Request contents.
		 *
		 * @return string|WP_Error
		 *
		 * @todo  Make sure thr $endpoint starts with a slash
		 */
		protected function request( $method, $endpoint, $body ) {

			// If there is no access token, we need to authenticate before making an API request.
			if ( empty( $this->token ) && 'auth' !== $method ) {
				$this->authenticate();
			}

			$response = '';

			switch ( $method ) :

				case 'GET':
					$response = wp_safe_remote_get( esc_url( $this->url . $this->api_route . $endpoint ), $this->build_query_args( $method, $body ) );
					break;

				case 'POST':
					$response = wp_remote_post( esc_url( $this->url . $this->api_route . $endpoint ), $this->build_query_args( $method, $body ) );
					break;

				case 'auth':
					$response = wp_remote_post( esc_url( $this->url . $endpoint ), $this->build_query_args( 'POST', $body ) );
					break;

			endswitch;

			$response_code    = wp_remote_retrieve_response_code( $response );
			$response_message = wp_remote_retrieve_response_message( $response );

			if ( 200 !== $response_code && ! empty( $response_message ) ) {
				return new WP_Error( $response_code, $response_message );
			} elseif ( 200 !== $response_code ) {
				return new WP_Error( $response_code, esc_attr__( 'Unknown error occurred', 'workhiro' ) );
			}

			return wp_remote_retrieve_body( $response );

		}

		/**
		 * Authenticate the requester before making any query.
		 *
		 * @since 0.1.0
		 * @throws Exception If the server response is invalid.
		 * @return void
		 */
		protected function authenticate() {

			$response = $this->request( 'auth', '/oauth/token', array(
				'username'   => $this->username,
				'password'   => $this->password,
				'grant_type' => 'password',
			) );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			// Store the token.
			$this->token = json_decode( $response, true );

			if ( ! isset( $this->token['access_token'] ) ) {
				throw new Exception( esc_attr__( 'Invalid response from the server', 'workhiro' ) );
			}

		}

		/**
		 * List job positions.
		 *
		 * @since 0.1.0
		 *
		 * @param string $state The state to filter jobs by. Possible states are published, draft and closed.
		 *
		 * @return array|WP_Error
		 */
		public function get_positions( $state = 'published' ) {

			$state     = in_array( $state, array( 'published', 'closed', 'draft' ), true ) ? $state : 'published';
			$positions = $this->request( 'GET', '/positions?' . $state, array() );

			if ( is_wp_error( $positions ) ) {
				return $positions;
			}

			return json_decode( $positions, true );

		}

		/**
		 * Get details of a specific position.
		 *
		 * @since 0.1.0
		 *
		 * @param int $id The position ID.
		 *
		 * @return WP_Error|array
		 */
		public function get_position( $id ) {

			$position = $this->request( 'GET', '/positions/' . (int) $id, array() );

			if ( ! is_wp_error( $position ) ) {
				$position = json_decode( $position );
			}

			return $position;

		}

	}

endif;
