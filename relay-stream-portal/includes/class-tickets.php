<?php
/**
 * Private support tickets with validated, privately stored attachments.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tickets.
 */
class RSP_Tickets {

	const STATUSES = array( 'open', 'awaiting_customer', 'awaiting_staff', 'resolved', 'closed' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_rsp_attachment', array( __CLASS__, 'download' ) );
	}

	/**
	 * Table.
	 *
	 * @param string $n Name.
	 * @return string
	 */
	private static function t( $n = 'tickets' ) {
		return RSP_Installer::table( $n );
	}

	/**
	 * Status label.
	 *
	 * @param string $s Status.
	 * @return string
	 */
	public static function status_label( $s ) {
		$l = array(
			'open'              => __( 'Open', 'relay-stream-portal' ),
			'awaiting_customer' => __( 'Awaiting your reply', 'relay-stream-portal' ),
			'awaiting_staff'    => __( 'Awaiting support', 'relay-stream-portal' ),
			'resolved'          => __( 'Resolved', 'relay-stream-portal' ),
			'closed'            => __( 'Closed', 'relay-stream-portal' ),
		);
		return isset( $l[ $s ] ) ? $l[ $s ] : $s;
	}

	/**
	 * Private storage directory (outside web access where the server honours .htaccess / web.config;
	 * files are stored with random names and no executable extension in all cases).
	 *
	 * @return string
	 */
	public static function private_dir() {
		$base = defined( 'RSP_PRIVATE_DIR' ) ? RSP_PRIVATE_DIR : trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'rsp-private';
		return untrailingslashit( apply_filters( 'rsp_private_dir', $base ) );
	}

