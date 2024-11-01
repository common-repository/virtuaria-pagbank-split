<?php
/**
 * Template setup receiver profile.
 *
 * @package virtuaria/pagseguro/split
 */

defined( 'ABSPATH' ) || exit;

$receiver = get_user_meta(
	$user->ID,
	'_virtuaria_receiver',
	true
);

$receiver = $receiver ? $receiver : array();

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
?>
<hr>
<h3>Definições do Seller</h3>
<table class="form-table">
	<tr>
		<th>
			<label for="receiver-status">Status</label>
		</th>
		<td>
			<select name="receiver_status"
				id="receiver-status">
				<option
					<?php
					if ( isset( $receiver['status'] ) ) {
						selected( 'active', $receiver['status'] );
					}
					?>
					value="active">Ativo</option>
				<option
					<?php
					if ( isset( $receiver['status'] ) ) {
						selected( 'deactive', $receiver['status'] );
					}
					?>
					value="deactive">Inativo</option>
			</select>
		</td>
	</tr>
	<tr>
		<th>
			<label for="receiver-account">Código da conta</label>
		</th>
		<td>
			<?php echo isset( $receiver['account'] ) ? esc_attr( $receiver['account'] ) : 'A definir'; ?>
		</td>
	</tr>
	<tr>
		<th>
			<label for="receiver-fee">Taxa Personalizada (%)</label>
		</th>
		<td>
			<input type="number"
				step="0.01"
				min="0"
				name="receiver_fee"
				id="receiver-fee"
				value="<?php echo isset( $receiver['fee'] ) ? esc_attr( $receiver['fee'] ) : ''; ?>" class="regular-text" />
		</td>
	</tr>
	<tr valign="top" class="shop-page">
		<th scope="row" class="titledesc">
			<label for="shop-page">
				Lista de Produtos
			</label>
		</th>
		<td class="forminp forminp-auth">
			<?php
			$seller_page = home_url( 'seller/' . $user->user_login );
			printf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( $seller_page ),
				esc_url( $seller_page )
			);
			?>
		</td>
	</tr>
	<tr valign="top" class="level-account">
		<th scope="row" class="titledesc">
			<label for="level-account">
				Nível do Seller
			</label>
		</th>
		<td class="forminp forminp-auth">
			<select name="receiver_level" id="level-account">
				<?php
				foreach ( $levels as $key => $value ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $key ),
						isset( $receiver['level'] ) && $receiver['level'] === $key ? 'selected' : '',
						esc_attr( $value )
					);
				}
				?>
			</select>
		</td>
	</tr>
</table>
<hr>
