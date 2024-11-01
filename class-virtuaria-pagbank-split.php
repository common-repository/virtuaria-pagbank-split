<?php
/**
 * Plugin Name: Virtuaria PagBank Split
 * Plugin URI: https://virtuaria.com.br
 * Description: Este plugin adiciona split de pagamento via Crédito, Pix ou Boleto do PagSeguro a sua loja virtual Woocommerce. O pagamento é distribuído entre sua conta do PagBank e as respectivas contas da sua rede de parceiros de negócios. É um plugin de fácil adoção. Normalmente não é necessário alterar nada no tema para usá-lo.
 * Version: 1.0.1
 * Author: Virtuaria
 * Author URI: https://virtuaria.com.br/
 * License: GPLv2 or later
 *
 * @package virtuaria/pagseguro/split
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Virtuaria_PagBank_Split' ) ) :
	define( 'VIRTUARIA_PAGBANK_SPLIT_DIR', plugin_dir_path( __FILE__ ) );
	define( 'VIRTUARIA_PAGBANK_SPLIT_URL', plugin_dir_url( __FILE__ ) );
	register_activation_hook( __FILE__, array( 'Virtuaria_PagBank_Split', 'install_pagbank_split' ) );
	register_deactivation_hook( __FILE__, array( 'Virtuaria_PagBank_Split', 'uninstall_pagbank_split' ) );
	/**
	 * Handle split resources.
	 */
	class Virtuaria_PagBank_Split {
		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Initialize functions.
		 */
		public function __construct() {
			if ( ! class_exists( 'Woocommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'missing_woocommerce' ) );
				return;
			}
			if ( ! class_exists( 'Virtuaria_Pagseguro' ) ) {
				add_action( 'admin_notices', array( $this, 'missing_virtuaria_pagbank' ) );
				return;
			}

			add_action( 'virtuaria_pagseguro_save_split_settings', array( $this, 'save_split_settings' ) );

			add_action( 'admin_menu', array( $this, 'setup_menu_split' ) );

			$options = get_option( 'woocommerce_virt_pagseguro_settings' );
			if ( ! isset( $options['split_enabled'] )
				|| 'yes' !== $options['split_enabled'] ) {
				return;
			}

			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			if ( class_exists( 'Virtuaria_Linx_Integration' ) ) {
				remove_action(
					'admin_init',
					array(
						Virtuaria_Linx_Integration::get_instance(),
						'redirect_salesman_to_orders',
					)
				);
				remove_filter(
					'setup_global_menu',
					array(
						Virtuaria_Linx_Integration::get_instance(),
						'salesman_admin_menu',
					)
				);
				remove_action(
					'admin_head',
					array(
						Virtuaria_Linx_Integration::get_instance(),
						'hide_admin_sidebar',
					)
				);
			}

			add_action( 'add_meta_boxes_shop_order', array( $this, 'disable_additional_charge' ), 15 );
			add_filter( 'virtuaria_pagseguro_allow_refund', array( $this, 'disable_partial_refund' ), 10, 3 );
			add_filter(
				'virtuaria_pagseguro_disable_discount_by_cart',
				array( $this, 'disable_pix_discount' ),
				10,
				2
			);

			$this->load_dependecys();
		}

		/**
		 * Load files.
		 */
		private function load_dependecys() {
			require_once 'includes/class-virtuaria-transactions-dao.php';
			require_once 'includes/class-virtuaria-receivers.php';
			require_once 'includes/class-virtuaria-transactions-report.php';
			require_once 'includes/class-virtuaria-receiver-report.php';
			require_once 'includes/class-virtuaria-seller-mails.php';
			require_once 'includes/class-virtuaria-seller-review-page.php';
		}

		/**
		 * Display warning about missing dependency.
		 */
		public function missing_virtuaria_pagbank() {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: plugin link */
							__(
								'Virtuaria PagBank Split precisa do plugin <b>Virtuaria PagSeguro</b> na versão 3.0 ou superior para funcionar! O plugin pode ser obtido clicando <a href="%s" target="_blank">aqui</a>.',
								'virtuaria-pagbank-split'
							),
							'https://wordpress.org/plugins/virtuaria-pagseguro/'
						)
					);
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Display warning about missing dependency.
		 */
		public function missing_woocommerce() {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: plugin link */
							__(
								'Virtuaria PagBank Split precisa do plugin <b>Woocommerce</b> na versão 4.0 ou superior para funcionar! O plugin pode ser obtido clicando <a href="%s" target="_blank">aqui</a>.',
								'virtuaria-pagbank-split'
							),
							'https://wordpress.org/plugins/woocommerce/'
						)
					);
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Display settings.
		 */
		public function split_setup_fields() {
			require_once VIRTUARIA_PAGBANK_SPLIT_DIR . 'templates/split-settings.php';
		}

		/**
		 * Update main settings.
		 */
		public function save_split_settings() {
			if ( isset( $_POST['setup_nonce'] )
				&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['setup_nonce'] ) ), 'setup_virtuaria_split' ) ) {
				$options   = get_option( 'woocommerce_virt_pagseguro_settings' );

				if ( isset( $_POST['woocommerce_virt_pagseguro_seller_identifier'] ) ) {
					$options['seller_identifier'] = sanitize_text_field(
						wp_unslash(
							$_POST['woocommerce_virt_pagseguro_seller_identifier']
						)
					);
				}

				if ( isset( $_POST['woocommerce_virt_pagseguro_main_fee'] ) ) {
					$options['main_fee'] = sanitize_text_field(
						wp_unslash(
							$_POST['woocommerce_virt_pagseguro_main_fee']
						)
					);
				}

				if ( ! isset( $_POST['woocommerce_virt_pagseguro_split_enabled'] ) ) {
					unset( $options['split_enabled'] );
				} else {
					$options['split_enabled'] = 'yes';
				}

				if ( ! isset( $_POST['woocommerce_virt_pagseguro_hide_shipping_method'] ) ) {
					unset( $options['hide_shipping_method'] );
				} else {
					$options['hide_shipping_method'] = 'yes';
				}

				if ( ! isset( $_POST['woocommerce_virt_pagseguro_hide_cpf'] ) ) {
					unset( $options['hide_cpf'] );
				} else {
					$options['hide_cpf'] = 'yes';
				}

				if ( ! isset( $_POST['woocommerce_virt_pagseguro_hide_address'] ) ) {
					unset( $options['hide_address'] );
				} else {
					$options['hide_address'] = 'yes';
				}

				if ( ! isset( $_POST['woocommerce_virt_pagseguro_hide_unpurchasable_products'] ) ) {
					unset( $options['hide_unpurchasable_products'] );
				} else {
					$options['hide_unpurchasable_products'] = 'yes';
				}

				if ( ! isset( $_POST['woocommerce_virt_pagseguro_hide_coupons'] ) ) {
					unset( $options['hide_coupons'] );
				} else {
					$options['hide_coupons'] = 'yes';
				}

				if ( ! isset( $_POST['woocommerce_virt_pagseguro_hide_reputation'] ) ) {
					unset( $options['hide_reputation'] );
				} else {
					$options['hide_reputation'] = 'yes';
				}

				if ( ! isset( $_POST['woocommerce_virt_pagseguro_hide_total_sales'] ) ) {
					unset( $options['hide_total_sales'] );
				} else {
					$options['hide_total_sales'] = 'yes';
				}

				update_option(
					'woocommerce_virt_pagseguro_settings',
					$options
				);
			}
		}

		/**
		 * Unistall plugin.
		 */
		public static function uninstall_pagbank_split() {
			do_action( 'unistall_virtuaria_pagbank_split' );
		}

		/**
		 * Unistall plugin.
		 */
		public static function install_pagbank_split() {
			require_once 'includes/class-virtuaria-transactions-dao.php';
			new Virtuaria_Transactions_DAO();
			do_action( 'install_virtuaria_pagbank_split' );
		}

		/**
		 * Disables additional charge.
		 *
		 * @return void
		 */
		public function disable_additional_charge() {
			remove_meta_box( 'pagseguro-additional-charge', 'shop_order', 'side' );
		}

		/**
		 * Disable partial refund.
		 *
		 * @param bool     $allow      The flag indicating if partial refund is allowed.
		 * @param wc_order $order      The order object.
		 * @param float    $refund_qtd The quantity of the refund.
		 * @return bool The updated flag indicating if partial refund is allowed.
		 */
		public function disable_partial_refund( $allow, $order, $refund_qtd ) {
			if ( $refund_qtd != $order->get_total() ) {
				$order->add_order_note(
					__( 'PagBank: Apenas reembolsos totais são permitidos.', 'virtuaria-pagbank-split' ),
					0,
					true
				);
				$allow = false;
			}
			return $allow;
		}

		/**
		 * Disables the pix discount based on the split enabled option.
		 *
		 * @param bool    $disable The current state of the pix discount.
		 * @param wc_cart $cart    The cart object.
		 * @return bool The updated state of the pix discount.
		 */
		public function disable_pix_discount( $disable, $cart ) {
			$options = get_option( 'woocommerce_virt_pagseguro_settings' );
			if ( isset( $options['split_enabled'] )
				&& 'yes' === $options['split_enabled'] ) {
				$disable = true;
			}
			return $disable;
		}

		/**
		 * Add menu 'Virtuaria Split'.
		 */
		public function setup_menu_split() {
			add_menu_page(
				__( 'Virtuaria Split', 'virtuaria-pagbank-split' ),
				__( 'Virtuaria Split', 'virtuaria-pagbank-split' ),
				'remove_users',
				'virtuaria_pagbank_split',
				array( $this, 'split_setup_fields' ),
				VIRTUARIA_PAGBANK_SPLIT_URL . 'admin/images/virtuaria.png'
			);
		}

		/**
		 * Load the plugin text domain for translation.
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain(
				'virtuaria-pagbank-split',
				false,
				dirname( plugin_basename( __FILE__ ) ) . '/languages/'
			);
		}
	}

	add_action( 'plugins_loaded', array( 'Virtuaria_PagBank_Split', 'get_instance' ) );

endif;
