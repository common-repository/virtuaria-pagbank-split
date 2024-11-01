<?php
/**
 * Handle Split Receiver.
 *
 * @package virtuaria/pagseguro/split
 */

defined( 'ABSPATH' ) || exit;

/**
 * Definition.
 */
class Virtuaria_Receivers {
	/**
	 * Settings.
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
			add_action( 'init', array( $this, 'create_receiver_role' ) );
			add_action( 'admin_menu', array( $this, 'remove_defaults_menus' ), 100 );
			add_filter( 'setup_global_menu', array( $this, 'remove_menus' ) );
			add_action( 'admin_init', array( $this, 'prevent_unauthorized_access' ) );
			add_action( 'edit_user_profile', array( $this, 'profile_receiver' ) );
			add_action( 'edit_user_profile_update', array( $this, 'save_profile_receiver' ) );
			add_action( 'save_post_product', array( $this, 'bind_product_and_receiver' ), 20, 3 );
			add_action( 'dokan_new_product_added', array( $this, 'bind_dokan_product_and_receiver' ) );
			add_filter( 'pre_get_posts', array( $this, 'display_receiver_products' ), 20 );
			add_filter( 'views_edit-product', array( $this, 'product_list_filters' ) );
			add_filter( 'woocommerce_is_purchasable', array( $this, 'deactivate_sell_products' ), 10, 2 );
			add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'deactivate_sell_products' ), 10, 2 );
			add_filter( 'virtuaria_pagseguro_split_charges', array( $this, 'add_split_receivers' ), 10, 3 );
			add_filter( 'manage_edit-product_columns', array( $this, 'receiver_product_admin_column' ) );
			add_action( 'manage_product_posts_custom_column', array( $this, 'receiver_product_admin_column_content' ), 10, 2 );
			add_action( 'admin_head-edit.php', array( $this, 'set_product_owner_column_width' ) );
			add_action( 'woocommerce_single_product_summary', array( $this, 'add_product_owner_identity' ), 15 );
			add_action( 'woocommerce_after_order_itemmeta', array( $this, 'add_product_owner_identity_in_order' ), 10, 2 );
			add_action( 'add_meta_boxes', array( $this, 'receiver_product_metabox' ) );
			add_action( 'save_post_product', array( $this, 'save_receiver_id' ) );
			add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_add_receiver' ), 10, 2 );
			add_action( 'admin_footer', array( $this, 'prevent_update_seller_exists' ) );
			add_action( 'admin_init', array( $this, 'save_account_id' ), 20 );
			add_action( 'admin_init', array( $this, 'redirect_before_update_receiver_token' ), 5 );
			add_action( 'admin_init', array( $this, 'redirect_access_dashboard' ) );
			add_action( 'init', array( $this, 'add_wocommerce_settings_permission' ) );
			add_action( 'show_user_profile', array( $this, 'connect_profile_receiver' ) );
			add_action( 'wp_login', array( $this, 'redirect_to_admin_list_product_page' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'add_new_receiver' ) );
			add_action( 'virtuaria_receiver_report_before_content', array( $this, 'new_receiver_confirmation' ) );
			add_action( 'virtuaria_receiver_report_before_content', array( $this, 'add_new_receiver_form' ), 20 );
			add_action( 'pre_get_posts', array( $this, 'receiver_own_uploads' ) );
			add_action( 'after_update_receiver_profile', array( $this, 'toogle_visibility_products' ), 10, 2 );
			add_action( 'woocommerce_after_cart_item_name', array( $this, 'product_seller_name_cart' ) );
			add_filter( 'woocommerce_checkout_cart_item_quantity', array( $this, 'product_seller_name_checkout' ), 10, 2 );
			add_filter( 'woocommerce_order_item_name', array( $this, 'product_seller_name_mail' ), 10, 2 );
			add_action( 'init', array( $this, 'unvailable_product_status' ) );
			add_action( 'save_post_product', array( $this, 'prevent_publish_product' ) );
			add_action( 'init', array( $this, 'seller_product_page_rewrite' ) );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
			add_filter( 'template_include', array( $this, 'process_endpoint_request' ) );
			remove_filter( 'show_admin_bar', 'dokan_disable_admin_bar' );
			add_action( 'init', array( $this, 'remove_dokan_block_admin_dash' ) );
		} else {
			$this->remove_receiver_role();
		}
		add_action( 'unistall_virtuaria_pagbank_split', array( $this, 'remove_receiver_role' ) );
	}

	/**
	 * Create new role.
	 */
	public function create_receiver_role() {
		if ( ! get_role( 'virtuaria_receiver' ) ) {
			add_role(
				'virtuaria_receiver',
				__( 'Seller', 'virtuaria-pagbank-split' ),
				array(
					'read'                      => true,
					'edit_posts'                => true,
					'edit_products'             => true,
					'edit_product'              => true,
					'read_product'              => true,
					'delete_product'            => true,
					'delete_published_products' => true,
					'upload_files'              => true,
					'publish_products'          => true,
					'edit_published_products'   => true,
					'assign_product_terms'      => true,
				)
			);
		}
	}

	/**
	 * Remove receiver role.
	 */
	public function remove_receiver_role() {
		remove_role( 'virtuaria_receiver' );
	}

	/**
	 * Remove menus unecessary.
	 *
	 * @param array $menus current menu items.
	 */
	public function remove_menus( $menus ) {
		if ( $this->current_user_is_receiver() ) {
			foreach ( $menus as $index => $menu ) {
				if ( in_array( $menu[2], array( 'menu_conteudo', 'marketing', 'index.php' ), true ) ) {
					unset( $menus[ $index ] );
				}
			}
		}
		return $menus;
	}

