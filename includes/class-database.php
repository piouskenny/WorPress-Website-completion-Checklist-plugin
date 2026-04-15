<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QA_Checklist_Database {

	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$projects_table = $wpdb->prefix . 'qa_projects';
		$items_table = $wpdb->prefix . 'qa_checklist_items';

		$sql_projects = "CREATE TABLE $projects_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			assigned_user_id bigint(20) DEFAULT NULL,
			status varchar(50) DEFAULT 'NOT_STARTED',
			has_woocommerce tinyint(1) DEFAULT 0,
			has_forms tinyint(1) DEFAULT 0,
			has_seo tinyint(1) DEFAULT 0,
			is_archived tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql_items = "CREATE TABLE $items_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			project_id bigint(20) NOT NULL,
			section varchar(50) NOT NULL,
			label varchar(255) NOT NULL,
			status varchar(20) DEFAULT 'pending',
			comment text,
			PRIMARY KEY  (id),
			KEY project_id (project_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_projects );
		dbDelta( $sql_items );
	}

	public static function get_projects( $filters = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qa_projects';
		
		$where = array( 'is_archived = 0' );
		if ( ! empty( $filters['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $filters['status'] );
		}
		if ( ! empty( $filters['assigned_user_id'] ) ) {
			$where[] = $wpdb->prepare( 'assigned_user_id = %d', $filters['assigned_user_id'] );
		}

		$where_str = implode( ' AND ', $where );
		return $wpdb->get_results( "SELECT * FROM $table WHERE $where_str ORDER BY updated_at DESC" );
	}

	public static function get_project_items( $project_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qa_checklist_items';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE project_id = %d", $project_id ) );
	}
}
