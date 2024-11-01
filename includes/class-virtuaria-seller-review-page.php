<?php
/**
 * Handle Seller Review Page.
 *
 * @package virtuaria/pagseguro/split
 */

defined( 'ABSPATH' ) || exit;

/**
 * Definition
 */
class Virtuaria_Seller_Review_Page {
	/**
	 * Instance dao.
	 *
	 * @var Virtuaria_Transactions_DAO
	 */
	private $dao;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Minimum reviews count to show avg in seller page.
	 *
	 * @var int
	 */
	private const MIN_REVIEWS_COUNT = 4;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_virt_pagseguro_settings' );
		$this->dao      = new Virtuaria_Transactions_DAO();

		add_action( 'init', array( $this, 'create_endpoint_seller_page' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'template_include', array( $this, 'process_endpoint_request' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $query_vars the query vars.
	 * @return array
	 */
	public function add_query_vars( $query_vars ) {
		$query_vars[] = 'seller_review';
		return $query_vars;
	}

	/**
	 * Create a seller page endpoint.
	 */
	public function create_endpoint_seller_page() {
		add_rewrite_rule( '^seller/([^/]*)/reviews?$', 'index.php?seller_review=$matches[1]', 'top' );
		flush_rewrite_rules();
	}

	/**
	 * Redirect access to confirm page.
	 *
	 * @param string $template the template path.
	 * @return string
	 */
	public function process_endpoint_request( $template ) {
		if ( false == get_query_var( 'seller_review' ) || is_shop() ) {
			return $template;
		}

		$GLOBALS['seller'] = get_user_by(
			'slug',
			sanitize_text_field( wp_unslash( get_query_var( 'seller_review' ) ) )
		);

		$reputation = $this->get_seller_reputation( $GLOBALS['seller']->ID );

		if ( isset( $this->settings['hide_reputation'] )
			|| 'yes' === $this->settings['hide_reputation'] ) {
			$GLOBALS['reputation'] = false;
		} elseif ( isset( $reputation->total )
			&& $reputation->total >= self::MIN_REVIEWS_COUNT ) {
			$GLOBALS['reputation'] = $reputation->media;
		}

		$total_sales = $this->dao->get_count_sales_by_seller(
			'production' === $this->settings['environment'] ? 1 : 0,
			$GLOBALS['seller']->ID
		);

		if ( ( ! isset( $this->settings['hide_total_sales'] )
			|| 'yes' !== $this->settings['hide_total_sales'] )
			&& $total_sales > 0 ) {
			$GLOBALS['sales'] = $total_sales;
		}

		$GLOBALS['receiver'] = get_user_meta(
			$GLOBALS['seller']->ID,
			'_virtuaria_receiver',
			true
		);

		if ( $GLOBALS['receiver']
			&& isset( $GLOBALS['receiver']['level'] )
			&& 'undefined' !== $GLOBALS['receiver']['level'] ) {
			$GLOBALS['account_level'] = $GLOBALS['receiver']['level']
				? $GLOBALS['receiver']['level']
				: 'undefined';
		}

		$GLOBALS['reviews'] = $this->get_seller_reviews( $GLOBALS['seller']->ID );
		return VIRTUARIA_PAGBANK_SPLIT_DIR . 'templates/review-page.php';
	}

	/**
	 * Get the reputation of a seller based on their user ID.
	 *
	 * @param int $user_id The ID of the user whose reputation is being retrieved.
	 * @return object The reputation data including the average rating and total count.
	 */
	private function get_seller_reputation( $user_id ) {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(cm.meta_value) as 'media', COUNT(cm.meta_value) as 'total'
				FROM {$wpdb->commentmeta} as cm INNER JOIN {$wpdb->comments} as c
				ON cm.comment_id = c.comment_ID
				INNER JOIN {$wpdb->postmeta} as pm
				ON pm.post_id = c.comment_post_ID AND pm.meta_key = '_receiver_id' AND pm.meta_value = %d
				WHERE c.comment_approved = 1
					AND EXTRACT(YEAR FROM c.comment_date) = YEAR(CURRENT_DATE)
					AND EXTRACT(MONTH FROM c.comment_date) >= (CASE WHEN EXTRACT(MONTH FROM CURRENT_DATE) <= 6 THEN 1 ELSE 7 END)
					AND cm.meta_key = 'rating'",
				$user_id
			)
		);

		return $result;
	}

	/**
	 * Get seller reviews based on seller ID.
	 *
	 * @param int $seller_id The ID of the seller.
	 * @return array The array of seller reviews.
	 */
	public function get_seller_reviews( $seller_id ) {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_receiver_id',
					'value'   => $seller_id,
					'compare' => '=',
				),
			),
		);

		$products = new WP_Query( $args );

		$reviews = array();

		if ( $products->have_posts() ) {
			while ( $products->have_posts() ) {
				$products->the_post();

				$comments = get_comments( array( 'post_id' => get_the_ID() ) );
				foreach ( $comments as $comment ) {
					$date_format = new DateTime( $comment->comment_date );
					$reviews[]   = array(
						'author'  => $comment->comment_author,
						'note'    => get_comment_meta( $comment->comment_ID, 'rating', true ),
						'comment' => $comment->comment_content,
						'date'    => $date_format->format( 'd/m/Y' ),
						'product' => get_the_title(),
						'link'    => get_permalink(),
					);
				}
			}
		}
		wp_reset_postdata();

		return $reviews;
	}

	/**
	 * Enqueue scripts and styles for the seller page.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( false == get_query_var( 'seller_review' ) ) {
			return;
		}

		wp_enqueue_style(
			'virtuaria-seller-page',
			VIRTUARIA_PAGBANK_SPLIT_URL . 'public/css/seller-page.css',
			array(),
			filemtime( VIRTUARIA_PAGBANK_SPLIT_DIR . 'public/css/seller-page.css' )
		);
	}
}

new Virtuaria_Seller_Review_Page();