	/**
	 * Create dir with deny rules.
	 */
	public static function ensure_private_dir() {
		$dir = self::private_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$files = array(
			'.htaccess'  => "Require all denied\nDeny from all\n",
			'index.php'  => "<?php // Silence.\n",
			'web.config' => '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>',
		);
		foreach ( $files as $name => $content ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				file_put_contents( $dir . '/' . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
	}

	/**
	 * Create a ticket.
	 *
	 * @param int    $user_id User.
	 * @param array  $data subject, category, body, order_id, account_uuid.
	 * @param array  $files $_FILES entry (normalized list).
	 * @return int|WP_Error Ticket id.
	 */
	public static function create( $user_id, array $data, array $files = array() ) {
		global $wpdb;
		$subject = substr( sanitize_text_field( isset( $data['subject'] ) ? $data['subject'] : '' ), 0, 190 );
		$body    = sanitize_textarea_field( isset( $data['body'] ) ? $data['body'] : '' );
		$cats    = RSP_Settings::support_categories();
		$cat     = isset( $data['category'] ) && isset( $cats[ $data['category'] ] ) ? $data['category'] : key( $cats );
		if ( strlen( $subject ) < 3 || strlen( $body ) < 5 ) {
			return new WP_Error( 'rsp_ticket', __( 'Please add a subject and a message.', 'relay-stream-portal' ) );
		}
		$order_id = 0;
		if ( ! empty( $data['order_id'] ) && RSP_Plugin::has_woocommerce() ) {
			$order = wc_get_order( (int) $data['order_id'] );
			if ( $order && (int) $order->get_customer_id() === (int) $user_id ) {
				$order_id = $order->get_id();
			}
		}
		$account_id = 0;
		if ( ! empty( $data['account_uuid'] ) ) {
			$acc        = RSP_Accounts::get_owned( (string) $data['account_uuid'], $user_id );
			$account_id = $acc ? (int) $acc->id : 0;
		}
		$now = RSP_Installer::now();
		$wpdb->insert(
			self::t(),
			array(
				'user_id'    => $user_id,
				'subject'    => $subject,
				'category'   => $cat,
				'status'     => 'awaiting_staff',
				'order_id'   => $order_id ? $order_id : null,
				'account_id' => $account_id ? $account_id : null,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		$id = (int) $wpdb->insert_id;
		$r  = self::reply( $id, $user_id, $body, false, $files );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		do_action( 'rsp_ticket_created', $id );
		return $id;
	}

	/**
	 * Get ticket.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Ticket visible to user? Staff can see all.
	 *
	 * @param object $ticket Ticket.
	 * @param int    $user_id User.
	 * @return bool
	 */
	public static function can_view( $ticket, $user_id ) {
		return $ticket && ( (int) $ticket->user_id === (int) $user_id || user_can( $user_id, 'rsp_manage_support' ) );
	}

	/**
	 * Tickets for a user.
	 *
	 * @param int $user_id User.
	 * @param int $limit Limit.
	 * @return object[]
	 */
	public static function for_user( $user_id, $limit = 50 ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d', $user_id, $limit ) ); // phpcs:ignore
	}

	/**
	 * Messages (internal notes only for staff).
	 *
	 * @param int  $ticket_id Ticket.
	 * @param bool $include_internal Include notes.
	 * @return object[]
	 */
	public static function messages( $ticket_id, $include_internal = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::t( 'ticket_messages' ) . ' WHERE ticket_id = %d' . ( $include_internal ? '' : ' AND is_internal = 0' ) . ' ORDER BY id ASC';
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $ticket_id ) ); // phpcs:ignore
	}

	/**
	 * Attachments for a message.
	 *
	 * @param int $message_id Message.
	 * @return object[]
	 */
	public static function attachments( $message_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, original_name, size FROM ' . self::t( 'attachments' ) . ' WHERE message_id = %d', $message_id ) ); // phpcs:ignore
	}

	/**
	 * Add a reply.
	 *
	 * @param int    $ticket_id Ticket.
	 * @param int    $author_id Author.
	 * @param string $body Body.
	 * @param bool   $internal Internal note (staff only).
	 * @param array  $files Uploaded files.
	 * @return int|WP_Error Message id.
	 */
	public static function reply( $ticket_id, $author_id, $body, $internal = false, array $files = array() ) {
		global $wpdb;
		$ticket = self::get( $ticket_id );
		if ( ! self::can_view( $ticket, $author_id ) ) {
			return new WP_Error( 'rsp_forbidden', __( 'Ticket not found.', 'relay-stream-portal' ) );
		}
		$is_staff = user_can( $author_id, 'rsp_manage_support' ) && (int) $ticket->user_id !== (int) $author_id;
		$internal = $internal && $is_staff;
		$body     = trim( sanitize_textarea_field( $body ) );
		if ( '' === $body ) {
			return new WP_Error( 'rsp_empty', __( 'Message cannot be empty.', 'relay-stream-portal' ) );
		}
		$valid = self::validate_files( $files );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$wpdb->insert( self::t( 'ticket_messages' ), array( 'ticket_id' => $ticket_id, 'author_id' => $author_id, 'body' => $body, 'is_internal' => $internal ? 1 : 0, 'created_at' => RSP_Installer::now() ) );
		$mid = (int) $wpdb->insert_id;
		foreach ( $valid as $f ) {
			self::store_file( $ticket_id, $mid, $author_id, $f );
		}
		if ( ! $internal ) {
			$status = $is_staff ? 'awaiting_customer' : 'awaiting_staff';
			$wpdb->update( self::t(), array( 'status' => $status, 'updated_at' => RSP_Installer::now() ), array( 'id' => $ticket_id ) );
			if ( $is_staff ) {
				RSP_Notifications::notify(
					(int) $ticket->user_id,
					'ticket_reply',
					/* translators: %d ticket id */
					sprintf( __( 'New reply on ticket #%d', 'relay-stream-portal' ), $ticket_id ),
					$ticket->subject,
					RSP_Settings::page_url( 'support', array( 'ticket' => $ticket_id ) ),
					'ticket_reply:' . $mid,
					true,
					array( 'ticket' => $ticket_id, 'subject' => $ticket->subject )
				);
			}
		}
		return $mid;
	}

	/**
	 * Normalize and validate $_FILES['attachments'].
	 *
	 * @param array $files Files array (name[], tmp_name[], ...).
	 * @return array|WP_Error
	 */
	public static function validate_files( array $files ) {
		if ( empty( $files['name'] ) ) {
			return array();
		}
		$names   = (array) $files['name'];
		$max     = max( 1, (int) RSP_Settings::get( 'attach_max_mb', 5 ) ) * MB_IN_BYTES;
		$allowed = array_filter( array_map( 'trim', explode( ',', strtolower( (string) RSP_Settings::get( 'attach_types' ) ) ) ) );
		$out     = array();
		foreach ( $names as $i => $name ) {
			if ( '' === $name ) {
				continue;
			}
			if ( count( $out ) >= 3 ) {
				return new WP_Error( 'rsp_files', __( 'Attach up to 3 files.', 'relay-stream-portal' ) );
			}
			$tmp  = ( (array) $files['tmp_name'] )[ $i ];
			$err  = ( (array) $files['error'] )[ $i ];
			$size = (int) ( (array) $files['size'] )[ $i ];
			if ( UPLOAD_ERR_OK !== (int) $err || ! is_uploaded_file( $tmp ) && ! defined( 'RSP_TESTING' ) ) {
				return new WP_Error( 'rsp_files', __( 'Upload failed.', 'relay-stream-portal' ) );
			}
			if ( $size > $max ) {
				return new WP_Error( 'rsp_files', __( 'A file is too large.', 'relay-stream-portal' ) );
			}
			$check = wp_check_filetype_and_ext( $tmp, $name );
			$ext   = $check['ext'] ? strtolower( $check['ext'] ) : '';
			if ( ! $ext || ! $check['type'] || ! in_array( $ext, $allowed, true ) ) {
				return new WP_Error( 'rsp_files', __( 'That file type is not allowed.', 'relay-stream-portal' ) );
			}
			$out[] = array( 'tmp' => $tmp, 'name' => sanitize_file_name( $name ), 'mime' => $check['type'], 'size' => $size );
		}
		return $out;
	}

	/**
	 * Move file into private storage with a random, extensionless name.
	 *
	 * @param int   $ticket_id Ticket.
	 * @param int   $message_id Message.
	 * @param int   $user_id User.
	 * @param array $f File.
	 */
	private static function store_file( $ticket_id, $message_id, $user_id, array $f ) {
		global $wpdb;
		self::ensure_private_dir();
		$stored = bin2hex( random_bytes( 20 ) ) . '.bin';
		$dest   = self::private_dir() . '/' . $stored;
		$moved  = defined( 'RSP_TESTING' ) ? copy( $f['tmp'], $dest ) : move_uploaded_file( $f['tmp'], $dest );
		if ( ! $moved ) {
			return;
		}
		$wpdb->insert(
			self::t( 'attachments' ),
			array(
				'ticket_id'     => $ticket_id,
				'message_id'    => $message_id,
				'user_id'       => $user_id,
				'stored_name'   => $stored,
				'original_name' => substr( $f['name'], 0, 190 ),
				'mime'          => $f['mime'],
				'size'          => $f['size'],
				'created_at'    => RSP_Installer::now(),
			)
		);
	}

	/**
	 * Download URL (nonce-protected).
	 *
	 * @param int $attachment_id Attachment.
	 * @return string
	 */
	public static function download_url( $attachment_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=rsp_attachment&id=' . (int) $attachment_id ), 'rsp_attachment_' . (int) $attachment_id );
	}

	/**
	 * Authorize an attachment for a user.
	 *
	 * @param int $attachment_id Attachment.
	 * @param int $user_id User.
	 * @return object|null Attachment row when allowed.
	 */
	public static function authorize_attachment( $attachment_id, $user_id ) {
		global $wpdb;
		$att = $wpdb->get_row( $wpdb->prepare( 'SELECT a.*, m.is_internal FROM ' . self::t( 'attachments' ) . ' a JOIN ' . self::t( 'ticket_messages' ) . ' m ON m.id = a.message_id WHERE a.id = %d', $attachment_id ) ); // phpcs:ignore
		if ( ! $att ) {
			return null;
		}
		$ticket = self::get( (int) $att->ticket_id );
		if ( ! self::can_view( $ticket, $user_id ) ) {
			return null;
		}
		if ( $att->is_internal && ! user_can( $user_id, 'rsp_manage_support' ) ) {
			return null;
		}
		return $att;
	}

	/**
	 * Stream an attachment after ownership + nonce checks.
	 */
	public static function download() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rsp_attachment_' . $id ) ) {
			wp_die( esc_html__( 'Link expired. Reload the ticket and try again.', 'relay-stream-portal' ), '', array( 'response' => 403 ) );
		}
		$att = self::authorize_attachment( $id, get_current_user_id() );
		if ( ! $att ) {
			wp_die( esc_html__( 'File not found.', 'relay-stream-portal' ), '', array( 'response' => 404 ) );
		}
		$path = self::private_dir() . '/' . basename( $att->stored_name );
		if ( ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'relay-stream-portal' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $att->original_name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $att->original_name ) );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Staff update of status/assignment/priority.
	 *
	 * @param int   $id Ticket.
	 * @param array $data status, assigned_to, priority.
	 */
	public static function staff_update( $id, array $data ) {
		global $wpdb;
		$upd = array( 'updated_at' => RSP_Installer::now() );
		if ( isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) ) {
			$upd['status'] = $data['status'];
		}
		if ( isset( $data['priority'] ) && in_array( $data['priority'], array( 'low', 'normal', 'high' ), true ) ) {
			$upd['priority'] = $data['priority'];
		}
		if ( isset( $data['assigned_to'] ) ) {
			$aid                = (int) $data['assigned_to'];
			$upd['assigned_to'] = $aid && user_can( $aid, 'rsp_manage_support' ) ? $aid : null;
		}
		$wpdb->update( self::t(), $upd, array( 'id' => $id ) );
		RSP_Audit::log( 'ticket_updated', 'ticket', $id, '', array_diff_key( $upd, array( 'updated_at' => 1 ) ) );
	}
}
