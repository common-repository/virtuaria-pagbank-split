<?php
/**
 * Template seller page.
 *
 * @package virtuaria/pagbank/split
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
echo '<div id="content" class="seller-page">';
$seller = get_user_by(
	'slug',
	isset( $_GET['seller'] )
		? sanitize_text_field( wp_unslash( $_GET['seller'] ) )
		: sanitize_text_field( wp_unslash( get_query_var( 'seller' ) ) )
);

if ( $seller ) {
	$display_name = $seller->first_name
		? $seller->first_name . ' ' . $seller->last_name
		: $seller->display_name;
	$display_name = sprintf(
		'<h1 class="seller-name">%s</h1>',
		ucwords( mb_strtolower( $display_name ) )
	);

	printf(
		'<div class="seller">%s <p class="description">%s</p><a href="%s">Mais informações</a></div>',
		wp_kses_post( $display_name ),
		wp_kses_post( get_the_author_meta( 'description', $seller->ID ) ),
		esc_url( home_url( 'seller/' . $seller->user_login . '/reviews' ) )
	);
}

if ( $GLOBALS['seller_products'] ) {
	$seller_products = implode(
		',',
		array_map(
			'sanitize_text_field',
			wp_unslash(
				$GLOBALS['seller_products']
			)
		)
	);
	echo do_shortcode( '[products limit="20" paginate="true" ids="' . esc_attr( $seller_products ) . '"]' );
} else {
	/**
	 * Hook: woocommerce_no_products_found.
	 *
	 * @hooked wc_no_products_found - 10
	 */
	do_action( 'woocommerce_no_products_found' );
}
echo '</div>';

/**
 * Hook: woocommerce_sidebar.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action( 'woocommerce_sidebar' );

?>
<style>
	.seller-name {
		font-size: 30px;
		margin-bottom: 5px;
	}
	.description {
		margin-bottom: 15px;
	}
	.seller > a{
		text-decoration: underline;
	}
	.seller {
		font-size: 16px;
		margin-bottom: 20px;
	}
</style>
<?php
get_footer( 'shop' );
