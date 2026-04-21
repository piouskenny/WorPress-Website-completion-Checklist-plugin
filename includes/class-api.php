<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QA_Checklist_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$namespace = 'qa-checklist/v1';

		register_rest_route( $namespace, '/projects', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_projects' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_project' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );

		register_rest_route( $namespace, '/projects/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_project' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_project' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );

		register_rest_route( $namespace, '/checklist/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );

		register_rest_route( $namespace, '/projects/(?P<id>\d+)/audit', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_project_audit' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );

		register_rest_route( $namespace, '/users', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_users' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );
	}

	public function check_permission() {
		return current_user_can( 'edit_posts' );
	}

	public function get_projects( $request ) {
		$filters = array(
			'status'           => $request->get_param( 'status' ),
			'assigned_user_id' => $request->get_param( 'user_id' ),
		);
		$projects = QA_Checklist_Database::get_projects( $filters );

		// Enrich with user names
		foreach ( $projects as $project ) {
			if ( $project->assigned_user_id ) {
				$user = get_userdata( $project->assigned_user_id );
				$project->user_name = $user ? $user->display_name : 'Unknown';
			} else {
				$project->user_name = 'Unassigned';
			}
		}

		return rest_ensure_response( $projects );
	}

	public function get_project( $request ) {
		global $wpdb;
		$id = $request['id'];
		$project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}qa_projects WHERE id = %d", $id ) );

		if ( ! $project ) {
			return new WP_Error( 'not_found', 'Project not found', array( 'status' => 404 ) );
		}

		$items = QA_Checklist_Database::get_project_items( $id );
		$project->items = $items;

		return rest_ensure_response( $project );
	}

	public function create_project( $request ) {
		global $wpdb;
		$params = $request->get_json_params();

		if ( empty( $params['name'] ) ) {
			return new WP_Error( 'missing_name', 'Project name is required', array( 'status' => 400 ) );
		}

		$wpdb->insert(
			"{$wpdb->prefix}qa_projects",
			array(
				'name'             => sanitize_text_field( $params['name'] ),
				'assigned_user_id' => ! empty( $params['assigned_user_id'] ) ? intval( $params['assigned_user_id'] ) : get_current_user_id(),
				'has_woocommerce'  => ! empty( $params['has_woocommerce'] ) ? 1 : 0,
				'has_forms'        => ! empty( $params['has_forms'] ) ? 1 : 0,
				'has_seo'          => ! empty( $params['has_seo'] ) ? 1 : 0,
			)
		);

		$project_id = $wpdb->insert_id;
		$this->initialize_checklist_items( $project_id, $params );

		return rest_ensure_response( array( 'id' => $project_id, 'message' => 'Project created' ) );
	}

	private function initialize_checklist_items( $project_id, $params ) {
		global $wpdb;
		$defaults = qa_checklist_get_default_items();
		$sections = array( 'core' );
		if ( ! empty( $params['has_woocommerce'] ) ) $sections[] = 'woocommerce';
		if ( ! empty( $params['has_forms'] ) ) $sections[] = 'forms';
		if ( ! empty( $params['has_seo'] ) ) $sections[] = 'seo';

		foreach ( $sections as $section ) {
			foreach ( $defaults[$section] as $label ) {
				$wpdb->insert(
					"{$wpdb->prefix}qa_checklist_items",
					array(
						'project_id' => $project_id,
						'section'    => $section,
						'label'      => $label,
						'status'     => 'pending',
					)
				);
			}
		}
	}

	public function update_project( $request ) {
		global $wpdb;
		$id = $request['id'];
		$params = $request->get_json_params();

		$data = array();
		if ( isset( $params['status'] ) ) $data['status'] = sanitize_text_field( $params['status'] );
		if ( isset( $params['is_archived'] ) ) $data['is_archived'] = $params['is_archived'] ? 1 : 0;
		if ( isset( $params['assigned_user_id'] ) ) $data['assigned_user_id'] = intval( $params['assigned_user_id'] );

		if ( empty( $data ) ) {
			return rest_ensure_response( array( 'message' => 'No changes' ) );
		}

		if ( isset( $data['status'] ) && $data['status'] === 'COMPLETED' ) {
			$project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}qa_projects WHERE id = %d", $id ) );
			
			if ( $project && $project->status !== 'COMPLETED' ) {
				$this->send_completion_email( $project );
			}
		}

		$wpdb->update( "{$wpdb->prefix}qa_projects", $data, array( 'id' => $id ) );

		return rest_ensure_response( array( 'message' => 'Project updated' ) );
	}

	private function send_completion_email( $project ) {
		$to = array( 'kehindeu13@gmail.com' );
		
		// Fetch extra emails from settings
		$extra_emails_str = get_option( 'qa_checklist_extra_emails' );
		if ( ! empty( $extra_emails_str ) ) {
			$extra_emails = array_map( 'trim', explode( ',', $extra_emails_str ) );
			foreach ( $extra_emails as $email ) {
				if ( is_email( $email ) ) {
					$to[] = $email;
				}
			}
		}

		$subject = 'Checklist Completed: ' . $project->name;
		$message = sprintf(
			"The QA checklist for project \"%s\" has been marked as completed.\n\nProject Name: %s\nStatus: Completed\n\nYou can view the project in the QA Checklist dashboard:\n%s",
			$project->name,
			$project->name,
			admin_url( 'admin.php?page=qa-checklist' )
		);
		
		// Set content type for HTML if needed, but mail is plain text currently
		$headers = array('Content-Type: text/plain; charset=UTF-8');
		
		$sent = wp_mail( $to, $subject, $message, $headers );
		if ( ! $sent ) {
			error_log( 'QA Checklist Error: Completion email failed to send to ' . implode( ', ', $to ) );
		}
	}

	public function update_item( $request ) {
		global $wpdb;
		$id = $request['id'];
		$params = $request->get_json_params();

		$data = array();
		if ( isset( $params['status'] ) ) $data['status'] = sanitize_text_field( $params['status'] );
		if ( isset( $params['comment'] ) ) $data['comment'] = sanitize_textarea_field( $params['comment'] );

		if ( empty( $data ) ) {
			return rest_ensure_response( array( 'message' => 'No changes' ) );
		}

		$wpdb->update( "{$wpdb->prefix}qa_checklist_items", $data, array( 'id' => $id ) );

		// Check if project is auto-completed after this item update
		$item = $wpdb->get_row( $wpdb->prepare( "SELECT project_id FROM {$wpdb->prefix}qa_checklist_items WHERE id = %d", $id ) );
		if ( $item ) {
			$this->check_project_completion( $item->project_id );
		}

		return rest_ensure_response( array( 'message' => 'Item updated' ) );
	}

	private function check_project_completion( $project_id ) {
		global $wpdb;
		
		$project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}qa_projects WHERE id = %d", $project_id ) );
		if ( ! $project || $project->status === 'COMPLETED' ) {
			return;
		}

		$items = QA_Checklist_Database::get_project_items( $project_id );
		if ( empty( $items ) ) {
			return;
		}

		$all_passed = true;
		foreach ( $items as $item ) {
			if ( $item->status !== 'pass' && $item->status !== 'passed' ) {
				$all_passed = false;
				break;
			}
		}

		if ( $all_passed ) {
			$wpdb->update( "{$wpdb->prefix}qa_projects", array( 'status' => 'COMPLETED' ), array( 'id' => $project_id ) );
			$this->send_completion_email( $project );
		}
	}

	public function get_users( $request ) {
		$users = get_users( array( 
			'role__in' => array( 'administrator', 'editor', 'author', 'contributor' ),
			'fields'   => array( 'ID', 'display_name' ) 
		) );
		return rest_ensure_response( $users );
	}

	public function run_project_audit( $request ) {
		$id = $request['id'];
		$automation = new QA_Checklist_Automation( $id );
		$results = $automation->run_audit();

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		// Check completion automatically after audit
		$this->check_project_completion( $id );

		return rest_ensure_response( array( 
			'success' => true, 
			'message' => 'Audit completed successfully',
			'results' => $results
		) );
	}
}
