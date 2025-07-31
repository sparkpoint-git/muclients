<?php
/**
 * GA4 Stats functionality REST endpoint.
 *
 * @link       http://wpmudev.com
 * @since      3.4.0
 *
 * @author     Joel James <joel@incsub.com>
 * @package    Beehive\Core\Modules\Google_Analytics\Endpoints\V1
 */

namespace Beehive\Core\Modules\Google_Analytics\Endpoints\V1;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use WP_REST_Request;
use WP_REST_Response;
use Beehive\Core\Modules\Google_Analytics;
use Beehive\Core\Utils\Abstracts\Endpoint;

class Stats extends Endpoint {

	/**
	 * API endpoint for the current endpoint.
	 *
	 * @since 3.2.4
	 *
	 * @var string $endpoint
	 */
	private $endpoint = '/stats';

	/**
	 * Stats class instance.
	 *
	 * @since 3.4.0
	 *
	 * @var Google_Analytics\Stats\GA4|null $stats
	 */
	protected $stats;

	/**
	 * Stats constructor.
	 *
	 * @since 3.4.0
	 */
	protected function __construct() {
		// Stats instance.
		parent::__construct();
		$this->stats = Google_Analytics\Stats\GA4::instance();
	}

	/**
	 * Register the routes for handling settings functionality.
	 *
	 * All custom routes for the stats functionality should be registered
	 * here using register_rest_route() function.
	 *
	 * @return void
	 * @since 3.2.4
	 */
	public function register_routes() {
		// Route to get post stats.
		register_rest_route(
			$this->get_namespace(),
			$this->endpoint . '/post/',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'post' ),
					'permission_callback' => array( $this, 'analytics_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
			)
		);

