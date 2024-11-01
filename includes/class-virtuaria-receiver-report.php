<?php
/**
 * Receiver reports.
 *
 * @package virtuaria/pagseguro/split
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Virtuaria_Receiver_Report' ) ) :
	/**
	 * Handle Receiver reports.
	 */
	class Virtuaria_Receiver_Report {
		/**
		 * Instance of the DAO.
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
			add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_styles_scripts' ), 20 );
			add_action( 'admin_menu', array( $this, 'receiver_report_submenu' ), 20 );
		}

		/**
		 * Generates a submenu page for the receiver report.
		 *
		 * @return void
		 */
		public function receiver_report_submenu() {
			add_submenu_page(
				'virtuaria_pagbank_split',
				__( 'Sellers', 'virtuaria-pagbank-split' ),
				__( 'Sellers', 'virtuaria-pagbank-split' ),
				'remove_users',
				'virtuaria-receivers',
				array( $this, 'receiver_report_page' )
			);
		}

		/**
		 * Displays the receiver report page.
		 *
		 * @return void
		 */
		public function receiver_report_page() {
			require_once VIRTUARIA_PAGBANK_SPLIT_DIR . 'templates/receiver-report.php';
		}

		/**
		 * Enqueue admin styles and scripts.
		 *
		 * @param String $hook hook page.
		 */
		public function add_admin_styles_scripts( $hook ) {
			$dir_path = plugin_dir_path( __FILE__ ) . '../admin/';
			$dir_url  = plugin_dir_url( __FILE__ ) . '../admin/';

			if ( 'virtuaria-split_page_virtuaria-receivers' === $hook ) {
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

				wp_enqueue_style(
					'receivers',
					$dir_url . 'css/receivers-report.css',
					array(),
					filemtime( $dir_path . 'css/receivers-report.css' )
				);

				wp_enqueue_script(
					'receivers-report',
					$dir_url . 'js/receivers-report.js',
					array( 'jquery' ),
					filemtime( $dir_path . 'js/receivers-report.js' ),
					true
				);

				wp_localize_script(
					'receivers-report',
					'data',
					$this->get_sellers()
				);

				wp_enqueue_script(
					'chartjs',
					$dir_url . 'chart.js/chart.min.js',
					array(),
					'4.4.1',
					true
				);

				wp_localize_script(
					'receivers-report',
					'sales',
					$this->format_sales(
						$this->dao->get_sales_by_semester(
							'production' === $this->settings['environment'] ? 1 : 0
						)
					)
				);
			}
		}

		/**
		 * Retrieves an array of sellers.
		 *
		 * This function gets an array of users with the 'virtuaria_receiver' role and retrieves the seller information for each user. The seller information includes the seller's ID, name, email, status, fee, account, and registration date. The function returns the array of sellers.
		 *
		 * @return array An array of sellers. Each seller is represented as an associative array with the following keys: 'id', 'name', 'email', 'status', 'fee', 'account', and 'date'.
		 */
		private function get_sellers() {
			$users = get_users( array( 'role' => 'virtuaria_receiver' ) );

			$sellers = array();
			if ( $users ) {
				foreach ( $users as $user ) {
					$seller_info = get_user_meta(
						$user->ID,
						'_virtuaria_receiver',
						true
					);

					if ( ! $seller_info ) {
						continue;
					}

					$date = DateTime::createFromFormat(
						'Y-m-d H:i:s',
						$user->user_registered
					);

					$connected = 'NÃO';
					if ( $seller_info['account'] ) {
						$connected = 'SIM <span class="tip">i</span><span class="account-id">( ' . $seller_info['account'] . ' )</span>';
					} elseif ( 'production' !== $this->settings['environment'] ) {
						$connected = 'SIM';
					}

					$sellers[] = array(
						'id'      => '#' . $user->ID,
						'name'    => sprintf(
							'<a href="%s">%s</a>',
							esc_url( get_edit_user_link( $user->ID ) ),
							esc_html( $user->display_name )
						),
						'mail'    => $user->user_email,
						'status'  => 'active' === $seller_info['status'] ? 'ATIVO' : 'INATIVO',
						'fee'     => $seller_info['fee'] ? $seller_info['fee'] . '%' : '-',
						'account' => $connected,
						'date'    => $date->format( 'd/m/Y H:i:s' ),
					);
				}
			}

			return $sellers;
		}

		/**
		 * Format sales to report.
		 *
		 * @param array $sales the sales.
		 * @return array
		 */
		private function format_sales( $sales ) {
			$formatted = array();
			if ( $sales ) {
				foreach ( $sales as $sales ) {
					$formatted[ $sales['seller'] ] = $sales['total_sold'];
				}
			}
			return $formatted;
		}
	}

	new Virtuaria_Receiver_Report();

endif;
