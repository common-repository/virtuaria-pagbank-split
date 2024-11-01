<?php
/**
 * Template seller review page.
 *
 * @package Virtuaria/PagSeguro/Split
 */

defined( 'ABSPATH' ) || exit;

get_header();
echo '<div id="seller-page">';
if ( isset( $GLOBALS['seller'] ) ) {
	$display_name = $GLOBALS['seller']->first_name
		? $GLOBALS['seller']->first_name . ' ' . $GLOBALS['seller']->last_name
		: $GLOBALS['seller']->display_name;
	printf(
		'<h1 class="seller-name">%s</h1>',
		wp_kses_post( ucwords( mb_strtolower( $display_name ) ) )
	);

	$bio = get_the_author_meta( 'description', $GLOBALS['seller']->ID );
	if ( $bio ) {
		printf(
			'<p class="description">%s</p>',
			wp_kses_post( $bio )
		);
	}

	if ( isset( $GLOBALS['reputation'] ) ) {
		if ( false !== $GLOBALS['reputation'] ) {
			printf(
				'<p class="reputation">Avaliação: <span class="start-rating"><span class="star" style="width: %s"></span></span></p>',
				esc_attr( ( $GLOBALS['reputation'] * 100 ) / 5 ) . '%'
			);
		}
	} else {
		printf(
			'<p class="reputation new-store">Nova Loja</p>',
			esc_html( number_format( $GLOBALS['reputation'], 2, '.', '' ) )
		);
	}

	if ( isset( $GLOBALS['account_level'] ) ) {
		$levels = apply_filters(
			'virtuaria_pagbank_split_seller_levels',
			array(
				'undefined' => __( 'Indefinido', 'virtuaria-pagbank-split' ),
				'bronze'    => __( 'Bronze', 'virtuaria-pagbank-split' ),
				'silver'    => __( 'Prata', 'virtuaria-pagbank-split' ),
				'gold'      => __( 'Ouro', 'virtuaria-pagbank-split' ),
				'diamond'   => __( 'Diamante', 'virtuaria-pagbank-split' ),
			)
		);
		printf(
			'<p class="account_level %s">Nível: <span class="value">%s</span></p>',
			esc_html( $GLOBALS['account_level'] ),
			esc_html( $levels[ $GLOBALS['account_level'] ] )
		);
	}

	if ( isset( $GLOBALS['sales'] ) ) {
		printf(
			'<p class="sales">Total de vendas: <span class="value">%s</span></p>',
			esc_html( $GLOBALS['sales'] )
		);
	}

	if ( empty( $GLOBALS['reviews'] ) ) {
		echo '<div>Não há avaliações para este Seller.</div>';
	} else {
		echo '<div class="reviews">';
		echo '<ul class="review-title"><li>Autor</li><li>Comentário</li><li>Avaliação</li></ul>';
		foreach ( $GLOBALS['reviews'] as $review ) {
			echo '<div class="review">';
			echo '<div class="author">' . esc_html( $review['author'] ) . '</div>';
			echo '<div class="content">';
			echo '<div class="comment">' . esc_html( $review['comment'] ) . '</div>';
			echo '<div class="product"><a href="' . esc_url( $review['link'] ) . '">' . esc_html( $review['product'] ) . '</a></div>';
			echo '</div>';
			echo '<div class="rating">';
			printf(
				'<div class="start-rating"><span class="star" style="width: %s"></span></div>',
				esc_attr( ( $review['note'] * 100 ) / 5 ) . '%'
			);
			echo '<div class="date">' . esc_html( $review['date'] ) . '</div>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}
} else {
	echo '<h2 class="no-seller">Nenhum vendedor encontrado</h2>';
}

echo '</div>';
get_footer();
