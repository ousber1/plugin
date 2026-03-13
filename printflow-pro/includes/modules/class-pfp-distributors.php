<?php
/**
 * Distributor / Reseller Management module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Distributors {

	public function init() {
		add_action( 'wp_ajax_pfp_save_distributor', array( $this, 'ajax_save_distributor' ) );
		add_action( 'wp_ajax_pfp_delete_distributor', array( $this, 'ajax_delete_distributor' ) );
	}

	public function get_distributors( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_distributors';
		$where = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC";
		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public function get_distributor( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pfp_distributors WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	public function save_distributor( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_distributors';

		$db_data = array(
			'name'            => $data['name'],
			'company'         => $data['company'] ?? '',
			'phone'           => $data['phone'] ?? '',
			'email'           => $data['email'] ?? '',
			'city'            => $data['city'] ?? '',
			'address'         => $data['address'] ?? '',
			'territory'       => $data['territory'] ?? '',
			'commission_rate' => $data['commission_rate'] ?? 0,
			'status'          => $data['status'] ?? 'active',
		);

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $db_data, array( 'id' => $data['id'] ) );
			return $data['id'];
		}
		$wpdb->insert( $table, $db_data );
		return $wpdb->insert_id;
	}

	public function ajax_save_distributor() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_distributors' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$data = array(
			'id'              => absint( $_POST['id'] ?? 0 ),
			'name'            => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'company'         => sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) ),
			'phone'           => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'email'           => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'city'            => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			'address'         => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
			'territory'       => sanitize_text_field( wp_unslash( $_POST['territory'] ?? '' ) ),
			'commission_rate' => floatval( $_POST['commission_rate'] ?? 0 ),
			'status'          => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
		);

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( array( 'message' => 'Le nom est obligatoire.' ) );
		}

		$id = $this->save_distributor( $data );
		wp_send_json_success( array( 'id' => $id, 'message' => 'Distributeur enregistré.' ) );
	}

	public function ajax_delete_distributor() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'pfp_manage_distributors' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'ID invalide.' ) );
		}

		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'pfp_distributors',
			array( 'status' => 'inactive' ),
			array( 'id' => $id )
		);
		wp_send_json_success( array( 'message' => 'Distributeur désactivé.' ) );
	}
}
