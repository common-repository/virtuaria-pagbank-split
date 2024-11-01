<?php
/**
 * Template to display seller itens.
 *
 * @package virtuaria/pagseguro/split/mails.
 */

defined( 'ABSPATH' ) || exit;

?>
<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align: left"><?php esc_html_e( 'Product', 'virtuaria-pagbank-split' ); ?></th>
				<th class="td" scope="col" style="text-align: left"><?php esc_html_e( 'Quantity', 'virtuaria-pagbank-split' ); ?></th>
				<th class="td" scope="col" style="text-align: left"><?php esc_html_e( 'Price', 'virtuaria-pagbank-split' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $products['products'] as $product ) {
				?>
				<tr>
					<td class="td" style="text-align: left;"><?php echo wp_kses_post( $product['title'] ); ?></td>
					<td class="td" style="text-align: left;"><?php echo wp_kses_post( $product['quantity'] ); ?></td>
					<td class="td" style="text-align: left;"><?php echo wp_kses_post( $product['total'] ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
		<tfoot>
			<?php
			if ( $products['discount'] > 0 ) :
				?>
				<tr>
					<th class="td" scope="row" colspan="2" style="text-align:left;">
						<?php esc_html_e( 'Descontos:', 'virtuaria-pagbank-split' ); ?>
					</th>
					<td class="td" style="text-align:left;">
						<?php
						echo wp_kses_post(
							wc_price(
								$products['discount'],
								array( 'price_format' => '%1$s&nbsp;%2$s' )
							)
						);
						?>
					</td>
				</tr>
				<?php
			endif;
			if ( $order->get_coupon_codes() ) :
				?>
				<tr>
					<th class="td" scope="row" colspan="2" style="text-align:left;">
						<?php esc_html_e( 'Cupons:', 'virtuaria-pagbank-split' ); ?>
					</th>
					<td class="td" style="text-align:left;">
						<?php
						echo wp_kses_post(
							implode( ', ', $order->get_coupon_codes() )
						);
						?>
					</td>
				</tr>
				<?php
			endif;
			?>
			<tr>
				<th class="td" scope="row" colspan="2" style="text-align:left;">
					<?php esc_html_e( 'Total da venda:', 'virtuaria-pagbank-split' ); ?>
				</th>
				<td class="td" style="text-align:left;">
					<?php
					echo wp_kses_post(
						wc_price(
							$products['total_sold'],
							array( 'price_format' => '%1$s&nbsp;%2$s' )
						)
					);
					?>
				</td>
			</tr>
			<?php
			if ( $order->get_customer_note() ) {
				?>
				<tr>
					<th class="td" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Note:', 'virtuaria-pagbank-split' ); ?></th>
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php echo wp_kses_post( wptexturize( $order->get_customer_note() ) ); ?></td>
				</tr>
				<?php
			}
			?>
		</tfoot>
	</table>
</div>

<?php do_action( 'woocommerce_email_after_order_table', $order, true, false, $email ); ?>