		// Route to get dashboard stats.
		register_rest_route(
			$this->get_namespace(),
			$this->endpoint . '/summary/',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'summary' ),
					'permission_callback' => array( $this, 'analytics_permission' ),
					'args'                => array(
						'to'      => $this->get_date_arg(),
						'from'    => $this->get_date_arg(),
						'network' => $this->get_network_arg(),
					),
				),
			)
		);

		// Route to get dashboard stats.
		register_rest_route(
			$this->get_namespace(),
			$this->endpoint . '/dashboard/',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'dashboard' ),
					'permission_callback' => array( $this, 'analytics_permission' ),
					'args'                => array(
						'to'      => $this->get_date_arg(),
						'from'    => $this->get_date_arg(),
						'network' => $this->get_network_arg(),
					),
				),
			)
		);

		// Route to get statistics page stats.
		register_rest_route(
			$this->get_namespace(),
			$this->endpoint . '/statistics/',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'statistics_page' ),
					'permission_callback' => array( $this, 'analytics_permission' ),
					'args'                => array(
						'to'      => $this->get_date_arg(),
						'from'    => $this->get_date_arg(),
						'network' => $this->get_network_arg(),
					),
				),
			)
		);

		// Route to get most popular posts stats.
		register_rest_route(
			$this->get_namespace(),
			$this->endpoint . '/popular/',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'popular_posts' ),
					'permission_callback' => array( $this, 'public_permission' ),
					'args'                => array(
						'count' => array(
							'required'    => false,
							'type'        => 'integer',
							'description' => __( 'No. of items required.', 'ga_trans' ),
						),
					),
				),
			)
		);

		// Route to check Analytics reporting API status.
		register_rest_route(
			$this->get_namespace(),
			$this->endpoint . '/api-status/',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'api_status' ),
					'permission_callback' => array( $this, 'analytics_permission' ),
					'args'                => array(
						'force'   => array(
							'required'    => false,
							'type'        => 'boolean',
							'description' => __( 'Should force skip cache.', 'ga_trans' ),
						),
						'network' => $this->get_network_arg(),
					),
				),
			)
		);

		// Route to get real time stats.
		register_rest_route(
			$this->get_namespace(),
			$this->endpoint . '/realtime/',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'realtime_stats' ),
					'permission_callback' => array( $this, 'analytics_permission' ),
					'args'                => array(
						'network' => $this->get_network_arg(),
					),
				),
			)
		);
	}

	/**
	 * Get the post stats data from cache or API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 * @since 3.2.4
	 */
	public function post( $request ) {
		// Get the post id.
		$post_id = $this->get_param( $request, 'id', 0, 'intval' );
		// Date from.
		$from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		// Date to.
		$to = gmdate( 'Y-m-d', strtotime( '-1 days' ) );

		// Get the stats.
		$stats = $this->stats->post_stats(
			$post_id,
			$from,
			$to,
			false,
			false,
			$exception
		);

		// Send response.
		return $this->get_stats_response(
			array(
				'stats'   => $stats,
				'periods' => array(
					'current' => array(
						'from' => $from,
						'to'   => $to,
					),
				),
			),
			$exception
		);
	}

	/**
	 * Get the dashboard summary data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 * @since 3.2.4
	 */
	public function summary( $request ) {
		// Get the required params.
		$to      = $request->get_param( 'to' );
		$from    = $request->get_param( 'from' );
		$network = $request->get_param( 'network' );

		// Get stats.
		$stats = $this->stats->stats( $from, $to, 'summary', $network, false, false, $exception );

		// Get previous period.
		$prev_period = Google_Analytics\Helper::get_previous_period( $from, $to );

		// Send response.
		return $this->get_stats_response(
			array(
				'stats'   => $stats,
				'periods' => array(
					'current'  => array(
						'from' => $from,
						'to'   => $to,
					),
					'previous' => array(
						'from' => $prev_period['from'],
						'to'   => $prev_period['to'],
					),
				),
			),
			$exception
		);
	}

	/**
	 * Get the statistics dashboard widget data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 * @since 3.2.4
	 */
	public function dashboard( $request ) {
		// Get the required params.
		$to      = $request->get_param( 'to' );
		$from    = $request->get_param( 'from' );
		$network = $request->get_param( 'network' );

		// Get stats.
		$stats = $this->stats->stats( $from, $to, 'dashboard', $network, false, false, $exception );

		// Get previous period.
		$prev_period = Google_Analytics\Helper::get_previous_period( $from, $to );

		// Send response.
		return $this->get_stats_response(
			array(
				'stats'   => $stats,
				'periods' => array(
					'current'  => array(
						'from' => $from,
						'to'   => $to,
					),
					'previous' => array(
						'from' => $prev_period['from'],
						'to'   => $prev_period['to'],
					),
				),
			),
			$exception
		);
	}

	/**
	 * Get the statistics page data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 * @since 3.2.4
	 */
	public function statistics_page( $request ) {
		// Get the required params.
		$to      = $request->get_param( 'to' );
		$from    = $request->get_param( 'from' );
		$network = $request->get_param( 'network' );

		// Get stats.
		$stats = $this->stats->stats( $from, $to, 'stats', $network, false, false, $exception );

		// Get previous period.
		$prev_period = Google_Analytics\Helper::get_previous_period( $from, $to );

		// Send response.
		return $this->get_stats_response(
			array(
				'stats'   => $stats,
				'periods' => array(
					'current'  => array(
						'from' => $from,
						'to'   => $to,
					),
					'previous' => array(
						'from' => $prev_period['from'],
						'to'   => $prev_period['to'],
					),
				),
			),
			$exception
		);
	}

	/**
	 * Get popular posts stats.
	 *
	 * Please note: Even if you set count param, you may get less
	 * no. of items. This is because we emit links from other sites
	 * and if there are many links from other sites in most popular
	 * pages API response, you will get less no. of items after the
	 * emission of other sites.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 * @since 3.2.4
	 */
	public function popular_posts( $request ) {
		// Get the total count of items required.
		$count = $this->get_param( $request, 'count', 0 );

		// Get the widget instance.
		$widget = Google_Analytics\Widgets\Popular::instance();

		// Get top posts list.
		$list = $widget->get_list( $count );

		// Send response.
		return $this->get_response( $list );
	}

	/**
	 * Get the statistics page data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 * @since 3.2.4
	 */
	public function realtime_stats( $request ) {
		// Get the required params.
		$network = $request->get_param( 'network' );

		// Get realtime stats.
		$stats = $this->stats->realtime( $network, $exception );

		// Send response.
		return $this->get_stats_response(
			array(
				'stats' => $stats,
			),
			$exception
		);
	}

	/**
	 * Check if required Analytics Reporting API is enabled.
	 *
	 * Analytics Reporting API is required to get the stats. We will check if API
	 * is working by checking stats for current day.
	 * NOTE: DO NOT overuse this API as this make one Google API request always.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 * @since 3.2.2
	 * @since 3.2.4 Moved to API route.
	 */
	public function api_status( $request ) {
		// Network flag.
		$network = $request->get_param( 'network' );
		// Force check by skipping cache.
		$force = $this->get_param( $request, 'force', false );

		// Try to get today's stats from API.
		$stats = $this->stats->stats(
			gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
			gmdate( 'Y-m-d', strtotime( '-1 days' ) ),
			'summary',
			$network,
			$force, // Optionally force.
			false,
			$exception
		);

		// We got stats. Yay!.
		if ( ! empty( $stats ) || ( empty( $stats ) && empty( $exception ) ) ) {
			return $this->get_response(
				array(
					'status'  => true,
					'message' => __( 'Analytics API is up and working.', 'ga_trans' ),
				)
			);
		}

		// Send error response.
		return $this->get_error_response(
			$exception,
			array(
				'status' => false,
				'error'  => __( 'Analytics API is not ready.', 'ga_trans' ),
			),
			true
		);
	}

	/**
	 * Send error message response from exception class.
	 *
	 * @param array|string                              $stats Stats data.
	 * @param \Exception|\Google_Service_Exception|bool $exception Exception object.
	 *
	 * @return WP_REST_Response
	 * @since 3.2.4
	 */
	public function get_stats_response( $stats, $exception ) {
		// Send success response.
		if ( ! empty( $stats ) || empty( $exception ) ) {
			return $this->get_response( $stats );
		}

		// Send error response.
		return $this->get_error_response(
			$exception,
			array(
				'error' => __( 'Couldn\'t fetch data. Please try again later.', 'ga_trans' ),
			)
		);
	}

	/**
	 * Get date argument.
	 *
	 * @return array
	 */
	public function get_date_arg(): array {
		return array(
			'required'          => true,
			'validate_callback' => array( $this, 'validate_param' ),
			'description'       => __( 'The date in YYYY-MM-DD format.', 'ga_trans' ),
		);
	}

	/**
	 * Get network argument.
	 *
	 * @return array
	 */
	public function get_network_arg(): array {
		return array(
			'required'    => false,
			'type'        => 'boolean',
			'description' => __( 'The network flag.', 'ga_trans' ),
		);
	}
}