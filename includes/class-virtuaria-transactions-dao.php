<?php
/**
 * Handle transaction data report.
 *
 * @package virtuaria/pagseguro/split/reports
 */

defined( 'ABSPATH' ) || exit;

/**
 * This class is responsible for managing data receiver from reports.
 */
class Virtuaria_Transactions_DAO {
	/**
	 * Table version.
	 *
	 * @var float
	 */
	private const REPORT_DB_VERSION = 1.0;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		global $wpdb;
		add_action( 'install_virtuaria_pagbank_split', array( $this, 'initialize_database' ) );
	}

	/**
	 * Initializes the database for the plugin.
	 */
	public function initialize_database() {
		$installed_ver = get_option( 'virtuaria_pagbank_split_db' );

		if ( floatVal( $installed_ver ) !== self::REPORT_DB_VERSION ) {
			global $wpdb;

			$sql = "CREATE TABLE {$wpdb->prefix}virtuaria_pagbank_split (
				id INTEGER NOT NULL AUTO_INCREMENT,
				order_id BIGINT NOT NULL,
				seller_name VARCHAR(100) NOT NULL,
				seller_id INTEGER,
				received DOUBLE DEFAULT 0,
				total_sold DOUBLE DEFAULT 0,
				fee DOUBLE DEFAULT 0,
				itens TEXT,
				created_at DATETIME NOT NULL,
				active BOOLEAN DEFAULT 0,
				customer TEXT,
				account_id VARCHAR(100),
				payment_method VARCHAR(25),
				shipping_method VARCHAR(100),
				production BOOLEAN DEFAULT 0,
				coupons TEXT,
				cpf VARCHAR(15),
				PRIMARY KEY  (id)
			);";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			update_option( 'virtuaria_pagbank_split_db', self::REPORT_DB_VERSION );
		}
	}

	/**
	 * Insert new record.
	 *
	 * @param array $data data to item.
	 * @return wp_error|array error message, or data inserted.
	 */
	public function add_record( $data ) {
		global $wpdb;

		$format = array();
		foreach ( $data as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$format[] = '%f';
			} else {
				$format[] = '%s';
			}
		}

		$result = $wpdb->insert(
			"{$wpdb->prefix}virtuaria_pagbank_split",
			$data,
			$format
		);

		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error(
				"{$wpdb->prefix}virtuaria_pagbank_split",
				'Falha ao inserir o novo item.',
				array(
					'status'         => 500,
					'database_error' => $wpdb->last_error,
				)
			);
		} else {
			return $data;
		}
	}

	/**
	 * Retrieves all records from the virtuaria_pagbank_split table with a specific seller_id.
	 *
	 * @param int $seller_id The ID of the seller.
	 * @param int $enviroment Enviroment 1 to production 0 to sandbox.
	 * @return array An array of all the records with the specified seller_id.
	 */
	public function get_records_by_seller_id( $seller_id, $enviroment ) {
		global $wpdb;

		$info = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}virtuaria_pagbank_split WHERE seller_id = %d AND active = 1 AND production = %d ORDER BY created_at DESC",
				$seller_id,
				$enviroment
			),
			ARRAY_A
		);

		if ( $info ) {
			return $info;
		}
		return array();
	}

	/**
	 * Retrieves all records from the virtuaria_pagbank_split table.
	 *
	 * @param int $enviroment Enviroment 1 to production 0 to sandbox.
	 * @return array An array of all the records.
	 */
	public function get_all_records( $enviroment ) {
		global $wpdb;

		$info = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}virtuaria_pagbank_split WHERE active = 1 AND production = %d ORDER BY created_at DESC",
				$enviroment
			),
			ARRAY_A
		);

		if ( $info ) {
			return $info;
		}
		return array();
	}

	/**
	 * Remove records from the table based on the given order ID.
	 *
	 * @param int $order_id The order ID to filter the records.
	 * @param int $enviroment Enviroment 1 to production 0 to sandbox.
	 * @return void
	 */
	public function remove_records( $order_id, $enviroment ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}virtuaria_pagbank_split WHERE order_id = %d AND production = %d",
				$order_id,
				$enviroment
			)
		);
	}

	/**
	 * Updates the 'active' field to 1 in the specified record of the database table
	 * based on the provided order ID.
	 *
	 * @param int $order_id The ID of the order.
	 * @param int $enviroment Enviroment 1 to production 0 to sandbox.
	 */
	public function active_records( $order_id, $enviroment ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}virtuaria_pagbank_split SET active = 1 WHERE order_id = %d AND production = %d",
				$order_id,
				$enviroment
			)
		);
	}

	/**
	 * Retrieves the sales information for a specific seller in a given environment.
	 *
	 * @param int $seller_id  The ID of the seller.
	 * @param int $enviroment The enviroment to retrieve sales from.
	 * @global wpdb $wpdb The WordPress database object.
	 * @return array The sales information for the seller.
	 */
	public function get_sales( $seller_id, $enviroment ) {
		global $wpdb;

		if ( $seller_id ) {
			$info = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT SUM(`received`) AS received, SUM(`total_sold`) AS total, created_at
					FROM {$wpdb->prefix}virtuaria_pagbank_split AS s	
					INNER JOIN $wpdb->posts AS p
					ON p.ID = s.order_id
					WHERE active = 1 AND production = %d AND seller_id = %d
					AND p.post_status IN ('wc-processing','wc-completed')
					GROUP BY DATE(created_at)
					ORDER by created_at ASC
					LIMIT 10",
					$enviroment,
					$seller_id
				),
				ARRAY_A
			);
		} else {
			$info = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT SUM(`received`) AS received, SUM(`total_sold`) AS total, created_at
					FROM {$wpdb->prefix}virtuaria_pagbank_split AS s	
					INNER JOIN $wpdb->posts AS p
					ON p.ID = s.order_id
					WHERE active = 1 AND production = %d AND seller_id IS NULL
					AND p.post_status IN ('wc-processing','wc-completed')
					GROUP BY DATE(created_at)
					ORDER by created_at ASC
					LIMIT 10",
					$enviroment
				),
				ARRAY_A
			);
		}

		return $info;
	}

	/**
	 * Get sales from all seller in semester.
	 *
	 * @param int $enviroment Enviroment 1 to production 0 to sandbox.
	 */
	public function get_sales_by_semester( $enviroment ) {
		global $wpdb;

		$info = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SUM(total_sold) AS total_sold, u.display_name as seller
				FROM {$wpdb->prefix}virtuaria_pagbank_split AS s
				INNER JOIN wp_users as u ON s.seller_id = u.ID
				INNER JOIN {$wpdb->posts} AS p ON p.ID = s.order_id
				WHERE s.active = 1 AND s.production = %d AND s.seller_id IS NOT NULL
				AND p.post_status IN ('wc-processing','wc-on-hold','wc-completed')
				AND EXTRACT(YEAR FROM s.created_at) = YEAR(CURRENT_DATE)
				AND EXTRACT(MONTH FROM s.created_at) >= (CASE WHEN EXTRACT(MONTH FROM CURRENT_DATE) <= 6 THEN 1 ELSE 7 END)
				GROUP BY u.ID
				ORDER BY total_sold DESC",
				$enviroment
			),
			ARRAY_A
		);

		return $info;
	}

	/**
	 * Get count sales by seller and enviroment.
	 *
	 * @param int $enviroment Enviroment 1 to production 0 to sandbox.
	 * @param int $user_id The ID of the seller.
	 * @global wpdb $wpdb The WordPress database object.
	 * @return int The count of sales.
	 */
	public function get_count_sales_by_seller( $enviroment, $user_id ) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}virtuaria_pagbank_split WHERE `seller_id` = %d AND production = %d",
				$user_id,
				$enviroment
			)
		);

		return $result;
	}
}
