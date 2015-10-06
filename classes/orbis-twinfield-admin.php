<?php

/**
 * Title: Orbis Twinfield admin
 * Description:
 * Copyright: Copyright (c) 2005 - 2015
 * Company: Pronamic
 * @author Remco Tolsma
 * @version 1.0.0
 */
class Orbis_Twinfield_Admin {
	/**
	 * Plugin
	 *
	 * @var Orbis_InfiniteWP_Plugin
	 */
	private $plugin;

	//////////////////////////////////////////////////

	/**
	 * Constructs and initialize an Orbis core admin
	 *
	 * @param Orbis_Plugin $plugin
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Actions
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	//////////////////////////////////////////////////

	/**
	 * Admin initalize
	 */
	public function admin_init() {

	}

	//////////////////////////////////////////////////

	/**
	 * Admin menu
	 */
	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=orbis_subscription',
			__( 'Orbis Twinfield', 'orbis_twinfield' ),
			__( 'Twinfield', 'orbis_twinfield' ),
			'manage_options',
			'orbis_twinfield',
			array( $this, 'page_orbis_twinfield' )
		);
	}

	/**
	 * Page Orbis InfiniteWP
	 */
	public function page_orbis_twinfield() {
		include plugin_dir_path( $this->plugin->file ) . 'admin/page-orbis-twinfield.php';
	}

	//////////////////////////////////////////////////

	/**
	 * Get subscriptions
	 */
	public function get_subscriptions() {
		global $wpdb;
		global $orbis_subscriptions_plugin;

		$date = strtotime( filter_input( INPUT_GET, 'date', FILTER_SANITIZE_STRING ) );
		if ( false === $date ) {
			$date = time();
		}

		// Interval
		$interval = filter_input( INPUT_GET, 'interval', FILTER_SANITIZE_STRING );
		$interval = empty( $interval ) ? 'Y' : $interval;

		switch ( $interval ) {
			case 'M' :
				$day_function    = 'DAYOFMONTH';
				$join_condition  = $wpdb->prepare( '( YEAR( invoice.start_date ) = %d AND MONTH( invoice.start_date ) = %d )', date( 'Y', $date ), date( 'n', $date ) );
				$where_condition = $wpdb->prepare( 'subscription.activation_date <= %s', date( 'Y-m-d', $date ) );

				break;
			case 'Y' :
			default:
				$day_function    = 'DAYOFYEAR';
				$join_condition  = $wpdb->prepare( 'YEAR( invoice.start_date ) = %d', date( 'Y', $date ) );
				$where_condition = $wpdb->prepare( '
					(
						YEAR( subscription.activation_date ) <= %d
							AND 
						MONTH( subscription.activation_date ) < ( MONTH( NOW() ) + 2 )
					)',
					date( 'Y', $date )
				);
				break;
		}

		$interval_condition = $wpdb->prepare( 'product.interval = %s', $interval );

		$query = "
			SELECT
				company.id AS company_id,
				company.name AS company_name,
				company.post_id AS company_post_id,
				product.name AS subscription_name,
				product.price,
				product.twinfield_article,
				product.interval,
				product.post_id AS product_post_id,
				subscription.id,
				subscription.type_id,
				subscription.post_id,
				subscription.name,
				subscription.activation_date,
				DAYOFYEAR( subscription.activation_date ) AS activation_dayofyear,
				invoice.invoice_number,
				invoice.start_date,
				(
					invoice.id IS NULL
						AND
					$day_function( subscription.activation_date ) < $day_function( NOW() )
				) AS too_late
			FROM
				$wpdb->orbis_subscriptions AS subscription
					LEFT JOIN
				$wpdb->orbis_companies AS company
						ON subscription.company_id = company.id
					LEFT JOIN
				$wpdb->orbis_subscription_products AS product
						ON subscription.type_id = product.id
					LEFT JOIN
				$wpdb->orbis_subscriptions_invoices AS invoice
						ON
							subscription.id = invoice.subscription_id
								AND
							$join_condition
			WHERE
				cancel_date IS NULL
					AND
				invoice_number IS NULL
					AND
				product.auto_renew
					AND
				$interval_condition
					AND
				$where_condition
			ORDER BY
				DAYOFYEAR( subscription.activation_date )
			;"
		;

		$subscriptions = $wpdb->get_results( $query ); //unprepared SQL

		return $subscriptions;
	}
}