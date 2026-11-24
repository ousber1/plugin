<?php
/**
 * Support module: ticket list, new ticket, ticket thread.
 *
 * @var array $vm
 * @var array $args
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

$rsp_p     = ! empty( $vm['preview'] );
$rsp_inert = RSP_Portal::inert( $vm );
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$rsp_tid = isset( $_GET['ticket'] ) ? (int) $_GET['ticket'] : 0;
$rsp_new = isset( $_GET['new'] ) || empty( $vm['tickets'] );
// phpcs:enable
$rsp_ticket = ( $rsp_tid && ! $rsp_p ) ? RSP_Tickets::get( $rsp_tid ) : null;
if ( $rsp_ticket && (int) $rsp_ticket->user_id !== get_current_user_id() ) {
	$rsp_ticket = null; // Ownership: customers only see their own tickets.
}
$rsp_cats  = RSP_Settings::support_categories();
$rsp_types = strtoupper( str_replace( ',', ', ', (string) RSP_Settings::get( 'attach_types' ) ) );
$rsp_base  = $rsp_p ? '#' : RSP_Settings::page_url( 'support' );
?>
<div class="rsp-page">
	<div class="rsp-page-head"><div><h1><?php echo esc_html( RSP_Portal::label( $args, 'title', __( 'Support', 'relay-stream-portal' ) ) ); ?></h1>
		<p class="rsp-muted"><?php esc_html_e( 'Private tickets between you and our team. We reply here and by email.', 'relay-stream-portal' ); ?></p></div>
		<?php if ( ! $rsp_new || $rsp_ticket ) : ?><a class="rsp-btn rsp-btn-primary" href="<?php echo esc_url( $rsp_p ? '#' : add_query_arg( 'new', 1, $rsp_base ) ); ?>"><?php RSP_Icons::e( 'plus' ); ?><?php esc_html_e( 'New ticket', 'relay-stream-portal' ); ?></a><?php endif; ?>
	</div>

	<?php if ( $rsp_ticket ) : ?>
		<?php $rsp_msgs = RSP_Tickets::messages( (int) $rsp_ticket->id, false ); ?>
		<section class="rsp-card rsp-thread">
			<div class="rsp-card-head"><div><h2><?php echo esc_html( $rsp_ticket->subject ); ?></h2>
				<small class="rsp-muted">#<?php echo (int) $rsp_ticket->id; ?> · <?php echo esc_html( isset( $rsp_cats[ $rsp_ticket->category ] ) ? $rsp_cats[ $rsp_ticket->category ] : $rsp_ticket->category ); ?></small></div>
				<?php echo RSP_Portal::pill( $rsp_ticket->status, RSP_Tickets::status_label( $rsp_ticket->status ) ); // phpcs:ignore ?></div>
			<ol class="rsp-messages">
				<?php foreach ( $rsp_msgs as $rsp_m ) :
					$rsp_mine = (int) $rsp_m->author_id === get_current_user_id();
					$rsp_au   = get_userdata( (int) $rsp_m->author_id );
					?>
					<li class="rsp-msg<?php echo $rsp_mine ? ' is-mine' : ' is-staff'; ?>">
						<div class="rsp-msg-meta"><strong><?php echo esc_html( $rsp_mine ? __( 'You', 'relay-stream-portal' ) : ( $rsp_au ? $rsp_au->display_name : __( 'Support', 'relay-stream-portal' ) ) ); ?></strong><time><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $rsp_m->created_at . ' UTC' ) ) ); ?></time></div>
						<div class="rsp-msg-body"><?php echo wp_kses_post( wpautop( esc_html( $rsp_m->body ) ) ); ?></div>
						<?php foreach ( RSP_Tickets::attachments( (int) $rsp_m->id ) as $rsp_att ) : ?>
							<a class="rsp-attachment" href="<?php echo esc_url( RSP_Tickets::download_url( (int) $rsp_att->id ) ); ?>"><?php RSP_Icons::e( 'paperclip', 16 ); ?><?php echo esc_html( $rsp_att->original_name ); ?> <small>(<?php echo esc_html( size_format( (int) $rsp_att->size ) ); ?>)</small></a>
						<?php endforeach; ?>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ( 'closed' !== $rsp_ticket->status ) : ?>
				<form method="post" enctype="multipart/form-data" class="rsp-form">
					<?php echo RSP_Forms::fields( 'ticket_reply' ); // phpcs:ignore ?>
					<input type="hidden" name="ticket" value="<?php echo (int) $rsp_ticket->id; ?>">
					<label class="rsp-field"><span><?php esc_html_e( 'Your reply', 'relay-stream-portal' ); ?></span><textarea name="body" rows="4" required maxlength="5000"></textarea></label>
					<label class="rsp-field"><span><?php
						/* translators: 1: types 2: MB */
						echo esc_html( sprintf( __( 'Attachments (up to 3 · %1$s · max %2$d MB each)', 'relay-stream-portal' ), $rsp_types, (int) RSP_Settings::get( 'attach_max_mb', 5 ) ) ); ?></span><input type="file" name="attachments[]" multiple></label>
					<button class="rsp-btn rsp-btn-primary" type="submit"><?php esc_html_e( 'Send reply', 'relay-stream-portal' ); ?></button>
				</form>
			<?php endif; ?>
			<p><a class="rsp-link" href="<?php echo esc_url( $rsp_base ); ?>">← <?php esc_html_e( 'All tickets', 'relay-stream-portal' ); ?></a></p>
		</section>
	<?php elseif ( $rsp_new ) : ?>
		<form method="post" enctype="multipart/form-data" class="rsp-card rsp-form">
			<?php echo RSP_Forms::fields( 'ticket_new' ); // phpcs:ignore ?>
			<div class="rsp-grid rsp-grid-2">
				<label class="rsp-field"><span><?php esc_html_e( 'Subject', 'relay-stream-portal' ); ?></span><input type="text" name="subject" required maxlength="190"></label>
				<label class="rsp-field"><span><?php esc_html_e( 'Category', 'relay-stream-portal' ); ?></span><select name="category"><?php foreach ( $rsp_cats as $rsp_k => $rsp_l ) : ?><option value="<?php echo esc_attr( $rsp_k ); ?>"><?php echo esc_html( $rsp_l ); ?></option><?php endforeach; ?></select></label>
				<?php if ( ! empty( $vm['accounts'] ) ) : ?>
					<label class="rsp-field"><span><?php esc_html_e( 'Related account (optional)', 'relay-stream-portal' ); ?></span><select name="account"><option value=""><?php esc_html_e( 'None', 'relay-stream-portal' ); ?></option><?php foreach ( $vm['accounts'] as $rsp_a ) : ?><option value="<?php echo esc_attr( $rsp_a['uuid'] ); ?>"><?php echo esc_html( $rsp_a['nickname'] ); ?></option><?php endforeach; ?></select></label>
				<?php endif; ?>
				<?php if ( ! empty( $vm['orders']['rows'] ) ) : ?>
					<label class="rsp-field"><span><?php esc_html_e( 'Related order (optional)', 'relay-stream-portal' ); ?></span><select name="order_id"><option value=""><?php esc_html_e( 'None', 'relay-stream-portal' ); ?></option><?php foreach ( $vm['orders']['rows'] as $rsp_o ) : ?><option value="<?php echo (int) $rsp_o['id']; ?>">#<?php echo esc_html( $rsp_o['number'] ); ?></option><?php endforeach; ?></select></label>
				<?php endif; ?>
			</div>
			<label class="rsp-field"><span><?php esc_html_e( 'How can we help?', 'relay-stream-portal' ); ?></span><textarea name="body" rows="6" required maxlength="5000"></textarea></label>
			<p class="rsp-muted rsp-small"><?php esc_html_e( 'Never share passwords in tickets. Our team can see your account status already.', 'relay-stream-portal' ); ?></p>
			<label class="rsp-field"><span><?php
				/* translators: 1: types 2: MB */
				echo esc_html( sprintf( __( 'Attachments (up to 3 · %1$s · max %2$d MB each)', 'relay-stream-portal' ), $rsp_types, (int) RSP_Settings::get( 'attach_max_mb', 5 ) ) ); ?></span><input type="file" name="attachments[]" multiple></label>
			<button class="rsp-btn rsp-btn-primary" type="submit"<?php echo $rsp_inert; // phpcs:ignore ?>><?php esc_html_e( 'Create ticket', 'relay-stream-portal' ); ?></button>
		</form>
	<?php endif; ?>

	<?php if ( ! $rsp_ticket && ! empty( $vm['tickets'] ) ) : ?>
		<section class="rsp-card">
			<div class="rsp-card-head"><h2><?php esc_html_e( 'Your tickets', 'relay-stream-portal' ); ?></h2></div>
			<ul class="rsp-mini-list">
				<?php foreach ( $vm['tickets'] as $rsp_t ) : ?>
					<li><a class="rsp-mini-name" href="<?php echo esc_url( $rsp_p ? '#' : add_query_arg( 'ticket', $rsp_t['id'], $rsp_base ) ); ?>"><?php echo esc_html( $rsp_t['subject'] ); ?><small>#<?php echo (int) $rsp_t['id']; ?> · <?php echo RSP_Portal::date( $rsp_t['updated_at'] ); // phpcs:ignore ?></small></a>
						<?php echo RSP_Portal::pill( $rsp_t['status'], $rsp_t['status_label'] ); // phpcs:ignore ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>
