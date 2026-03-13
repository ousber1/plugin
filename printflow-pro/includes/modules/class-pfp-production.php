<?php
/**
 * Production Workflow Management module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Production {

	/**
	 * Production statuses with French labels.
	 *
	 * @var array
	 */
	private $statuses = array(
		'nouveau'              => 'Nouveau',
		'attente_validation'   => 'En attente de validation',
		'fichier_recu'         => 'Fichier reçu',
		'en_cours_design'      => 'En cours de design',
		'en_cours_impression'  => 'En cours d\'impression',
		'en_finition'          => 'En finition',
		'pret_livraison'       => 'Prêt pour livraison',
		'livre'                => 'Livré',
		'annule'               => 'Annulé',
	);

	/**
	 * Initialize the module.
	 */
	public function init() {
		add_action( 'wp_ajax_pfp_update_job_status', array( $this, 'ajax_update_status' ) );
		add_action( 'wp_ajax_pfp_assign_job', array( $this, 'ajax_assign_job' ) );
		add_action( 'wp_ajax_pfp_get_production_board', array( $this, 'ajax_get_board' ) );
		add_action( 'wp_ajax_pfp_update_checklist', array( $this, 'ajax_update_checklist' ) );
		add_action( 'wp_ajax_pfp_add_job_note', array( $this, 'ajax_add_note' ) );
	}

	/**
	 * Get all production jobs with optional filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_jobs( $args = array() ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND j.status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['assigned_to'] ) ) {
			$where   .= ' AND j.assigned_to = %d';
			$params[] = $args['assigned_to'];
		}

		if ( ! empty( $args['priority'] ) ) {
			$where   .= ' AND j.priority = %s';
			$params[] = $args['priority'];
		}

		$limit  = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT j.*, u.display_name as assigned_name
				FROM {$prefix}pfp_production_jobs j
				LEFT JOIN {$wpdb->users} u ON j.assigned_to = u.ID
				WHERE {$where}
				ORDER BY FIELD(j.priority, 'urgent', 'high', 'normal', 'low'), j.created_at ASC
				LIMIT %d OFFSET %d";

		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Get a single production job with details.
	 *
	 * @param int $job_id Job ID.
	 * @return array|null
	 */
	public function get_job( $job_id ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT j.*, u.display_name as assigned_name
				FROM {$prefix}pfp_production_jobs j
				LEFT JOIN {$wpdb->users} u ON j.assigned_to = u.ID
				WHERE j.id = %d",
				$job_id
			),
			ARRAY_A
		);

		if ( $job ) {
			// Get checklist.
			$job['checklist'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$prefix}pfp_production_checklists WHERE job_id = %d ORDER BY id",
					$job_id
				),
				ARRAY_A
			);

			// Get logs.
			$job['logs'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT l.*, u.display_name as user_name
					FROM {$prefix}pfp_production_logs l
					LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
					WHERE l.job_id = %d
					ORDER BY l.created_at DESC",
					$job_id
				),
				ARRAY_A
			);

			// Get order info.
			if ( $job['order_id'] ) {
				$order = wc_get_order( $job['order_id'] );
				if ( $order ) {
					$job['order_number']  = $order->get_order_number();
					$job['customer_name'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
				}
			}
		}

		return $job;
	}

	/**
	 * Update job status.
	 *
	 * @param int    $job_id     Job ID.
	 * @param string $new_status New status.
	 * @param string $notes      Notes.
	 * @return bool
	 */
	public function update_job_status( $job_id, $new_status, $notes = '' ) {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$job = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$prefix}pfp_production_jobs WHERE id = %d", $job_id ),
			ARRAY_A
		);

		if ( ! $job ) {
			return false;
		}

		$old_status = $job['status'];
		$update_data = array( 'status' => $new_status );

		// Set timestamps.
		if ( 'en_cours_impression' === $new_status && empty( $job['started_at'] ) ) {
			$update_data['started_at'] = current_time( 'mysql' );
		}
		if ( in_array( $new_status, array( 'pret_livraison', 'livre' ), true ) && empty( $job['completed_at'] ) ) {
			$update_data['completed_at'] = current_time( 'mysql' );
		}

		$updated = $wpdb->update(
			"{$prefix}pfp_production_jobs",
			$update_data,
			array( 'id' => $job_id )
		);

		if ( $updated !== false ) {
			// Log the status change.
			$wpdb->insert(
				"{$prefix}pfp_production_logs",
				array(
					'job_id'      => $job_id,
					'from_status' => $old_status,
					'to_status'   => $new_status,
					'user_id'     => get_current_user_id(),
					'notes'       => $notes,
				)
			);

			// Auto-deduct materials when printing starts.
			if ( 'en_cours_impression' === $new_status ) {
				do_action( 'pfp_production_started', $job_id, $job );
			}

			// Sync order status for delivery-ready.
			if ( 'pret_livraison' === $new_status && $job['order_id'] ) {
				$order = wc_get_order( $job['order_id'] );
				if ( $order ) {
					$order->update_status( 'pfp-ready-delivery', __( 'Prêt pour livraison', 'printflow-pro' ) );
				}
			}
		}

		return $updated !== false;
	}

	/**
	 * Assign a job to a staff member.
	 *
	 * @param int    $job_id  Job ID.
	 * @param int    $user_id User ID.
	 * @param string $machine Machine/equipment.
	 * @return bool
	 */
	public function assign_job( $job_id, $user_id, $machine = '' ) {
		global $wpdb;

		$data = array( 'assigned_to' => $user_id );
		if ( $machine ) {
			$data['machine'] = $machine;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'pfp_production_jobs',
			$data,
			array( 'id' => $job_id )
		);

		if ( $updated !== false ) {
			$user = get_userdata( $user_id );
			$wpdb->insert(
				$wpdb->prefix . 'pfp_production_logs',
				array(
					'job_id'      => $job_id,
					'from_status' => '',
					'to_status'   => 'assigned',
					'user_id'     => get_current_user_id(),
					'notes'       => sprintf( 'Assigné à %s', $user ? $user->display_name : $user_id ),
				)
			);
		}

		return $updated !== false;
	}

	/**
	 * Get jobs organized by status for kanban board.
	 *
	 * @return array
	 */
	public function get_kanban_board() {
		$board = array();
		foreach ( $this->statuses as $status_key => $status_label ) {
			if ( 'annule' === $status_key ) {
				continue;
			}
			$board[ $status_key ] = array(
				'label' => $status_label,
				'jobs'  => $this->get_jobs( array( 'status' => $status_key, 'limit' => 20 ) ),
			);
		}
		return $board;
	}

	/**
	 * Create default checklist items for a job.
	 *
	 * @param int $job_id Job ID.
	 */
	public function create_default_checklist( $job_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_production_checklists';

		$items = array(
			'Vérification du fichier client',
			'Validation des couleurs',
			'Réglage machine',
			'Impression test (épreuve)',
			'Impression finale',
			'Contrôle qualité',
			'Découpe / Finition',
			'Emballage',
		);

		foreach ( $items as $item ) {
			$wpdb->insert(
				$table,
				array(
					'job_id'    => $job_id,
					'item_text' => $item,
				)
			);
		}
	}

	/**
	 * AJAX handler: update job status.
	 */
	public function ajax_update_status() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_production' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$job_id     = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		if ( ! $job_id || ! isset( $this->statuses[ $new_status ] ) ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		$result = $this->update_job_status( $job_id, $new_status, $notes );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Statut mis à jour.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Erreur lors de la mise à jour.' ) );
		}
	}

	/**
	 * AJAX handler: assign job.
	 */
	public function ajax_assign_job() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_production' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$job_id  = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$machine = isset( $_POST['machine'] ) ? sanitize_text_field( wp_unslash( $_POST['machine'] ) ) : '';

		if ( ! $job_id || ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		$result = $this->assign_job( $job_id, $user_id, $machine );

		wp_send_json_success( array( 'message' => 'Travail assigné.' ) );
	}

	/**
	 * AJAX handler: get kanban board data.
	 */
	public function ajax_get_board() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_production' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		wp_send_json_success( $this->get_kanban_board() );
	}

	/**
	 * AJAX handler: update checklist item.
	 */
	public function ajax_update_checklist() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_production' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$item_id    = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$is_checked = ! empty( $_POST['is_checked'] );

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => 'Paramètre invalide.' ) );
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'pfp_production_checklists',
			array(
				'is_checked' => $is_checked ? 1 : 0,
				'checked_by' => get_current_user_id(),
				'checked_at' => $is_checked ? current_time( 'mysql' ) : null,
			),
			array( 'id' => $item_id )
		);

		wp_send_json_success( array( 'message' => 'Liste de contrôle mise à jour.' ) );
	}

	/**
	 * AJAX handler: add job note.
	 */
	public function ajax_add_note() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		$notes  = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		if ( ! $job_id || empty( $notes ) ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_production_logs',
			array(
				'job_id'      => $job_id,
				'from_status' => '',
				'to_status'   => 'note',
				'user_id'     => get_current_user_id(),
				'notes'       => $notes,
			)
		);

		wp_send_json_success( array( 'message' => 'Note ajoutée.' ) );
	}

	/**
	 * Get available statuses.
	 *
	 * @return array
	 */
	public function get_statuses() {
		return $this->statuses;
	}
}
