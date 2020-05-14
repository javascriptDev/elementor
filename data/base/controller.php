<?php

namespace Elementor\Data\Base;

use WP_REST_Controller;
use WP_REST_Server;


abstract class Controller extends WP_REST_Controller {

	const ROOT_NAMESPACE = 'elementor';

	const REST_BASE = '';

	const VERSION = '1';

	/**
	 * Loaded endpoint(s).
	 *
	 * @var \Elementor\Data\Base\Endpoint[]
	 */
	public $endpoints = [];

	/**
	 * Loaded command(s) format.
	 *
	 * @var string[]
	 */
	public $command_formats = [];

	/**
	 * Loaded processor(s).
	 *
	 * @var \Elementor\Data\Base\Processor[][]
	 */
	public $processors = [];

	/**
	 * Controller constructor.
	 *
	 * Register endpoints on 'rest_api_init'.
	 */
	public function __construct() {
		$this->namespace = self::ROOT_NAMESPACE . '/v' . static::VERSION;
		$this->rest_base = static::REST_BASE . $this->get_name();

		add_action( 'rest_api_init', function () {
			$this->register_internal_endpoints();
			$this->register_endpoints();

			// Aka hooks.
			$this->register_processors();
		} );
	}

	/**
	 * Get controller name.
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Get controller namespace.
	 *
	 * @return string
	 */
	public function get_namespace() {
		return $this->namespace;
	}

	/**
	 * Get processors.
	 *
	 * @param string $command
	 * @param string $format
	 *
	 * @return \Elementor\Data\Base\Processor[]
	 */
	public function get_processors( $command, $format ) {
		$result = [];

		if ( $format ) {
			$command_parts = explode( '/', $command );
			$format_parts = explode( '/', $format );
			$format_parts_length = count( $format_parts );

			$command = '';

			for ( $format_index = 0, $command_index = 0; $format_index < $format_parts_length; $format_index++ ) {
				$format_part = $format_parts[ $format_index ];

				if ( ':' === $format_part[0] ) {
					continue;
				}

				$command .= $command_parts[ $command_index ] . '/';
				$command_index++;
			}

			$command = rtrim( $command, '/' );
		}

		if ( isset( $this->processors[ $command ] ) ) {
			$result = $this->processors[ $command ];
		}

		return $result;
	}

	/**
	 * Get controller reset base.
	 *
	 * @return string
	 */
	public function get_rest_base() {
		return $this->rest_base;
	}

	/**
	 * Get controller route.
	 *
	 * @return string
	 */
	public function get_controller_route() {
		return $this->get_namespace() . '/' . $this->get_rest_base();
	}

	public function get_items( $request ) {
		return $this->get_controller_index();
	}

	/**
	 * Retrieves the index for a controller.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_controller_index() {
		$server = rest_get_server();
		$routes = $server->get_routes();

		$endpoints = array_intersect_key( $server->get_routes(), $routes );

		$controller_route = $this->get_controller_route();

		array_walk( $endpoints, function ( &$item, $endpoint ) use ( &$endpoints, $controller_route ) {
			if ( ! strstr( $endpoint, $controller_route ) ) {
				unset( $endpoints[ $endpoint ] );
			}
		} );

		$data = [
			'namespace' => $this->get_namespace(),
			'controller' => $controller_route,
			'routes' => $server->get_data_for_routes( $endpoints ),
		];

		$response = rest_ensure_response( $data );

		// Link to the root index.
		$response->add_link( 'up', rest_url( '/' ) );

		return $response;
	}

	/**
	 * Register endpoints.
	 */
	abstract public function register_endpoints();

	/**
	 * Register processors.
	 */
	public function register_processors() {
	}

	/**
	 * Register internal endpoints.
	 */
	protected function register_internal_endpoints() {
		register_rest_route( $this->get_namespace(), '/' . $this->get_rest_base(), [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_items' ),
				'args' => [],
				'permission_callback' => function ( $request ) {
					return $this->permission_callback( $request );
				},
			],
		] );
	}

	/**
	 * Register endpoint.
	 *
	 * @param string $endpoint_class
	 */
	protected function register_endpoint( $endpoint_class ) {
		$endpoint_instance = new $endpoint_class( $this );

		// TODO: Validate instance like in register_sub_endpoint().

		$endpoint_route = $this->get_name() . '/' . $endpoint_instance->get_name();

		$this->endpoints[ $endpoint_route ] = $endpoint_instance;

		$command = $endpoint_route;
		$format = $endpoint_instance::get_format_suffix();

		if ( $command ) {
			$format = $command . '/' . $format;
		} else {
			$format = $format . $command;
		}

		$this->register_endpoint_format( $command, $format );
	}

	public function register_endpoint_format( $command, $format ) {
		$this->command_formats[ $command ] = rtrim( $format, '/' );
	}

	/**
	 * Register a processor.
	 *
	 * That will be later attached to the endpoint class.
	 *
	 * @param string $processor_class
	 */
	protected function register_processor( $processor_class ) {
		$processor_instance = new $processor_class( $this );

		// TODO: Validate processor instance.

		$command = $processor_instance->get_command();

		if ( ! isset( $this->processors[ $command ] ) ) {
			$this->processors[ $command ] = [];
		}

		$this->processors[ $command ] [] = $processor_instance;
	}

	/**
	 * Permission callback.
	 *
	 * Default controller permission callback.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool
	 */
	public function permission_callback( $request ) {
		switch ( $request->get_method() ) {
			case 'GET':
			case 'POST':
			case 'UPDATE':
			case 'PUT':
			case 'DELETE':
				// TODO: Handle all the situations.
				return current_user_can( 'edit_posts' );
		}

		return false;
	}
}