	/**
	 * Remove menus unecessary.
	 */
	public function remove_defaults_menus() {
		if ( $this->current_user_is_receiver() ) {
			remove_menu_page( 'edit-comments.php' );
			remove_menu_page( 'index.php' );
			remove_menu_page( 'tools.php' );
			remove_menu_page( 'edit.php' );
			remove_menu_page( 'upload.php' );
			remove_menu_page( 'edit.php?post_type=shop_order' );
			remove_submenu_page( 'edit.php?post_type=product', 'product-reviews' );

			global $submenu;

			foreach ( $submenu['edit.php?post_type=product'] as $key => $menu ) {
				if ( false !== strpos( $menu[2], 'edit-tags.php?taxonomy=product_cat' )
					|| false !== strpos( $menu[2], 'edit-tags.php?taxonomy=product_tag' ) ) {
					unset( $submenu['edit.php?post_type=product'][ $key ] );
				}
			}
		}
	}

	/**
	 * Prevent unautorized access to pages.
	 */
	public function prevent_unauthorized_access() {
		global $pagenow;

		$allowed_pages = array(
			'profile.php',
			'edit.php',
			'post.php',
			'index.php',
			'post-new.php',
		);

		if ( $this->current_user_is_receiver()
			&& ! is_ajax()
			&& ( ! in_array( $pagenow, $allowed_pages, true )
			|| ( ( 'post-new.php' === $pagenow || 'edit.php' === $pagenow )
			&& ( ! isset( $_GET['post_type'] ) || 'product' !== $_GET['post_type'] ) ) )
			&& ( ! isset( $_GET['page'] ) || 'seller_performance' !== $_GET['page'] ) ) {
			wp_die( 'Acesso não autorizado! Em caso de dúvidas, entre em contato via ' . esc_html( get_option( 'admin_email' ) ) );
		}
	}

	/**
	 * News fields to receiver profile.
	 *
	 * @param wp_user $user the user.
	 */
	public function profile_receiver( $user ) {
		if ( ! $this->current_user_is_receiver() ) {
			require_once VIRTUARIA_PAGBANK_SPLIT_DIR . 'templates/setup-receiver-profile.php';
		}
	}

