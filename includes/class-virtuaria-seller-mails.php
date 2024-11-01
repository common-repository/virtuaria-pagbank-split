<?php
/**
 * Send transactions mails to sellers.
 *
 * @package virtuaria/pagseguro/split/mails
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle transactions mails.
 */
class Virtuaria_Seller_Mails {
	/**
	 * Split settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_virt_pagseguro_settings' );
		if ( isset( $this->settings['split_enabled'] )
			&& 'yes' === $this->settings['split_enabled'] ) {
			add_action( 'woocommerce_order_status_changed', array( $this, 'send_mails' ), 10, 4 );
		}
	}

	/**
	 * Send transactions mails to sellers.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param wc_order $order Order object.
	 */
	public function send_mails( $order_id, $old_status, $new_status, $order ) {
		if ( ! in_array(
			$order->get_payment_method(),
			array(
				'virt_pagseguro',
				'virt_pagseguro_credit',
				'virt_pagseguro_pix',
				'virt_pagseguro_ticket',
			),
			true
		) ) {
			return;
		}

		$receiver_products = $this->get_receivers_products_from_order( $order );
		if ( $receiver_products ) {
			foreach ( $receiver_products as $receiver_email => $products ) {
				$this->send_mail(
					$receiver_email,
					$products,
					$order,
					$new_status
				);
			}
		}
	}

	/**
	 * Get receivers products from order.
	 *
	 * @param wc_order $order Order object.
	 * @return array
	 */
	private function get_receivers_products_from_order( $order ) {
		$receiver_products = array();
		/**
		 * Get receivers info.
		 *
		 * @var WC_Order_Item_Product $item item from order.
		 */
		foreach ( $order->get_items() as $item ) {
			$product_id  = $item->get_product_id();
			$receiver_id = get_post_meta( $product_id, '_receiver_id', true );

			if ( ! $receiver_id ) {
				continue;
			}

			$receiver = get_user_by( 'id', $receiver_id );

			if ( $receiver ) {
				$receiver_products[ $receiver->user_email ]['products'][] = array(
					'product_id' => $product_id,
					'title'      => $item->get_product()->get_title(),
					'total'      => wc_price(
						$item->get_subtotal(),
						array( 'price_format' => '%1$s&nbsp;%2$s' )
					),
					'quantity'   => $item->get_quantity(),
				);

				$receiver_products[ $receiver->user_email ]['discount']   += $item->get_subtotal() - $item->get_total();
				$receiver_products[ $receiver->user_email ]['total_sold'] += $item->get_total();
			}
		}

		return $receiver_products;
	}

	/**
	 * Sends an email with the given receiver email, products, order, and new status.
	 *
	 * @param string   $receiver_email The email address of the receiver.
	 * @param array    $products An array of products.
	 * @param wc_order $order The order object.
	 * @param string   $new_status The new status of the order.
	 * @return int|bool The number of successful recipients or false on failure.
	 */
	private function send_mail( $receiver_email, $products, $order, $new_status ) {
		if ( 'pending' === $new_status ) {
			return;
		}

		switch ( $new_status ) {
			case 'on-hold':
				$email_heading = 'Novo pedido recebido';
				$message       = sprintf(
					'Novo pedido #%1$s de %2$s, recebido em %3$s.',
					$order->get_id(),
					$order->get_formatted_billing_full_name(),
					$order->get_date_created()->date_i18n( wc_date_format() )
				);
				break;
			case 'processing':
				$email_heading = 'Pagamento aprovado';
				$message       = sprintf(
					'Recebemos a confirmação de pagamento do pedido #%1$s, recebido em %2$s.',
					$order->get_id(),
					$order->get_date_created()->date_i18n( wc_date_format() )
				);
				break;
			case 'completed':
				$email_heading = 'Pedido concluído';
				$message       = sprintf(
					'O pedido #%1$s de %2$s foi concluído.',
					$order->get_id(),
					$order->get_date_created()->date_i18n( wc_date_format() )
				);
				break;
			case 'refunded':
				$email_heading = 'Pedido reembolsado';
				$message       = sprintf(
					'O pedido #%1$s de %2$s foi reembolsado.',
					$order->get_id(),
					$order->get_date_created()->date_i18n( wc_date_format() )
				);
				break;
			default:
				$email_heading = 'Pedido cancelado';
				$message       = sprintf(
					'O pedido #%1$s de %2$s foi cancelado.',
					$order->get_id(),
					$order->get_date_created()->date_i18n( wc_date_format() )
				);
				break;
		}

		ob_start();

		echo esc_html( $message ) . '<br/><br/>';

		echo 'Abaixo seguem os itens do pedido para referência: <br/><br/>';

		require VIRTUARIA_PAGBANK_SPLIT_DIR . 'templates/seller-itens.php';

		do_action( 'woocommerce_email_order_meta', $order, true, false );

		if ( ! isset( $this->settings['hide_address'] )
			|| 'yes' !== $this->settings['hide_address'] ) {
			/*
			* @hooked WC_Emails::customer_details() Shows customer details
			* @hooked WC_Emails::email_address() Shows email address
			*/
			do_action( 'woocommerce_email_customer_details', $order, true, false );
		}

		$message = ob_get_clean();
		$message = WC()->mailer()->wrap_message(
			$email_heading,
			$message
		);

		$email_heading = str_replace(
			array(
				'Novo pedido',
				'Pedido',
			),
			'',
			$email_heading
		);
		return WC()->mailer()->send(
			$receiver_email,
			sprintf(
				'[%1$s] Pedido #%2$s %3$s',
				get_bloginfo( 'name' ),
				$order->get_id(),
				mb_strtolower( $email_heading )
			),
			$message,
		);
	}
}

new Virtuaria_Seller_Mails();
