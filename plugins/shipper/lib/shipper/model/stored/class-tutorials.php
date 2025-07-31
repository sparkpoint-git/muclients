<?php
/**
 * Shipper tutorials class
 *
 * @package Shipper
 */

/**
 * Class Shipper_Model_Stored_Tutorials
 *
 * @since 1.2.6
 */
class Shipper_Model_Stored_Tutorials extends Shipper_Model_Stored {
	const CACHE_KEY           = 'shipper_tutorials';
	const CATEGORY_ID         = 11237;
	const CACHE_TIMEOUT       = HOUR_IN_SECONDS;
	const HIDE_TUTORIALS      = 'hide_tutorials';
	const NUMBER_OF_TUTORIALS = 12;

	/**
	 * Constructor
	 *
	 * Sets up appropriate storage namespace
	 */
	public function __construct() {
		parent::__construct( 'shipper-tutorials' );
	}

	/**
	 * Get the article endpoint
	 *
	 * @return string
	 */
	public function get_url() {
		$base  = defined( 'WPMUDEV_CUSTOM_API_SERVER' ) && WPMUDEV_CUSTOM_API_SERVER
			? WPMUDEV_CUSTOM_API_SERVER
			: 'https://wpmudev.com/';
		$route = 'blog/wp-json/wp/v2/posts';

		return add_query_arg(
			array(
				'tutorials_categories' => self::CATEGORY_ID,
				'per_page'             => self::NUMBER_OF_TUTORIALS,
				'_fields'              => 'title,content,link,_links.wp:featuredmedia',
				'_embed'               => 'wp:featuredmedia',
			),
			trailingslashit( $base ) . $route
		);
	}

	/**
	 * Get all the tutorials
	 *
	 * @param int $number_of_tutorials number of tutorials to return.
	 *
	 * @return array
	 */
	public function all( $number_of_tutorials = self::NUMBER_OF_TUTORIALS ) {
		$posts    = $this->fetch();
		$articles = array();

		if ( ! is_array( $posts ) ) {
			return $articles;
		}

		foreach ( $posts as $index => $post ) {
			if ( $index === $number_of_tutorials ) {
				break;
			}

			$article            = new stdClass();
			$article->title     = $post->title->rendered;
			$article->excerpt   = wp_trim_words( $post->content->rendered, 10 );
			$article->url       = $this->attach_utm( $post->link );
			$article->read_time = $this->get_read_time( $post );
			$article->image_url = $this->get_image_url( $post );
			$articles[]         = $article;
		}

		return $articles;
	}

	/**
	 * Fetch all the articles
	 *
	 * @return array.
	 */
	public function fetch() {
		$cached_tutorials = get_transient( self::CACHE_KEY );

		if ( $cached_tutorials ) {
			return json_decode( $cached_tutorials );
		}

		$response = wp_remote_get(
			$this->get_url(),
			array(
				'sslverify' => defined( 'WPMUDEV_API_SSLVERIFY' ) ? WPMUDEV_API_SSLVERIFY : true,
				'headers'   => array(
					'user-agent' => shipper_get_user_agent(),
				),
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) ) {
			return array();
		}

		$tutorials = wp_remote_retrieve_body( $response );
		set_transient( self::CACHE_KEY, $tutorials, self::CACHE_TIMEOUT );

		return json_decode( $tutorials );
	}

	/**
	 * Estimate read time
	 *
	 * @param string $content the article to estimate.
	 *
	 * @return int
	 */
	public function estimate_read_time( $content ) {
		$min_for_3k_chars = 3000;
		$content_length   = strlen( $content );

		return $content_length > $min_for_3k_chars
			? absint( ceil( $content_length / $min_for_3k_chars ) )
			: 1;
	}

	/**
	 * Attach UTM tag to a link
	 *
	 * @param string $link the link which to attach the UTM tag.
	 */
	public function attach_utm( $link ) {
		return add_query_arg(
			array(
				'utm_source'   => 'shipper',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'shipper_tutorial_read_article',
			),
			trailingslashit( $link )
		);
	}

	/**
	 * Return formatted read time string
	 *
	 * @param object $article Article object.
	 *
	 * @return string
	 */
	public function get_read_time( $article ) {
		return sprintf(
			/* translators: %s: read time */
			__( '%s min read', 'shipper' ),
			$this->estimate_read_time( $article->content->rendered )
		);
	}

	/**
	 * Get the image url of an article
	 *
	 * @param object $article Article object.
	 *
	 * @return string
	 */
	public function get_image_url( $article ) {
		return ! empty( $article->_embedded->{'wp:featuredmedia'}[0]->source_url )
			? $article->_embedded->{'wp:featuredmedia'}[0]->source_url
			: '';
	}

	/**
	 * Hide the tutorials
	 *
	 * @return void
	 */
	public function hide_tutorials() {
		$this->set( self::HIDE_TUTORIALS, true );
		$this->save();
	}

	/**
	 * Show tutorials
	 *
	 * @return void
	 */
	public function show_tutorials() {
		$this->set( self::HIDE_TUTORIALS, false );
		$this->save();
	}

	/**
	 * Check whether tutorials section on dashboard is hidden or not
	 *
	 * @return bool
	 */
	public function is_hidden() {
		if ( $this->should_be_hidden() ) {
			return true;
		}

		return $this->get( self::HIDE_TUTORIALS, false );
	}

	/**
	 * Check whether entire tutorials menu should be hidden or not
	 *
	 * @return bool
	 */
	public function should_be_hidden() {
		return ! Shipper_Helper_Assets::has_docs_links();
	}
}