<?php
/**
 * File Upload / Artwork Management module.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_File_Manager {

	/**
	 * Allowed file types for artwork uploads.
	 *
	 * @var array
	 */
	private $allowed_types = array( 'pdf', 'ai', 'eps', 'psd', 'png', 'jpg', 'jpeg', 'tiff', 'tif' );

	/**
	 * Maximum file size in bytes (500MB).
	 *
	 * @var int
	 */
	private $max_file_size = 524288000;

	/**
	 * Initialize the module.
	 */
	public function init() {
		add_action( 'wp_ajax_pfp_upload_artwork', array( $this, 'ajax_upload_artwork' ) );
		add_action( 'wp_ajax_nopriv_pfp_upload_artwork', array( $this, 'ajax_upload_artwork' ) );
		add_action( 'wp_ajax_pfp_review_artwork', array( $this, 'ajax_review_artwork' ) );
		add_action( 'wp_ajax_pfp_artwork_comment', array( $this, 'ajax_add_comment' ) );

		// Add upload field to WooCommerce product pages.
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_upload_field' ) );

		// Save uploaded file reference in cart item.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_file_to_cart_item' ), 10, 3 );

		// Display file info in cart and order.
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_file_in_cart' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_file_to_order_item' ), 10, 4 );
	}

	/**
	 * Render the file upload field on product pages.
	 */
	public function render_upload_field() {
		global $product;

		if ( ! $product || 'yes' !== $product->get_meta( '_pfp_file_upload_required' ) ) {
			return;
		}

		$allowed_ext = implode( ', .', $this->allowed_types );
		$max_size_mb = round( $this->max_file_size / ( 1024 * 1024 ) );

		include PFP_PLUGIN_DIR . 'includes/frontend/views/file-upload.php';
	}

	/**
	 * AJAX handler: upload artwork file.
	 */
	public function ajax_upload_artwork() {
		check_ajax_referer( 'pfp_file_upload_nonce', 'nonce' );

		if ( ! isset( $_FILES['artwork_file'] ) ) {
			wp_send_json_error( array( 'message' => 'Aucun fichier sélectionné.' ) );
		}

		$file = $_FILES['artwork_file'];

		// Validate file type.
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $this->allowed_types, true ) ) {
			wp_send_json_error(
				array( 'message' => 'Format de fichier non autorisé. Formats acceptés: ' . implode( ', ', $this->allowed_types ) )
			);
		}

		// Validate file size.
		if ( $file['size'] > $this->max_file_size ) {
			wp_send_json_error(
				array( 'message' => 'Le fichier est trop volumineux. Taille maximale: ' . round( $this->max_file_size / ( 1024 * 1024 ) ) . ' Mo.' )
			);
		}

		// Create secure upload directory.
		$upload_dir = wp_upload_dir();
		$pfp_dir    = $upload_dir['basedir'] . '/printflow-pro/artwork/' . gmdate( 'Y/m' );

		if ( ! file_exists( $pfp_dir ) ) {
			wp_mkdir_p( $pfp_dir );
			// Add index.php to prevent directory listing.
			file_put_contents( $pfp_dir . '/index.php', '<?php // Silence is golden.' );
		}

		// Generate unique filename.
		$new_filename = wp_unique_filename( $pfp_dir, sanitize_file_name( $file['name'] ) );
		$destination  = $pfp_dir . '/' . $new_filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
			wp_send_json_error( array( 'message' => 'Erreur lors du téléchargement du fichier.' ) );
		}

		// Store in database.
		global $wpdb;
		$table = $wpdb->prefix . 'pfp_artwork_files';

		$customer_id = get_current_user_id();
		$order_id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		$wpdb->insert(
			$table,
			array(
				'order_id'          => $order_id,
				'order_item_id'     => 0,
				'customer_id'       => $customer_id,
				'file_path'         => str_replace( $upload_dir['basedir'], '', $destination ),
				'original_filename' => sanitize_file_name( $file['name'] ),
				'file_type'         => $ext,
				'file_size'         => $file['size'],
				'version'           => 1,
				'status'            => 'pending',
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		$artwork_id = $wpdb->insert_id;

		wp_send_json_success(
			array(
				'artwork_id' => $artwork_id,
				'filename'   => $file['name'],
				'message'    => 'Fichier téléchargé avec succès.',
			)
		);
	}

	/**
	 * AJAX handler: review artwork (approve/reject).
	 */
	public function ajax_review_artwork() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'pfp_manage_files' ) ) {
			wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
		}

		$artwork_id = isset( $_POST['artwork_id'] ) ? absint( $_POST['artwork_id'] ) : 0;
		$action     = isset( $_POST['review_action'] ) ? sanitize_text_field( wp_unslash( $_POST['review_action'] ) ) : '';

		if ( ! $artwork_id || ! in_array( $action, array( 'approved', 'rejected' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pfp_artwork_files';

		$wpdb->update(
			$table,
			array(
				'status'      => $action,
				'reviewed_by' => get_current_user_id(),
				'reviewed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $artwork_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		$status_label = 'approved' === $action ? 'approuvé' : 'rejeté';
		wp_send_json_success( array( 'message' => "Fichier {$status_label}." ) );
	}

	/**
	 * AJAX handler: add artwork comment.
	 */
	public function ajax_add_comment() {
		check_ajax_referer( 'pfp_admin_nonce', 'nonce' );

		$artwork_id = isset( $_POST['artwork_id'] ) ? absint( $_POST['artwork_id'] ) : 0;
		$comment    = isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';

		if ( ! $artwork_id || empty( $comment ) ) {
			wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pfp_artwork_comments',
			array(
				'artwork_id' => $artwork_id,
				'user_id'    => get_current_user_id(),
				'comment'    => $comment,
			),
			array( '%d', '%d', '%s' )
		);

		wp_send_json_success( array( 'message' => 'Commentaire ajouté.' ) );
	}

	/**
	 * Add uploaded file reference to cart item data.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id    Product ID.
	 * @param int   $variation_id  Variation ID.
	 * @return array
	 */
	public function add_file_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
		if ( isset( $_POST['pfp_artwork_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$cart_item_data['pfp_artwork_id'] = absint( $_POST['pfp_artwork_id'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return $cart_item_data;
	}

	/**
	 * Display file info in cart.
	 *
	 * @param array $item_data Cart item data for display.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_file_in_cart( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['pfp_artwork_id'] ) ) {
			global $wpdb;
			$filename = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT original_filename FROM {$wpdb->prefix}pfp_artwork_files WHERE id = %d",
					$cart_item['pfp_artwork_id']
				)
			);
			if ( $filename ) {
				$item_data[] = array(
					'key'   => __( 'Fichier téléchargé', 'printflow-pro' ),
					'value' => esc_html( $filename ),
				);
			}
		}
		return $item_data;
	}

	/**
	 * Save file reference to order item meta.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order object.
	 */
	public function save_file_to_order_item( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['pfp_artwork_id'] ) ) {
			$item->add_meta_data( '_pfp_artwork_id', $values['pfp_artwork_id'] );

			// Update artwork record with order info.
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'pfp_artwork_files',
				array(
					'order_id'      => $order->get_id(),
					'order_item_id' => $item->get_id(),
				),
				array( 'id' => $values['pfp_artwork_id'] ),
				array( '%d', '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Get all artwork files for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function get_order_artwork( $order_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}pfp_artwork_files WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get pending artwork files for review.
	 *
	 * @return array
	 */
	public function get_pending_artwork() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT af.*, u.display_name as customer_name
			FROM {$wpdb->prefix}pfp_artwork_files af
			LEFT JOIN {$wpdb->users} u ON af.customer_id = u.ID
			WHERE af.status = 'pending'
			ORDER BY af.created_at ASC",
			ARRAY_A
		);
	}
}
