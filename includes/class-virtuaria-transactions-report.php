<?php
/**
 * Handle Report Receiver.
 *
 * @package virtuaria/pagseguro/split/report
 */

defined( 'ABSPATH' ) || exit;

/**
 * Report from sells to receiver.
 *
 * @since 1.0.0
 */
class Virtuaria_Transactions_Report {
	/**
	 * Instance from Virtuaria_Transactions_DAO class.
	 *
	 * @var Virtuaria_Transactions_DAO
	 */
	private $dao;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		$this->dao      = new Virtuaria_Transactions_DAO();
		$this->settings = get_option( 'woocommerce_virt_pagseguro_settings' );

		add_action( 'virtuaria_split_receivers_info', array( $this, 'new_item_report' ), 10, 2 );
		add_action( 'virtuaria_pagseguro_succesfull_create_order', array( $this, 'on_create_order' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'seller_report_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_styles_scripts' ), 20 );
	}

	/**
	 * Add new order in report.
	 *
	 * @param array    $receivers receivers.
	 * @param WC_Order $order      order.
	 */
	public function new_item_report( $receivers, $order ) {
		foreach ( $receivers as $receiver ) {
			$data = array(
				'order_id'        => $order->get_id(),
				'seller_name'     => $this->get_seller_name( $receiver['receiver_id'] ),
				'seller_id'       => $receiver['receiver_id'],
				'received'        => $this->convert_to_float(
					$receiver['received']
				),
				'total_sold'      => $this->convert_to_float(
					$receiver['total_sold']
				),
				'fee'             => $this->get_effective_fee( $receiver ),
				'itens'           => wp_json_encode( $receiver['products'] ),
				'created_at'      => gmdate( 'Y-m-d H:i:s' ),
				'customer'        => sprintf(
					'%s<p class="contact">Telefone: <a href="tel:%s">%s</a><br>E-mail: <a href="mailto:%s">%s</a></p>',
					$order->get_formatted_shipping_address()
						? $order->get_formatted_shipping_address()
						: $order->get_formatted_billing_address(),
					$order->get_billing_phone(),
					$order->get_billing_phone(),
					$order->get_billing_email(),
					$order->get_billing_email(),
				),
				'account_id'      => $receiver['account_id'],
				'production'      => $receiver['production'],
				'payment_method'  => $this->get_payment_method(
					$order
				),
				'shipping_method' => $order->get_shipping_method(),
				'coupons'         => implode( ', ', $order->get_coupon_codes() ),
				'cpf'             => $order->get_meta( '_billing_cpf' ),
			);
			$this->dao->add_record( $data );
		}
	}

	/**
	 * Converts a value to a float and does not round.
	 *
	 * @param mixed $value The value to be converted.
	 * @return float The converted float value.
	 */
	private function convert_to_float( $value ) {
		return explode(
			'.',
			strval( $value * 100 )
		)[0] / 100.0;
	}

	/**
	 * Retrieves the payment method for the given order.
	 *
	 * @param object $order The order object.
	 * @return string The payment method.
	 */
	private function get_payment_method( $order ) {
		if ( 'virt_pagseguro' === $order->get_payment_method()
			&& (
				( isset( $_POST['new_charge_nonce'] )
					&& wp_verify_nonce(
						sanitize_text_field(
							wp_unslash( $_POST['new_charge_nonce'] )
						),
						'do_new_charge'
					)
				)
				|| ( isset( $_POST['virt_pagseguro_credit_nonce'] )
					&& wp_verify_nonce(
						sanitize_text_field(
							wp_unslash( $_POST['virt_pagseguro_credit_nonce'] )
						),
						'do_new_charge'
					)
				) || ( isset( $_POST['virt_pagseguro_pix_nonce'] )
					&& wp_verify_nonce(
						sanitize_text_field(
							wp_unslash( $_POST['virt_pagseguro_pix_nonce'] )
						),
						'do_new_charge'
					)
				) || ( isset( $_POST['virt_pagseguro_ticket_nonce'] )
					&& wp_verify_nonce(
						sanitize_text_field(
							wp_unslash( $_POST['virt_pagseguro_ticket_nonce'] )
						),
						'do_new_charge'
					)
				)
			) && isset( $_POST['payment_mode'] ) ) {
			switch ( $_POST['payment_mode'] ) {
				case 'pix':
					return 'Pix';
				case 'ticket':
					return 'Boleto';
				default:
					return 'Crédito';
			}
		} else {
			switch ( $order->get_payment_method() ) {
				case 'virt_pagseguro_pix':
					return 'Pix';
				case 'virt_pagseguro_ticket':
					return 'Boleto';
				default:
					return 'Crédito';
			}
		}
	}

	/**
	 * Retrieves the name of the seller based on the user ID.
	 *
	 * @param int $user_id The ID of the user.
	 * @return string The name of the seller.
	 */
	private function get_seller_name( $user_id ) {
		if ( ! $user_id ) {
			return get_bloginfo( 'name' );
		}

		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			return $user->display_name;
		}

		return '';
	}

	/**
	 * Active or remove records when a new order is created.
	 *
	 * @param boolean  $success description.
	 * @param wc_order $order description.
	 */
	public function on_create_order( $success, $order ) {
		if ( $success ) {
			$this->dao->active_records(
				$order->get_id(),
				'production' === $this->settings['environment'] ? 1 : 0
			);
		} else {
			$this->dao->remove_records(
				$order->get_id(),
				'production' === $this->settings['environment'] ? 1 : 0
			);
		}
	}

	/**
	 * Setup seller report menu.
	 *
	 * @return void
	 */
	public function seller_report_submenu() {
		if ( $this->current_user_is_receiver() ) {
			add_submenu_page(
				'woocommerce',
				__( 'Transações', 'virtuaria-pagbank-split' ),
				__( 'Transações', 'virtuaria-pagbank-split' ),
				'edit_products',
				'seller_performance',
				array( $this, 'seller_report' )
			);
		} else {
			add_submenu_page(
				'virtuaria_pagbank_split',
				__( 'Transações', 'virtuaria-pagbank-split' ),
				__( 'Transações', 'virtuaria-pagbank-split' ),
				'remove_users',
				'shop_performance',
				array( $this, 'shop_report' ),
				5
			);
		}
	}

	/**
	 * Check receiver is current user.
	 */
	private function current_user_is_receiver() {
		$roles = wp_get_current_user()->roles;
		return ! in_array(
			'administrator',
			$roles,
			true
		) && ! in_array(
			'customer',
			$roles,
			true
		) && (
			in_array(
				'virtuaria_receiver',
				$roles,
				true
			)
			|| in_array(
				'seller',
				$roles,
				true
			)
		);
	}

	/**
	 * Generates a seller report.
	 */
	public function seller_report() {
		?>
		<h1 class="list-title">Transações</h1>
		<p>Relatório de transações ocorridas na loja virtual. A coluna Status refere-se ao status atual do pedido.<button class="button print-report button-primary" onclick="window.print()">Imprimir Relatório</button></p>
		<div class="date-filter">
			<h2>Filtros</h2>
			<label for="min-date">De:</label>
			<input type="date" name="min_date" id="min-date" />
			<label for="max-date">Até:</label>
			<input type="date" name="max_date" id="max-date" />
			<label for="status">Status:</label>
			<select name="status" id="status">
				<option value="">Todos</option>
				<?php
				$allowed_status = wc_get_order_statuses();
				unset( $allowed_status['wc-pending'] );
				unset( $allowed_status['wc-cancelled'] );
				unset( $allowed_status['wc-failed'] );
				unset( $allowed_status['wc-refunded'] );
				unset( $allowed_status['wc-checkout-draft'] );
				unset( $allowed_status['wc-refund-req'] );

				foreach ( $allowed_status as $status ) {
					printf(
						'<option value="%s">%s</option>',
						esc_html( $status ),
						esc_html( $status )
					);
				}
				?>
			</select>
			<?php
			$uri = isset( $_SERVER['REQUEST_URI'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
				: '';
			if ( $uri ) {
				if ( ! isset( $_GET['show_cancelled'] ) ) {
					?>
					<a href="<?php echo esc_url( home_url( $uri ) . '&show_cancelled=yes' ); ?>"
						class="button button-primary" id="transations-cancel">
						Mostrar Canceladas
					</a>
					<?php
				} else {
					?>
					<a href="<?php echo esc_url( home_url( str_replace( '&show_cancelled=yes', '', $uri ) ) ); ?>"
						class="button button-primary" id="transations-cancel">
						Ocultar Canceladas
					</a>
					<?php
				}
			}
			?>
		</div>
		<table id="all-transations-table" class="display" width="100%"></table>
		<div class="chart-container">
			<h2 class="graphics">Estatísticas de Vendas</h2>
			<select id="selectVista">
				<option value="diario">Diária</option>
				<option value="mensal">Mensal</option>
			</select>
			<canvas id="salesChart"></canvas>
		</div>
		<?php
	}

	/**
	 * Generates a shop report.
	 */
	public function shop_report() {
		$this->seller_report();
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param String $hook hook page.
	 */
	public function add_admin_styles_scripts( $hook ) {
		$dir_path = plugin_dir_path( __FILE__ ) . '../admin/';
		$dir_url  = plugin_dir_url( __FILE__ ) . '../admin/';

		$report_pages = array(
			'woocommerce_page_seller_performance',
			'virtuaria-split_page_shop_performance',
		);

		if ( in_array( $hook, $report_pages, true ) ) {
			wp_enqueue_script(
				'datatables.min',
				$dir_url . 'datatables/datatables.min.js',
				array( 'jquery' ),
				filemtime( $dir_path . 'datatables/datatables.min.js' ),
				true
			);

			wp_enqueue_style(
				'datatables.min-css',
				$dir_url . 'datatables/datatables.min.css',
				array(),
				filemtime( $dir_path . 'datatables/datatables.min.css' )
			);

			wp_enqueue_script(
				'chartjs',
				$dir_url . 'chart.js/chart.min.js',
				array(),
				'4.4.1',
				true
			);

			wp_enqueue_script(
				'report-base',
				$dir_url . 'js/reports.js',
				array( 'jquery' ),
				filemtime( $dir_path . 'js/reports.js' ),
				true
			);
		}

		if ( 'woocommerce_page_seller_performance' === $hook ) {
			wp_enqueue_style(
				'seller-report',
				$dir_url . 'css/seller-report.css',
				array(),
				filemtime( $dir_path . 'css/seller-report.css' )
			);

			wp_enqueue_script(
				'seller-report',
				$dir_url . 'js/seller-report.js',
				array( 'jquery' ),
				filemtime( $dir_path . 'js/seller-report.js' ),
				true
			);

			wp_localize_script(
				'seller-report',
				'data',
				$this->transaction_formatter(
					$this->dao->get_records_by_seller_id(
						get_current_user_id(),
						'production' === $this->settings['environment'] ? 1 : 0
					)
				)
			);

			wp_localize_script(
				'seller-report',
				'sales',
				$this->format_sales(
					$this->dao->get_sales(
						get_current_user_id(),
						'production' === $this->settings['environment'] ? 1 : 0
					)
				)
			);
		}

		if ( 'virtuaria-split_page_shop_performance' === $hook ) {
			wp_enqueue_style(
				'manager-report',
				$dir_url . 'css/manager-report.css',
				array(),
				filemtime( $dir_path . 'css/manager-report.css' )
			);

			wp_enqueue_script(
				'manager-report',
				$dir_url . 'js/manager-report.js',
				array( 'jquery' ),
				filemtime( $dir_path . 'js/manager-report.js' ),
				true
			);

			wp_localize_script(
				'manager-report',
				'data',
				$this->transaction_formatter(
					$this->dao->get_all_records(
						'production' === $this->settings['environment'] ? 1 : 0
					)
				)
			);

			wp_localize_script(
				'manager-report',
				'sales',
				$this->format_sales(
					$this->dao->get_sales(
						false,
						'production' === $this->settings['environment'] ? 1 : 0
					)
				)
			);
		}
	}

	/**
	 * Format the transactions.
	 *
	 * @param array $transactions The transactions to be formatted.
	 * @return array The formatted transactions.
	 */
	private function transaction_formatter( $transactions ) {
		$formatted_transactions = array();
		if ( ! empty( $transactions ) ) {
			$statuses = wc_get_order_statuses();
			foreach ( $transactions as $transaction ) {
				$order = wc_get_order( $transaction['order_id'] );

				$order_status = 'wc-' . $order->get_status();
				if ( ! $order || ! isset( $_GET['show_cancelled'] )
					&& in_array(
						$order_status,
						array( 'wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-pending' ),
						true
					) ) {
					continue;
				}
				if ( isset( $transaction['order_id'] ) ) {
					$transaction['status'] = sprintf(
						'<span class="%s">%s</span>',
						$order_status,
						$statuses[ $order_status ]
					);
					if ( ! $this->current_user_is_receiver() ) {
						$transaction['order_id'] = sprintf(
							'<a href="%s">#%s</a>',
							$order->get_edit_order_url(),
							$transaction['order_id']
						);
					}
				}

				$sold_by_platform    = 0;
				$received_commission = 0;

				if ( isset( $transaction['itens'] ) ) {
					$itens = json_decode( $transaction['itens'], true );

					$html_itens   = array();
					$html_itens[] = '<button class="see-itens button-primary">Ver +</button>';
					$html_itens[] = '<div class="itens-modal">';
					$html_itens[] = '<div class="customer">';
					$html_itens[] = sprintf(
						'<h3>%1$s - <span class="order-id">%2$s %3$s</span></h3>',
						__( 'Detalhes do Pedido', 'virtuaria-pagbank-split' ),
						$transaction['order_id'],
						$this->current_user_is_receiver()
							? ''
							: ucwords( $transaction['seller_name'] )
					);
					if ( ( ! $this->current_user_is_receiver()
						|| ( ! isset( $this->settings['hide_address'] )
						|| 'yes' !== $this->settings['hide_address'] ) )
						&& $transaction['customer'] ) {
						$html_itens[] = str_replace( '<br/>', '. ', $transaction['customer'] );
					}
					if ( ( ! $this->current_user_is_receiver()
						|| ( ! isset( $this->settings['hide_cpf'] )
						|| 'yes' !== $this->settings['hide_cpf'] ) )
						&& $transaction['cpf'] ) {
						$html_itens[] = sprintf(
							'<p class="customer-cpf">CPF: %s</p>',
							$transaction['cpf']
						);
					}
					if ( ( ! $this->current_user_is_receiver()
						|| ( ! isset( $this->settings['hide_shipping_method'] )
						|| 'yes' !== $this->settings['hide_shipping_method'] ) )
						&& $transaction['shipping_method'] ) {
						$html_itens[] = sprintf(
							'<p class="shipping-method">Método de entrega: %s</p>',
							$transaction['shipping_method']
						);
					}

					$html_itens[]            = '</div>';
					$html_itens['products']  = '<ul class="itens">';
					$html_itens['products'] .= '<li class="header"><ul><li>SKU</li><li>Nome</li><li>Qtd</li><li>Preço</li><li>Subtotal</li><li>Comissão</li></ul></li>';
					foreach ( $itens as &$item ) {
						if ( ! $transaction['seller_id'] ) {
							if ( 0 === $item['fee'] ) {
								$sold_by_platform += $item['subtotal'];
							} else {
								$received_commission += $item['fee'];
								continue;
							}
						}
						if ( isset( $item['product_id'] ) ) {
							$html_itens['products'] .= '<li><ul><li>' . $item['sku'] . '</li>';
							$html_itens['products'] .= '<li>' . sprintf(
								'<a href="%s">#%s</a>',
								get_edit_post_link( $item['product_id'] ),
								$item['title']
							) . '</li>';
							$html_itens['products'] .= '<li>' . $item['quantity'] . '</li>';
							$html_itens['products'] .= '<li>' . wc_price(
								$item['price'],
								array( 'price_format' => '%1$s&nbsp;%2$s' )
							) . '</li>';
							$html_itens['products'] .= '<li>' . wc_price(
								$item['subtotal'],
								array( 'price_format' => '%1$s&nbsp;%2$s' )
							);
							if ( $item['discount'] ) {
								$html_itens['products'] .= '<span class="discount">'
									. wc_price(
										$item['discount'],
										array( 'price_format' => '%1$s&nbsp;%2$s' )
									)
									. ' <span class="discount-text">de desconto</span></span>';
							}
							$html_itens['products'] .= '</li>';
							$html_itens['products'] .= '<li>' . wc_price(
								$item['fee'],
								array( 'price_format' => '%1$s&nbsp;%2$s' )
							) . '</li></ul></li>';
						}
					}

					$html_itens['products'] .= sprintf(
						'<li class="total-line"><ul><li></li><li></li><li></li><li class="total">TOTAL</li><li class="sold">%s</li><li class="tax">%s</li></ul></li>',
						wc_price(
							$sold_by_platform
								? $sold_by_platform
								: $transaction['total_sold'],
							array( 'price_format' => '%1$s&nbsp;%2$s' )
						),
						isset( $transaction['seller_id'] )
							? wc_price(
								$transaction['total_sold'] - $transaction['received'],
								array( 'price_format' => '%1$s&nbsp;%2$s' )
							)
							: 'R$ 0,00',
					);

					$html_itens['products'] .= '</ul><!-- end itens list -->';

					if ( ( ! isset( $this->settings['hide_coupons'] )
						|| 'yes' !== $this->settings['hide_coupons'] )
						&& $transaction['coupons'] ) {
						$html_itens[] = sprintf(
							'<p class="coupons">Cupons: %s</p>',
							mb_strtoupper( $transaction['coupons'] )
						);
					}

					if ( ! isset( $transaction['seller_id'] ) ) {
						if ( ! $sold_by_platform ) {
							unset( $html_itens['products'] );
						}

						$html_itens[] = '<p class="total">Total do pedido: ' . wc_price(
							$transaction['total_sold'],
							array( 'price_format' => '%1$s&nbsp;%2$s' )
						) . '</p>';
						$html_itens[] = '<p class="received">Total recebido: ' . wc_price(
							$transaction['received'],
							array( 'price_format' => '%1$s&nbsp;%2$s' )
						) . '</p>';
						$html_itens[] = '<p class="commission">Comissões: ' . wc_price(
							$received_commission,
							array( 'price_format' => '%1$s&nbsp;%2$s' )
						) . '</p>';

						$html_itens[] = '<small class="disclaimer">No Total Recebido estão inclusos juros de parcelamento, frete e outros valores extras incluídos no pedido, caso existam.</small>';
					}

					$transaction['itens'] = implode( '', $html_itens ) . '</div><!--end itens modal-->';
				}

				$fee = $transaction['fee'];
				if ( isset( $transaction['fee'] ) ) {
					$transaction['fee'] = wc_price(
						$transaction['total_sold'] - $transaction['received'],
						array( 'price_format' => '%1$s&nbsp;%2$s' )
					) . ' <span class="comission">(' . round( 100 - $fee ) . '%)</span>';
				}

				if ( isset( $transaction['received'] ) ) {
					$transaction['received'] = wc_price(
						$transaction['received'],
						array( 'price_format' => '%1$s&nbsp;%2$s' )
					);
				}

				if ( isset( $transaction['total_sold'] ) ) {
					$transaction['total_sold'] = wc_price(
						$transaction['total_sold'],
						array( 'price_format' => '%1$s&nbsp;%2$s' )
					);
				}

				if ( isset( $transaction['created_at'] ) ) {
					$date = DateTime::createFromFormat(
						'Y-m-d H:i:s',
						$transaction['created_at']
					);

					$transaction['created_at'] = $date->format( 'd/m/Y H:i:s' );
				}

				if ( isset( $transaction['payment_method'] ) ) {
					$transaction['payment_method'] = ucfirst(
						$transaction['payment_method']
					);
				} else {
					$transaction['payment_method'] = '-';
				}

				if ( ! $transaction['seller_id'] ) {
					if ( ! $sold_by_platform ) {
						$transaction['fee']        = '-';
						$transaction['total_sold'] = '-';
						$transaction['received']   = '-';
					} else {
						$transaction['fee']        = '<b>R$ 0,00</b> <span class="comission">(0%)</span>';
						$transaction['total_sold'] = wc_price(
							$sold_by_platform,
							array( 'price_format' => '%1$s&nbsp;%2$s' )
						);
						$transaction['received']   = $transaction['total_sold'];
					}
				}
				$formatted_transactions[] = $transaction;
			}
		}

		return $formatted_transactions;
	}

	/**
	 * Calculates the effective fee for a receiver.
	 *
	 * @param array $receiver An array containing the receiver's data including 'received' and 'total_sold'.
	 * @return float The effective fee for the receiver.
	 */
	private function get_effective_fee( $receiver ) {
		$fee = ( $receiver['received'] / $receiver['total_sold'] ) * 1000;

		return $this->convert_to_float(
			$fee
		) / 10;
	}

	/**
	 * Formats an array of sales data.
	 *
	 * This function takes an array of sales data and formats it into a new array,
	 * where each sale is grouped by the 'created_at' value. The formatted array
	 * contains the following information for each sale:
	 *
	 * - 'received': The received amount, formatted as a price with a custom format.
	 * - 'total_sold': The total sold amount, formatted as a price with a custom format.
	 *
	 * @param array $sales The array of sales data to be formatted.
	 * @return array The formatted array of sales data.
	 */
	public function format_sales( $sales ) {
		$formatted = array();
		if ( $sales ) {
			foreach ( $sales as $sale ) {
				if ( $sale['created_at'] ) {
					$received = $sale['received'];

					if ( ! $this->current_user_is_receiver() ) {
						$received = $sale['total'] - $received;
					}
					$formatted[ explode( ' ', $sale['created_at'] )[0] ] = array(
						'received_seller' => floatval( $received ),
						'total_sold'      => floatval( $sale['total'] ),
						'received'        => floatval( $sale['received'] ),
					);
				}
			}
		}
		return $formatted;
	}
}

new Virtuaria_Transactions_Report();