	/**
	 * Save profile.
	 *
	 * @param int $user_id the user id.
	 */
	public function save_profile_receiver( $user_id ) {
		if ( isset( $_POST['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {

			$receiver = get_user_meta(
				$user_id,
				'_virtuaria_receiver',
				true
			);

			$receiver = $receiver ? $receiver : array();
			if ( isset( $_POST['receiver_status'] ) ) {
				$receiver['status'] = sanitize_text_field(
					wp_unslash(
						$_POST['receiver_status']
					)
				);
			}

			if ( isset( $_POST['receiver_account'] ) ) {
				$receiver['account'] = sanitize_text_field(
					wp_unslash(
						$_POST['receiver_account']
					)
				);
			}

			if ( isset( $_POST['receiver_fee'] ) ) {
				$receiver['fee'] = sanitize_text_field(
					wp_unslash(
						$_POST['receiver_fee']
					)
				);
			}

			if ( isset( $_POST['receiver_level'] ) ) {
				$receiver['level'] = sanitize_text_field(
					wp_unslash(
						$_POST['receiver_level']
					)
				);
			}

			update_user_meta(
				$user_id,
				'_virtuaria_receiver',
				$receiver
			);

			do_action( 'after_update_receiver_profile', $user_id, $receiver );
		}
	}

	/**
	 * Bing current user to new product.
	 *
	 * @param int     $post_id product id.
	 * @param wp_post $post    current post.
	 * @param boolean $update  true if is updating.
	 */
	public function bind_product_and_receiver( $post_id, $post, $update ) {
		if ( is_admin()
			&& ! $update
			&& $this->current_user_is_receiver() ) {
			update_post_meta(
				$post_id,
				'_receiver_id',
				get_current_user_id()
			);
		}
	}

	/**
	 * Bing current user to new product in dokan enviroment.
	 *
	 * @param int $product_id product id.
	 */
	public function bind_dokan_product_and_receiver( $product_id ) {
		if ( $this->current_user_is_receiver() ) {
			update_post_meta(
				$product_id,
				'_receiver_id',
				get_current_user_id()
			);
		}
	}

	/**
	 * Display only product created by current receiver.
	 *
	 * @param WP_Query $query the query.
	 */
	public function display_receiver_products( $query ) {
		if ( is_admin()
			&& $query->is_main_query()
			&& $this->current_user_is_receiver()
			&& 'product' === $query->get( 'post_type' ) ) {
			$query->set( 'author', get_current_user_id() );
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
	 * Hide post count info.
	 *
	 * @param array $filters view filter.
	 */
	public function product_list_filters( $filters ) {
		if ( $this->current_user_is_receiver() ) {
			foreach ( $filters as $index => $filter ) {
				$filters[ $index ] = preg_replace(
					'/(<span.+)<\/span>/',
					'',
					$filter
				);
			}
		}
		return $filters;
	}

	/**
	 * Deactivates selling of products based on receiver status.
	 *
	 * @param bool       $is_purchasable Whether the product is purchasable.
	 * @param wc_product $product        The product object.
	 *
	 * @return bool The updated value of $is_purchasable.
	 */
	public function deactivate_sell_products( $is_purchasable, $product ) {
		$receiver_id = get_post_meta(
			$product->get_parent_id()
				? $product->get_parent_id()
				: $product->get_id(),
			'_receiver_id',
			true
		);
		if ( $receiver_id ) {
			$receiver_data = get_user_meta(
				$receiver_id,
				'_virtuaria_receiver',
				true
			);

			if ( ! $this->is_available( $receiver_data ) ) {
				$is_purchasable = false;
			}
		}

		return $is_purchasable;
	}

	/**
	 * Check if receiver is available.
	 *
	 * @param array $receiver receiver data.
	 * @return bool true if is available otherwise false
	 */
	private function is_available( $receiver ) {
		return ( isset( $receiver['status'] )
			&& 'active' === $receiver['status'] )
			&& ( 'production' !== $this->settings['environment']
			|| ( 'production' === $this->settings['environment']
				&& ( isset( $receiver['account'] )
				&& ! empty( $receiver['account'] ) ) ) );
	}

	/**
	 * Add receivers to node split.
	 *
	 * @param array    $split_receivers split receiver values.
	 * @param wc_order $order            order.
	 * @param int      $total_sell       total sell.
	 *
	 * @return array Array com os recebedores das cobranças.
	 */
	public function add_split_receivers( $split_receivers, $order, $total_sell ) {
		$split_receivers = array(
			'method'    => 'FIXED',
			'receivers' => array(),
		);

		$total_sell /= 100;

		$receivers = array();
		/**
		 * Get receivers info.
		 *
		 * @var WC_Order_Item_Product $item item from order.
		 */
		foreach ( $order->get_items() as $item ) {
			$product_id  = $item->get_product_id();
			$receiver_id = get_post_meta( $product_id, '_receiver_id', true );

			if ( ! $receiver_id ) {
				if ( ! isset( $receivers[ $this->settings['marketplace'] ] ) ) {
					$this->init_marketplace_receiver( $receivers, $order );
				}

				$receivers[ $this->settings['marketplace'] ]['products'][] = array(
					'product_id' => $product_id,
					'sku'        => $item->get_product()->get_sku(),
					'title'      => $item->get_product()->get_title(),
					'subtotal'   => $item->get_total(),
					'fee'        => 0,
					'quantity'   => $item->get_quantity(),
					'price'      => $item->get_product()->get_price(),
					'discount'   => $item->get_subtotal() - $item->get_total(),
				);

				continue;
			}

			$receiver = get_user_meta( $receiver_id, '_virtuaria_receiver', true );

			if ( ! $this->is_available( $receiver ) ) {
				continue;
			}

			$fee_percentage = 0;
			if ( isset( $receiver['fee'] )
				&& floatval( $receiver['fee'] ) > 0 ) {
				$fee_percentage = floatval( $receiver['fee'] );
			} else {
				$fee_percentage = floatval( $this->settings['main_fee'] );
			}

			if ( ! isset( $receivers[ $receiver_id ] ) ) {
				$account = $receiver['account'];

				if ( 'production' !== $this->settings['environment'] ) {
					$account = 'ACCO_C441E21F-2C34-4B2A-AA90-3308EAA6D480';
				}
				$receivers[ $receiver_id ] = array(
					'receiver_id' => $receiver_id,
					'received'    => 0,
					'fee'         => $fee_percentage,
					'products'    => array(),
					'total_sold'  => 0,
					'account_id'  => $account,
					'production'  => 'production' === $this->settings['environment'] ? 1 : 0,
				);
			}

			if ( ! isset( $receivers[ $this->settings['marketplace'] ] ) ) {
				$this->init_marketplace_receiver( $receivers, $order );
			}

			$fee_percentage  = apply_filters(
				'virtuaria_split_fee_percentage',
				$fee_percentage,
				$receiver_id,
				$product_id
			);
			$fee_percentage /= 100;

			if ( $fee_percentage > 0 ) {
				$fee_amount = number_format(
					$item->get_total() * $fee_percentage,
					2,
					'.',
					''
				);
				$item_total = $item->get_total() - $fee_amount;

				$receivers[ $receiver_id ]['total_sold'] += $item->get_total();
				$receivers[ $receiver_id ]['received']   += $item_total;

				$receivers[ $receiver_id ]['products'][]                   = array(
					'product_id' => $product_id,
					'sku'        => $item->get_product()->get_sku(),
					'title'      => $item->get_product()->get_title(),
					'subtotal'   => $item->get_total(),
					'fee'        => $fee_amount,
					'quantity'   => $item->get_quantity(),
					'price'      => $item->get_product()->get_price(),
					'discount'   => $item->get_subtotal() - $item->get_total(),
				);
				$receivers[ $this->settings['marketplace'] ]['products'][] = array(
					'product_id' => $product_id,
					'sku'        => $item->get_product()->get_sku(),
					'title'      => $item->get_product()->get_title(),
					'subtotal'   => $item->get_total(),
					'fee'        => $fee_amount,
					'quantity'   => $item->get_quantity(),
					'price'      => $item->get_product()->get_price(),
					'discount'   => $item->get_subtotal() - $item->get_total(),
				);
			} else {
				$receivers[ $receiver_id ]['received']  += $item->get_total();
				$receivers[ $receiver_id ]['products'][] = array(
					'product_id' => $product_id,
					'sku'        => $item->get_product()->get_sku(),
					'title'      => $item->get_product()->get_title(),
					'subtotal'   => $item->get_total(),
					'fee'        => 0,
					'quantity'   => $item->get_quantity(),
					'price'      => $item->get_product()->get_price(),
					'discount'   => $item->get_subtotal() - $item->get_total(),
				);
			}
		}

		if ( $receivers ) {
			$total_comssioned = 0;

			foreach ( $receivers as $account_id => $commission ) {
				if ( is_int( $account_id ) ) {
					$total_comssioned += $commission['received'];
				}
			}

			$receivers[ $this->settings['marketplace'] ]['received']   = $total_sell - $total_comssioned;
			$receivers[ $this->settings['marketplace'] ]['total_sold'] = $total_sell;

			$account_to_send = array();
			foreach ( $receivers as $commission ) {
				if ( ! isset( $account_to_send[ $commission['account_id'] ] ) ) {
					$split_receivers['receivers'][] = array(
						'account' => array(
							'id' => $commission['account_id'],
						),
						'amount'  => array(
							'value' => apply_filters(
								'virtuaria_split_commission_total',
								$this->convert_to_int( $commission['received'] ),
								$commission['account_id'],
								$commission,
								$order
							),
						),
					);

					$account_to_send[ $commission['account_id'] ] = count( $split_receivers['receivers'] ) - 1;
				} else {
					$split_receivers['receivers'][ $account_to_send[ $commission['account_id'] ] ]['amount']['value'] += apply_filters(
						'virtuaria_split_commission_total',
						$this->convert_to_int( $commission['received'] ),
						$commission['account_id'],
						$commission,
						$order
					);
				}
			}
		}

		do_action( 'virtuaria_split_receivers_info', $receivers, $order );

		if ( count( $receivers ) === 1 ) {
			return false;
		}

		return $split_receivers;
	}

	/**
	 * Converts a value to a int and does not round.
	 *
	 * @param mixed $value The value to be converted.
	 * @return int The converted float value.
	 */
	private function convert_to_int( $value ) {
		return explode(
			'.',
			strval( $value * 100 )
		)[0];
	}

	/**
	 * Initializes the marketplace receiver.
	 *
	 * @param array    $receivers A reference to the receiver array.
	 * @param wc_order $order     The order object.
	 * @return void
	 */
	private function init_marketplace_receiver( &$receivers, $order ) {
		$receivers[ $this->settings['marketplace'] ] = array(
			'receiver_id' => null,
			'received'    => 0,
			'fee'         => floatval( $this->settings['main_fee'] ),
			'products'    => array(),
			'total_sold'  => $order->get_total(),
			'account_id'  => $this->settings['marketplace'],
			'production'  => 'production' === $this->settings['environment'] ? 1 : 0,
		);
	}

	/**
	 * Adds the 'Vendedor' column to the $columns array for the admin view of the receiver product.
	 *
	 * @param array $columns An array of columns for the admin view.
	 * @return array The modified $columns array with the 'Vendedor' column added.
	 */
	public function receiver_product_admin_column( $columns ) {
		if ( ! $this->current_user_is_receiver() ) {
			$columns['product_owner'] = 'Seller';
		}
		return $columns;
	}

	/**
	 * Content from product receiver.
	 *
	 * @param string $column The column being displayed.
	 * @param int    $post_id The ID of the post being displayed.
	 * @return void
	 */
	public function receiver_product_admin_column_content( $column, $post_id ) {
		if ( 'product_owner' === $column ) {
			$receiver_id = get_post_meta( $post_id, '_receiver_id', true );
			if ( $receiver_id ) {
				$user = get_user_by( 'id', $receiver_id );
				if ( $user ) {
					echo esc_html( $user->display_name );
					return;
				}
			}
			echo '<span class="na">–</span>';
		}
	}

	/**
	 * Set the width of the 'product_owner' column.
	 */
	public function set_product_owner_column_width() {
		echo '<style>
			.wp-list-table .column-product_owner {
				width: 87px;
			}
		</style>';
	}

	/**
	 * Adds the product owner's identity to the product page if a seller identifier is set.
	 */
	public function add_product_owner_identity() {
		global $product;

		if ( isset( $this->settings['seller_identifier'] )
			&& ! empty( $this->settings['seller_identifier'] ) ) {
			$receiver_id = get_post_meta(
				$product->get_id(),
				'_receiver_id',
				true
			);

			if ( $receiver_id ) {
				$seller = get_user_by( 'id', $receiver_id );
				if ( $seller ) {
					$display_name = $seller->first_name
						? $seller->first_name . ' ' . $seller->last_name
						: $seller->display_name;
					$display_name = ucwords( mb_strtolower( $display_name ) );

					$seller_page  = home_url( 'seller/' . $seller->user_login );
					$display_name = sprintf(
						'<a class="seller-identifier" href="%s">%s</a>',
						esc_url( $seller_page ),
						$display_name
					);

					$identity_text = str_replace(
						'{NOMESELLER}',
						$display_name,
						$this->settings['seller_identifier']
					);

					printf(
						'<p class="product-owner">%s</p>',
						wp_kses_post( $identity_text )
					);
				}
			} else {
				$display_name = sprintf(
					'<a class="seller-identifier" href="%s">%s</a>',
					esc_url( wc_get_page_permalink( 'shop' ) ),
					get_bloginfo( 'name' )
				);

				$identity_text = str_replace(
					'{NOMESELLER}',
					$display_name,
					$this->settings['seller_identifier']
				);

				printf(
					'<p class="product-owner">%s</p>',
					wp_kses_post( $identity_text )
				);
			}

			echo '<style>.single-product .summary .product-owner {
				font-size: 1.3em;
				margin-bottom: 25px;
			}
			.product-owner .seller-identifier {
				font-weight: bold;
			}</style>';
		}
	}

	/**
	 * Adds the product owner's identity in the order.
	 *
	 * @param int                   $item_id The ID of the item.
	 * @param WC_Order_Item_Product $item The object of the item.
	 * @return void
	 */
	public function add_product_owner_identity_in_order( $item_id, $item ) {
		if ( $item instanceof WC_Order_Item_Product ) {
			$receiver_id = get_post_meta(
				$item->get_product_id(),
				'_receiver_id',
				true
			);

			if ( $receiver_id ) {
				$seller = get_user_by( 'id', $receiver_id );
				if ( $seller ) {
					printf(
						'<div class="product-owner" style="font-size:12px;color:gray;">Vendido por %s</div>',
						esc_html( $seller->display_name )
					);
				}
			}
		}
	}

	/**
	 * Adds a metabox for the receiver product.
	 *
	 * @return void
	 */
	public function receiver_product_metabox() {
		if ( ! $this->current_user_is_receiver() ) {
			add_meta_box(
				'virtuaria-receiver',
				'Seller',
				array( $this, 'content_product_owner_box' ),
				'product',
				'side'
			);
		}
	}
	/**
	 * Adiciona um box para definir o recebedor responsável pelo produto.
	 *
	 * @param WP_Post $post O objeto do post atual.
	 * @return void
	 */
	public function content_product_owner_box( $post ) {
		$receiver_id = get_post_meta( $post->ID, '_receiver_id', true );

		if ( empty( $receiver_id ) ) {
			$this->select_receivers();
			wp_nonce_field(
				'add_product_owner',
				'product_owner_nonce',
			);
		} else {
			$user = get_user_by( 'id', $receiver_id );
			if ( $user ) {
				printf(
					'<p>Vendido por <a href="%s">%s - %s</a>.</p>',
					esc_url( get_edit_user_link( $user->ID ) ),
					esc_html( $user->display_name ),
					esc_html( $user->user_email )
				);
			}
		}
	}

	/**
	 * Selects the receiver from the list of users with the 'virtuaria_receiver' role.
	 *
	 * @return void
	 */
	private function select_receivers() {
		$users = get_users( array( 'role' => 'virtuaria_receiver' ) );

		echo '<select name="virtuaria_receiver" id="virtuaria-receiver">';

		echo '<option value="">Selecione um usuário seller</option>';

		foreach ( $users as $user ) {
			$user_id    = $user->ID;
			$user_name  = $user->display_name;
			$user_email = $user->user_email;
			echo '<option value="'
			. esc_attr( $user_id )
			. '">' . esc_attr( $user_name . ' - ' . $user_email ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Saves the receiver ID for a product post.
	 *
	 * @param int $post_id The ID of the post.
	 */
	public function save_receiver_id( $post_id ) {
		if ( ! is_admin() || 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['virtuaria_receiver'] )
			&& isset( $_POST['product_owner_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['product_owner_nonce'] ) ), 'add_product_owner' )
			&& ! get_post_meta( $post_id, '_receiver_id', true ) ) {
			$receiver_id = sanitize_text_field(
				wp_unslash(
					$_POST['virtuaria_receiver']
				)
			);

			update_post_meta( $post_id, '_receiver_id', $receiver_id );

			$this->update_post_author( $post_id, $receiver_id );
		}
	}

	/**
	 * Updates the author of a post in the WordPress database.
	 *
	 * @param int $post_id The ID of the post to update.
	 * @param int $author_id The ID of the new author.
	 */
	private function update_post_author( $post_id, $author_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'posts';

		$data = array(
			'post_author' => $author_id,
		);

		$where = array(
			'ID' => $post_id,
		);

		$wpdb->update( $table_name, $data, $where );
	}

	/**
	 * Adds a receiver in the quick edit.
	 *
	 * @param string $column_name The name of the column.
	 * @param string $post_type The type of the post.
	 * @return void
	 */
	public function quick_edit_add_receiver( $column_name, $post_type ) {
		if ( 'product_owner' !== $column_name || 'product' !== $post_type ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-left">
			<div class="inline-edit-col">
				<label>
					<span class="title">Seller</span>
					<?php
					$this->select_receivers();
					wp_nonce_field(
						'add_product_owner',
						'product_owner_nonce',
					);
					?>
				</label>
				<small>Uma vez selecionado não é possível alterar o vendedor.</small>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Prevent quick edit seller.
	 *
	 * @return void
	 */
	public function prevent_update_seller_exists() {
		global $pagenow;

		if ( 'edit.php' === $pagenow
			&& isset( $_GET['post_type'] )
			&& 'product' === $_GET['post_type'] ) {
			?>
			<script>
				jQuery( function( $ ){
					const wp_inline_edit_function = inlineEditPost.edit;

					inlineEditPost.edit = function( post_id ) {

						wp_inline_edit_function.apply( this, arguments );

						if ( typeof( post_id ) == 'object' ) { // if it is object, get the ID number
							post_id = parseInt( this.getId( post_id ) );
						}
						// add rows to variables
						const edit_row = $( '#edit-' + post_id );
						const post_row = $( '#post-' + post_id )

						const recepient = $( '.column-product_owner', post_row ).text();

						if ( recepient != '–' ) {
							$( edit_row ).find( '#virtuaria-receiver' ).parent().parent().hide();
						} else {
							$( edit_row ).find( '#virtuaria-receiver' ).parent().parent().show();
						}
					}
				});
			</script>
			<?php
		}
	}

	/**
	 * Save account id from integration connection.
	 */
	public function save_account_id() {
		global $pagenow;

		if ( isset( $_GET['account_id'] )
			&& isset( $_GET['token'] ) ) {

			if ( isset( $_GET['page'] )
				&& 'virtuaria_pagseguro' === $_GET['page']
				&& ! $this->current_user_is_receiver() ) {
				$settings = get_option(
					'woocommerce_virt_pagseguro_settings'
				);

				$settings['marketplace'] = sanitize_text_field(
					wp_unslash(
						$_GET['account_id']
					)
				);
				update_option(
					'woocommerce_virt_pagseguro_settings',
					$settings
				);
			} elseif ( $this->current_user_is_receiver()
				&& 'profile.php' === $pagenow ) {
				$user_id  = get_current_user_id();
				$receiver = get_user_meta(
					$user_id,
					'_virtuaria_receiver',
					true
				);

				$receiver['account'] = sanitize_text_field(
					wp_unslash(
						$_GET['account_id']
					)
				);

				if ( isset( $this->settings['environment'] )
					&& 'sandbox' === $this->settings['environment'] ) {
					$receiver['token_sanbox'] = sanitize_text_field(
						wp_unslash(
							$_GET['token']
						)
					);
				} else {
					$receiver['token_production'] = sanitize_text_field(
						wp_unslash(
							$_GET['token']
						)
					);
				}

				update_user_meta(
					$user_id,
					'_virtuaria_receiver',
					$receiver
				);
			}
		} elseif ( $this->current_user_is_receiver()
			&& 'profile.php' === $pagenow
			&& isset( $_GET['section'] )
			&& 'virt_pagseguro' === $_GET['section']
			&& ( isset( $_GET['proccess'] )
			|| isset( $_GET['access_revoked'] ) ) ) {

			$user_id  = get_current_user_id();
			$receiver = get_user_meta(
				$user_id,
				'_virtuaria_receiver',
				true
			);

			if ( isset( $this->settings['environment'] )
				&& 'sandbox' === $this->settings['environment'] ) {
				unset( $receiver['token_sanbox'] );
			} else {
				unset( $receiver['token_production'] );
			}

			unset( $receiver['account'] );

			update_user_meta(
				$user_id,
				'_virtuaria_receiver',
				$receiver
			);
		}
	}

	/**
	 * Redirect after update receiver token.
	 */
	public function redirect_before_update_receiver_token() {
		$token_update = isset( $_GET['token'] )
			|| isset( $_GET['proccess'] )
			|| isset( $_GET['access_revoked'] );

		if ( isset( $_GET['page'] )
			&& $this->current_user_is_receiver()
			&& $token_update
			&& 'wc-settings' === $_GET['page'] ) {
			unset( $_GET['page'] );

			$query_args = array();
			if ( isset( $_GET['section'] ) ) {
				$query_args['section'] = sanitize_text_field(
					wp_unslash(
						$_GET['section']
					)
				);
			}

			if ( isset( $_GET['token'] ) ) {
				$query_args['token'] = sanitize_text_field(
					wp_unslash(
						$_GET['token']
					)
				);
			}

			if ( isset( $_GET['proccess'] ) ) {
				$query_args['proccess'] = sanitize_text_field(
					wp_unslash(
						$_GET['proccess']
					)
				);
			}

			if ( isset( $_GET['access_revoked'] ) ) {
				$query_args['access_revoked'] = sanitize_text_field(
					wp_unslash(
						$_GET['access_revoked']
					)
				);
			}

			if ( isset( $_GET['account_id'] ) ) {
				$query_args['account_id'] = sanitize_text_field(
					wp_unslash(
						$_GET['account_id']
					)
				);
			}

			if ( wp_safe_redirect(
				admin_url(
					'profile.php?' . http_build_query(
						$query_args
					)
				)
			) ) {
				exit;
			}
		}
	}

	/**
	 * Display option to connect profile with pagbank.
	 *
	 * @param WP_User $user The current user.
	 */
	public function connect_profile_receiver( $user ) {
		if ( $this->current_user_is_receiver() ) {
			$global_fee = $this->settings['main_fee'];

			require_once VIRTUARIA_PAGBANK_SPLIT_DIR . 'templates/setup-connection-receiver-profile.php';
		}
	}

	/**
	 * Dynamically adds permission for the current user to view the WooCommerce settings page.
	 */
	public function add_wocommerce_settings_permission() {
		$user = wp_get_current_user();
		if ( isset( $_GET['page'] )
			&& 'wc-settings' === $_GET['page']
			&& $this->current_user_is_receiver()
			&& ! $user->has_cap( 'manage_woocommerce' ) ) {
			$user->add_cap( 'manage_woocommerce' );
		} else {
			$user->remove_cap( 'manage_woocommerce' );
		}
	}

	/**
	 * Redirects users to the admin list product page after login.
	 *
	 * @param string  $user_login The username.
	 * @param wp_user $user       The user object.
	 */
	public function redirect_to_admin_list_product_page( $user_login, $user ) {
		$token_update = isset( $_REQUEST['redirect_to'] )
			&& false !== strpos(
				sanitize_text_field( wp_unslash( $_REQUEST['redirect_to'] ) ),
				'account_id'
			);

		if ( count( $user->roles ) === 1
			&& in_array( 'virtuaria_receiver', $user->roles, true )
			&& ! $token_update
			&& wp_safe_redirect( admin_url( 'edit.php?post_type=product' ) ) ) {
			exit;
		}
	}

	/**
	 * Redirect user to the access dashboard if they are a receiver.
	 */
	public function redirect_access_dashboard() {
		global $pagenow;

		if ( $this->current_user_is_receiver()
			&& 'index.php' === $pagenow
			&& wp_safe_redirect( admin_url( 'edit.php?post_type=product' ), 301 ) ) {
			exit;
		}
	}

	/**
	 * Add new receiver form.
	 */
	public function add_new_receiver_form() {
		require_once VIRTUARIA_PAGBANK_SPLIT_DIR . 'templates/new-receiver.php';
	}

	/**
	 * Adds a new receiver.
	 */
	public function add_new_receiver() {
		if ( isset( $_POST['_wpnonce'], $_GET['page'], $_POST['seller_fee'], $_POST['seller_name'], $_POST['seller_email'] )
			&& 'virtuaria-receivers' === $_GET['page']
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'add-seller' ) ) {
			$user_id = wp_create_user(
				sanitize_text_field( wp_unslash( $_POST['seller_name'] ) ),
				wp_generate_password(),
				sanitize_text_field( wp_unslash( $_POST['seller_email'] ) ),
			);

			if ( ! is_wp_error( $user_id ) ) {
				$user = new WP_User( $user_id );

				$user->remove_role( 'customer' );
				$user->add_role( 'virtuaria_receiver' );

				wp_new_user_notification( $user_id, null, 'both' );

				$receiver_info = array(
					'account' => '',
					'status'  => 'active',
					'fee'     => sanitize_text_field( wp_unslash( $_POST['seller_fee'] ) ),
				);

				update_user_meta( $user->ID, '_virtuaria_receiver', $receiver_info );
				set_transient(
					'virtuaria_new_receiver',
					true,
					10
				);
			} else {
				set_transient(
					'virtuaria_new_receiver',
					$user_id->get_error_message(),
					10
				);
			}
		}
	}

	/**
	 * Displays the confirmation message for adding a new receiver.
	 */
	public function new_receiver_confirmation() {
		$confirmed = get_transient( 'virtuaria_new_receiver' );
		if ( true === $confirmed ) {
			?>
			<div id="message" class="updated success">Usuário adicionado com sucesso!</div>
			<?php
		} elseif ( ! empty( $confirmed ) ) {
			?>
			<div id="message" class="updated error">
				<?php echo esc_html( $confirmed ); ?>
			</div>
			<?php
		}
	}

	/**
	 * Retrieves the receiver's own uploads from the WordPress query.
	 *
	 * @param WP_Query $wp_query The WordPress query object.
	 * @return void
	 */
	public function receiver_own_uploads( $wp_query ) {
		global $current_user, $pagenow;

		if ( ! is_a( $current_user, 'WP_User' )
			|| 'admin-ajax.php' !== $pagenow
			|| ! isset( $_REQUEST['action'] )
			|| 'query-attachments' !== $_REQUEST['action']
			|| ! $this->current_user_is_receiver() ) {
			return;
		}

		$wp_query->set( 'author', $current_user->ID );
	}

	/**
	 * Toggles the visibility of products based on user ID and receiver status.
	 *
	 * @param int   $user_id  The ID of the user.
	 * @param array $receiver The receiver's information.
	 */
	public function toogle_visibility_products( int $user_id, array $receiver ) {
		global $wpdb;

		if ( isset( $this->settings['hide_unpurchasable_products'] )
			&& 'yes' === $this->settings['hide_unpurchasable_products'] ) {
			if ( 'active' !== $receiver['status'] ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->posts} AS ps JOIN (
							SELECT p.ID FROM {$wpdb->posts} AS p INNER JOIN {$wpdb->postmeta} AS pm
							ON p.ID = pm.post_id AND meta_key = '_receiver_id' AND pm.meta_value = %d ) AS sub
							ON ps.ID = sub.ID
							SET post_status = 'virt_unavailable'
							WHERE ps.post_status = 'publish'",
						$user_id
					)
				);
			} else {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->posts} AS p INNER JOIN {$wpdb->postmeta} AS pm
						ON p.ID = pm.post_id
						SET post_status = 'publish'
						WHERE post_status = 'virt_unavailable'
						AND meta_key = '_receiver_id' AND pm.meta_value = %d",
						$user_id
					)
				);
			}
		}
	}

	/**
	 * Retrieves the name of the seller for the product in the cart item.
	 *
	 * @param array $cart_item The cart item data.
	 */
	public function product_seller_name_cart( $cart_item ) {
		echo wp_kses_post(
			$this->get_formatted_seller_product( $cart_item )
		);
	}

	/**
	 * Retrieves the name of the seller for the product in the checkout item.
	 *
	 * @param string $item_html The checkout item name html.
	 * @param array  $cart_item The cart item data.
	 */
	public function product_seller_name_checkout( $item_html, $cart_item ) {
		$item_html .= $this->get_formatted_seller_product( $cart_item );
		return $item_html;
	}

	/**
	 * Retrieves the name of the seller for the product in the mail item.
	 *
	 * @param string                $item_html The checkout item name html.
	 * @param WC_Order_Item_Product $item The cart item data.
	 */
	public function product_seller_name_mail( $item_html, $item ) {
		$item_html .= $this->get_formatted_seller_product( $item );
		return $item_html;
	}

	/**
	 * Get the formatted seller product for the given cart item.
	 *
	 * @param array $cart_item The cart item data.
	 * @return string The formatted seller product.
	 */
	private function get_formatted_seller_product( $cart_item ) {
		if ( $cart_item instanceof WC_Order_Item_Product ) {
			$seller_id = $cart_item->get_product()->get_meta( '_receiver_id' );
		} else {
			$seller_id = $cart_item['data']->get_meta( '_receiver_id' );
		}
		$seller_name = '';
		if ( $seller_id ) {
			$seller = get_user_by( 'id', $seller_id );
			if ( $seller ) {
				$display_name = $seller->first_name
					? $seller->first_name . ' ' . $seller->last_name
					: $seller->display_name;
				$display_name = ucwords( mb_strtolower( $display_name ) );

				if ( is_cart() ) {
					$seller_page = home_url( 'seller/' . $seller->user_login );

					$display_name = sprintf(
						'<a class="seller-identifier" href="%s" target="_blank">%s</a>',
						esc_url( $seller_page ),
						$display_name
					);
				}
			}
		} elseif ( is_cart() ) {
			$display_name = sprintf(
				'<a class="seller-identifier" href="%s">%s</a>',
				esc_url( wc_get_page_permalink( 'shop' ) ),
				get_bloginfo( 'name' )
			);
		} else {
			$display_name = get_bloginfo( 'name' );
		}

		$seller_name = sprintf(
			'<p class="product-seller" style="font-size:0.8em">Vendido por: <span class="seller-name">%s</span></p>',
			wp_kses_post( $display_name )
		);
		return $seller_name;
	}

	/**
	 * Register new product status 'unavailable'.
	 */
	public function unvailable_product_status() {
		$args = array(
			'label'                     => 'Seller Inativo',
			// translators: %s: seller count.
			'label_count'               => _n_noop(
				'Seller Inativo <span class="count">(%s)</span>',
				'Seller Inativo <span class="count">(%s)</span>',
				'virtuaria-pagbank-split'
			),
			'public'                    => is_admin() ? true : false,
			'exclude_from_search'       => false,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
		);

		register_post_status(
			'virt_unavailable',
			$args
		);
	}

	/**
	 * Prevents publishing product for the current user if they are an inactive receiver.
	 *
	 * @param int $post_id The ID of the product to be prevented from publishing.
	 */
	public function prevent_publish_product( $post_id ) {
		if ( $this->current_user_is_receiver() ) {
			$receiver = get_user_meta(
				get_current_user_id(),
				'_virtuaria_receiver',
				true
			);

			if ( ( isset( $this->settings['hide_unpurchasable_products'] )
				&& 'yes' === $this->settings['hide_unpurchasable_products'] )
				&& isset( $receiver['status'] )
				&& 'deactive' === $receiver['status'] ) {
				global $wpdb;
				$wpdb->update(
					$wpdb->posts,
					array( 'post_status' => 'virt_unavailable' ),
					array( 'ID' => $post_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Get products from seller.
	 *
	 * @return array
	 */
	private function get_seller_products() {
		if ( isset( $_GET['seller'] ) || get_query_var( 'seller' ) ) {
			$seller = get_user_by(
				'slug',
				isset( $_GET['seller'] )
					? sanitize_text_field( wp_unslash( $_GET['seller'] ) )
					: get_query_var( 'seller' )
			);

			if ( $seller ) {
				$receiver_data = get_user_meta(
					$seller->ID,
					'_virtuaria_receiver',
					true
				);

				if ( $receiver_data ) {
					global $wpdb;

					$product_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT p.ID FROM {$wpdb->posts} AS p INNER JOIN {$wpdb->postmeta} AS pm
							ON p.ID = pm.post_id AND p.post_type = 'product' AND p.post_status = 'publish'
							WHERE pm.meta_key = '_receiver_id' AND pm.meta_value = %d ",
							$seller->ID
						)
					);

					if ( $product_ids ) {
						return (array) $product_ids;
					}
				}
			}
		}
		return array();
	}

	/**
	 * Rewrite the seller product page URL.
	 */
	public function seller_product_page_rewrite() {
		add_rewrite_rule(
			'^seller/([^/]+)/?$',
			'index.php?seller=$matches[1]',
			'top'
		);
		flush_rewrite_rules();
	}

	/**
	 * Add query vars.
	 *
	 * @param array $query_vars the query vars.
	 * @return array
	 */
	public function add_query_vars( $query_vars ) {
		$query_vars[] = 'seller';
		return $query_vars;
	}

	/**
	 * Redirect access to confirm page.
	 *
	 * @param string $template the template path.
	 * @return string
	 */
	public function process_endpoint_request( $template ) {
		if ( false == get_query_var( 'seller' ) ) {
			return $template;
		}

		$GLOBALS['seller_products'] = $this->get_seller_products();
		return VIRTUARIA_PAGBANK_SPLIT_DIR . 'templates/seller-page.php';
	}

	/**
	 * Remove dokan block admin dash.
	 */
	public function remove_dokan_block_admin_dash() {
		if ( class_exists( '\WeDevs\Dokan\Core' ) ) {
			global $wp_filter;

			$filters = $wp_filter['admin_init'];

			if ( ! $wp_filter['admin_init'] instanceof WP_Hook ) {
				return;
			}

			foreach ( $filters->callbacks as $filter_id => $filter ) {
				$filter = array_values( $filter );

				foreach ( $filter as $key => $hook ) {
					if ( isset( $hook['function'] )
						&& is_array( $hook['function'] )
						&& isset( $hook['function'][0], $hook['function'][1] )
						&& 'block_admin_access' === $hook['function'][1]
						&& $hook['function'][0] instanceof \WeDevs\Dokan\Core ) {

						$filters->remove_filter(
							'admin_init',
							$hook['function'],
							10
						);
					}
				}
			}
		}
	}
}

new Virtuaria_Receivers();
