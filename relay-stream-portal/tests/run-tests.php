<?php
/**
 * Integration test suite. Run inside a WordPress install with WooCommerce (and optionally Elementor):
 *
 *   wp eval-file wp-content/plugins/relay-stream-portal/tests/run-tests.php
 *
 * Provider calls use the FIXTURE fake panel (tests/fixtures/class-fake-panel.php). Nothing here
 * talks to a real panel. Creates users/orders prefixed "rsptest".
 *
 * @package RelayStreamPortal\Tests
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}
if ( ! defined( 'RSP_TESTING' ) ) {
	define( 'RSP_TESTING', true );
}
require_once __DIR__ . '/fixtures/class-fake-megaott.php';

$GLOBALS['rsp_results'] = array();
$GLOBALS['rsp_mail']    = array();

/**
 * Assert.
 *
 * @param string $group Group.
 * @param string $name Name.
 * @param bool   $ok Result.
 * @param string $detail Detail.
 */
function rsp_t( $group, $name, $ok, $detail = '' ) {
	$GLOBALS['rsp_results'][] = array( 'group' => $group, 'name' => $name, 'ok' => (bool) $ok, 'detail' => $detail );
	WP_CLI::line( ( $ok ? "\033[32mPASS\033[0m " : "\033[31mFAIL\033[0m " ) . "[$group] $name" . ( $ok || '' === $detail ? '' : " — $detail" ) );
}

// Fresh rate-limit state and no auth cookies in CLI.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_rsp\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_rsp\\_rl\\_%'" ); // phpcs:ignore
wp_cache_flush();
add_filter( 'send_auth_cookies', '__return_false' );

// Capture all mail instead of sending.
add_filter(
	'pre_wp_mail',
	static function ( $null, $atts ) {
		$GLOBALS['rsp_mail'][] = $atts;
		return true;
	},
	10,
	2
);

/**
 * Create a test customer.
 *
 * @param string $tag Tag.
 * @return int
 */
function rsp_user( $tag ) {
	$email = 'rsptest-' . $tag . '-' . strtolower( wp_generate_password( 6, false ) ) . '@example.test';
	$id    = RSP_Auth::register( array( 'email' => $email, 'password' => 'Secret-pass-123', 'first_name' => ucfirst( $tag ) ) );
	return is_wp_error( $id ) ? 0 : $id;
}

/**
 * Build a plan order through the real snapshot hook, unpaid.
 *
 * @param int    $user_id User.
 * @param int    $product_id Plan product.
 * @param string $purpose new|renew|gift.
 * @param array  $extra Cart values.
 * @return WC_Order
 */
function rsp_order( $user_id, $product_id, $purpose, array $extra = array() ) {
	$order = wc_create_order( array( 'customer_id' => $user_id ) );
	$order->set_billing_email( get_userdata( $user_id )->user_email );
	$item = new WC_Order_Item_Product();
	$item->set_product( wc_get_product( $product_id ) );
	$item->set_quantity( 1 );
	$item->set_total( wc_get_product( $product_id )->get_price() );
	RSP_Woocommerce::snapshot_line_item( $item, 'k', array_merge( array( 'rsp_purpose' => $purpose ), $extra ), $order );
	$order->add_item( $item );
	$order->set_payment_method( 'cheque' );
	$order->set_payment_method_title( 'Test payment (offline)' );
	$order->calculate_totals();
	$order->save();
	return wc_get_order( $order->get_id() );
}

/**
 * Switch to live MegaOTT mode with the fixture API and map the two test plans to packages 4 (1 month) and 5 (3 months).
 *
 * @return RSP_Fake_Megaott
 */
function rsp_live() {
	$plan1 = $GLOBALS['rsp_plan1'];
	$plan3 = $GLOBALS['rsp_plan3'];
	$mo                              = new RSP_Fake_Megaott();
	RSP_Provider_Factory::$transport = $mo->transport();
	RSP_Settings::update( array( 'api_mode' => 'live', 'api_mapping_verified' => 1, 'megaott_token_enc' => RSP_Crypto::encrypt( $mo->token ), 'megaott_base_url' => 'https://megaott.net/api/v1', 'api_allowed_hosts' => 'megaott.net', 'megaott_connections' => 2 ) );
	foreach ( array( $plan1 => '4', $plan3 => '5' ) as $pid => $pkg ) {
		$pr = wc_get_product( $pid );
		$pr->update_meta_data( '_rsp_package', $pkg );
		$pr->save();
	}
	return $mo;
}

/**
 * Manual mode.
 */
function rsp_manual() {
	RSP_Provider_Factory::$transport = null;
	RSP_Settings::update( array( 'api_mode' => 'manual', 'api_mapping_verified' => 0, 'megaott_token_enc' => '' ) );
}

$plans = RSP_Woocommerce::plans();
$plan1 = $plans[0]['id'];
$plan3 = $plans[1]['id'];
$GLOBALS['rsp_plan1'] = $plan1;
$GLOBALS['rsp_plan3'] = $plan3;

/* ================= Adapter transport security (fixture) ================= */
$fx = new RSP_Fake_Megaott();
$bad = new RSP_Megaott_Provider( 'http://megaott.net/api/v1', $fx->token, array( 'megaott.net' ), 20, array(), $fx->transport() );
rsp_t( 'adapter', 'non-HTTPS endpoint refused before sending', 'failed' === $bad->test_connection()->outcome && 0 === count( $fx->calls ) );
$bad = new RSP_Megaott_Provider( 'https://evil.example/api/v1', $fx->token, array( 'megaott.net' ), 20, array(), $fx->transport() );
rsp_t( 'adapter', 'host outside allowlist refused before sending', 'failed' === $bad->test_connection()->outcome && 0 === count( $fx->calls ) );
$bad = new RSP_Megaott_Provider( 'https://megaott.net/api/v1', '', array( 'megaott.net' ), 20, array(), $fx->transport() );
rsp_t( 'adapter', 'missing token refused before sending', 'failed' === $bad->test_connection()->outcome && 0 === count( $fx->calls ) );
$mp = new RSP_Megaott_Provider( 'https://megaott.net/api/v1', $fx->token, array( 'megaott.net' ), 20, array(), $fx->transport() );
$fx->next = array( 'timeout_before_apply' );
rsp_t( 'adapter', 'write timeout => ambiguous', 'ambiguous' === $mp->create_account( array( 'months' => 1, 'package' => '4', 'username' => 'tx1' ) )->outcome );
$fx->next = array( 'http500' );
rsp_t( 'adapter', '5xx on write => ambiguous', 'ambiguous' === $mp->create_account( array( 'months' => 1, 'package' => '4', 'username' => 'tx2' ) )->outcome );
$fx->next = array( 'timeout_before_apply' );
rsp_t( 'adapter', 'read timeout => failed (safe to retry)', 'failed' === $mp->test_connection()->outcome );
rsp_t( 'adapter', 'validation error is permanent (no blind retries)', ! empty( $mp->create_account( array( 'months' => 1, 'package' => '77', 'username' => 'tx3' ) )->data['permanent'] ) );
$e = RSP_Audit::redact_string( 'https://x/api?username=bob&password=pw&token=SECRET' );
rsp_t( 'adapter', 'secrets redacted in strings', false === strpos( $e, 'SECRET' ) && false === strpos( $e, 'bob' ) && false === strpos( $e, '=pw' ) );

/* ================= Crypto ================= */
$enc = RSP_Crypto::encrypt( 'hello-secret' );
rsp_t( 'crypto', 'round trip', 'hello-secret' === RSP_Crypto::decrypt( $enc ) && false === strpos( $enc, 'hello' ) );
$raw      = base64_decode( substr( $enc, 5 ) );
$raw[30]  = chr( ord( $raw[30] ) ^ 1 );
$tampered = 'rsp1:' . base64_encode( $raw );
rsp_t( 'crypto', 'tampered ciphertext rejected', '' === RSP_Crypto::decrypt( $tampered ) );
rsp_t( 'crypto', 'mask keeps last 4', '••••••••cret' === RSP_Crypto::mask( 'hello-secret' ) );

/* ================= Auth ================= */
RSP_Settings::update( array( 'email_verification' => 0, 'registration_open' => 1 ) );
$a = rsp_user( 'alice' );
rsp_t( 'auth', 'registration creates customer', $a > 0 && in_array( 'customer', get_userdata( $a )->roles, true ) );
$dup = RSP_Auth::register( array( 'email' => get_userdata( $a )->user_email, 'password' => 'Secret-pass-123' ) );
rsp_t( 'auth', 'duplicate email rejected', is_wp_error( $dup ) );
rsp_t( 'auth', 'short password rejected', is_wp_error( RSP_Auth::register( array( 'email' => 'rsptest-short@example.test', 'password' => 'short' ) ) ) );
rsp_t( 'auth', 'wrong password rejected', is_wp_error( RSP_Auth::login( get_userdata( $a )->user_email, 'nope', false ) ) );
$u = RSP_Auth::login( get_userdata( $a )->user_email, 'Secret-pass-123', false );
rsp_t( 'auth', 'login by email works', $u instanceof WP_User && $u->ID === $a );
rsp_t( 'auth', 'open redirect blocked', RSP_Settings::page_url( 'dashboard' ) === RSP_Auth::safe_redirect_target( 'https://evil.example/' ) );
rsp_t( 'auth', 'same-site redirect allowed', home_url( '/portal-plans/' ) === RSP_Auth::safe_redirect_target( home_url( '/portal-plans/' ) ) );
RSP_Settings::update( array( 'email_verification' => 1 ) );
$GLOBALS['rsp_mail'] = array();
$v                   = rsp_user( 'verify' );
$blocked             = RSP_Auth::login( get_userdata( $v )->user_email, 'Secret-pass-123', false );
$mail                = end( $GLOBALS['rsp_mail'] );
preg_match( '/rsp_verify=([A-Za-z0-9]+)/', $mail['message'], $m );
rsp_t( 'auth', 'unverified user cannot log in; verification email sent', is_wp_error( $blocked ) && 'rsp_unverified' === $blocked->get_error_code() && ! empty( $m[1] ) );
rsp_t( 'auth', 'verification token valid', ! empty( $m[1] ) && wp_check_password( $m[1], get_user_meta( $v, 'rsp_verify_hash', true ), $v ) );
update_user_meta( $v, 'rsp_email_verified', '1' );
rsp_t( 'auth', 'verified user can log in', RSP_Auth::login( get_userdata( $v )->user_email, 'Secret-pass-123', false ) instanceof WP_User );
RSP_Settings::update( array( 'email_verification' => 0 ) );

/* ================= Manual fulfillment ================= */
rsp_manual();
wp_set_current_user( $a );
$o = rsp_order( $a, $plan1, 'new', array( 'rsp_nickname' => 'Lounge' ) );
ob_start();
do_action( 'woocommerce_thankyou', $o->get_id() );
ob_end_clean();
rsp_t( 'billing', 'thank-you page on unpaid order creates no job', 0 === count( RSP_Jobs::for_order( $o->get_id() ) ) );
$snap = RSP_Woocommerce::snapshot( current( $o->get_items() ) );
rsp_t( 'billing', 'immutable snapshot stored on the order item', $snap && 1 === (int) $snap['months'] && array_key_exists( 'package', $snap ) && 'new' === $snap['purpose'] );
$o->payment_complete( 'TEST-TXN-1' );
$o->update_status( 'completed' );
do_action( 'woocommerce_payment_complete', $o->get_id() );
$jobs = RSP_Jobs::for_order( $o->get_id() );
rsp_t( 'billing', 'duplicate payment events create exactly one job', 1 === count( $jobs ) );
rsp_t( 'billing', 'manual mode: job waits in staff review, account pending activation', 'needs_review' === $jobs[0]->state && 'pending_activation' === RSP_Accounts::get( $jobs[0]->account_id )->status );
rsp_t( 'billing', 'payment and provisioning status separate', 'needs_review' === RSP_Fulfillment::order_provisioning_status( wc_get_order( $o->get_id() ) ) && wc_get_order( $o->get_id() )->is_paid() );
wp_set_current_user( 1 );
$res = RSP_Jobs::manual_complete( $jobs[0]->id, array( 'username' => 'manual_u', 'password' => 'manual_p', 'server' => 'http://srv.example.test', 'expiry' => gmdate( 'Y-m-d', strtotime( '+1 month' ) ) ), 'Provisioned in panel during test' );
$acc = RSP_Accounts::get( $jobs[0]->account_id );
rsp_t( 'billing', 'manual completion activates account with confirmed expiry', true === $res && 'active' === $acc->status && 'Lounge' === $acc->nickname );
rsp_t( 'billing', 'credentials stored encrypted', false === strpos( (string) $acc->credentials, 'manual_p' ) && 'manual_p' === RSP_Accounts::credentials( $acc )['password'] );
global $wpdb;
$audit = $wpdb->get_var( $wpdb->prepare( 'SELECT context FROM ' . RSP_Installer::table( 'audit' ) . " WHERE action = 'manual_completion' AND target_id = %s", $jobs[0]->id ) ); // phpcs:ignore
rsp_t( 'billing', 'manual override audited without secrets', $audit && false === strpos( $audit, 'manual_p' ) );
rsp_t( 'billing', 'activation notification created', 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RSP_Installer::table( 'notifications' ) . ' WHERE user_id = %d AND type = %s', $a, 'activation' ) ) ); // phpcs:ignore
$mails = wp_json_encode( $GLOBALS['rsp_mail'] );
rsp_t( 'billing', 'no email contains player password', false === strpos( $mails, 'manual_p' ) );

/* ================= Live fulfillment (MegaOTT fixture) ================= */
$panel = rsp_live();
wp_set_current_user( $a );
$o2 = rsp_order( $a, $plan3, 'new', array( 'rsp_nickname' => 'Bedroom' ) );
$o2->payment_complete( 'TEST-TXN-2' );
RSP_Jobs::process_queue();
$j2   = RSP_Jobs::for_order( $o2->get_id() )[0];
$acc2 = RSP_Accounts::get( $j2->account_id );
rsp_t( 'live', 'paid order -> create -> verified by lookup -> active', 'succeeded' === $j2->state && 'active' === $acc2->status && $acc2->provider_user_id && 1 === $panel->creates() );
$line = $panel->subs[ (int) $acc2->provider_user_id ];
rsp_t( 'live', 'local expiry equals panel-confirmed expiry', substr( $acc2->expires_at, 0, 10 ) === substr( $line['expiring_at'], 0, 10 ) );
rsp_t( 'live', 'panel note carries stable local id', 'rsp-' . $acc2->uuid === $line['note'] );
do_action( 'woocommerce_order_status_completed', $o2->get_id() );
RSP_Jobs::process_queue();
rsp_t( 'live', 'replayed payment event does not create a second remote line', 1 === $panel->creates() && 1 === count( RSP_Jobs::for_order( $o2->get_id() ) ) );

$o3 = rsp_order( $a, $plan1, 'new', array( 'rsp_nickname' => 'Kitchen' ) );
$o3->payment_complete( 'TEST-TXN-3' );
RSP_Jobs::process_queue();
$acc3    = RSP_Accounts::get( RSP_Jobs::for_order( $o3->get_id() )[0]->account_id );
$before2 = RSP_Accounts::get( $acc2->id )->expires_at;
$before3 = $acc3->expires_at;
$o4      = rsp_order( $a, $plan3, 'renew', array( 'rsp_account' => $acc3->uuid ) );
$o4->payment_complete( 'TEST-TXN-4' );
RSP_Jobs::process_queue();
$j4 = RSP_Jobs::for_order( $o4->get_id() )[0];
rsp_t( 'live', 'renewal applies to the selected account only', 'succeeded' === $j4->state && (int) $j4->account_id === (int) $acc3->id && RSP_Accounts::get( $acc3->id )->expires_at > $before3 && RSP_Accounts::get( $acc2->id )->expires_at === $before2 );

$o6          = rsp_order( $a, $plan1, 'renew', array( 'rsp_account' => $acc3->uuid ) );
$panel->next = array( null, 'timeout_before_apply' );
$o6->payment_complete( 'TEST-TXN-6' );
RSP_Jobs::process_queue();
$j6 = RSP_Jobs::for_order( $o6->get_id() )[0];
RSP_Jobs::process_queue();
$ext = count( array_filter( $panel->calls, static function ( $c ) { return 'POST /subscriptions/{id}/extend' === $c['route']; } ) );
rsp_t( 'live', 'extend lost before applying -> expiry unchanged -> needs review, not replayed', 'needs_review' === RSP_Jobs::get( $j6->id )->state && 2 === $ext );
wp_set_current_user( 1 );
$retry = RSP_Jobs::staff_retry( $j6->id, 'Confirmed unchanged in panel' );
RSP_Jobs::process_queue();
rsp_t( 'live', 'staff retry after reconciliation succeeds', true === $retry && 'succeeded' === RSP_Jobs::get( $j6->id )->state );
wp_set_current_user( $a );

$unmapped = wc_get_product( $plans[2]['id'] );
$ou       = rsp_order( $a, $unmapped->get_id(), 'new' );
$ou->payment_complete( 'TEST-TXN-U' );
RSP_Jobs::process_queue();
$ju = RSP_Jobs::for_order( $ou->get_id() )[0];
rsp_t( 'live', 'plan without a MegaOTT package goes straight to review (no retries, no remote call)', 'needs_review' === $ju->state && 1 === (int) $ju->attempts && false !== strpos( $ju->review_reason, 'package' ) );

$legacy = RSP_Accounts::create( array( 'user_id' => $a, 'nickname' => 'Old panel', 'provider' => 'tvplus', 'status' => 'active' ) );
RSP_Accounts::update( $legacy->id, array( 'provider_user_id' => (string) $acc2->provider_user_id, 'expires_at' => gmdate( 'Y-m-d 23:59:59', strtotime( '+5 days' ) ) ) );
RSP_Accounts::set_credentials( $legacy->id, array( 'username' => 'old', 'password' => 'old' ) );
$exts_before = count( array_filter( $panel->calls, static function ( $c ) { return 'POST /subscriptions/{id}/extend' === $c['route']; } ) );
$ol          = rsp_order( $a, $plan1, 'renew', array( 'rsp_account' => $legacy->uuid ) );
$ol->payment_complete( 'TEST-TXN-L' );
RSP_Jobs::process_queue();
$jl = RSP_Jobs::for_order( $ol->get_id() )[0];
rsp_t( 'live', 'account from a previous (non-MegaOTT) panel is never sent to MegaOTT', 'needs_review' === $jl->state && count( array_filter( $panel->calls, static function ( $c ) { return 'POST /subscriptions/{id}/extend' === $c['route']; } ) ) === $exts_before );
RSP_Accounts::update( $legacy->id, array( 'status' => 'cancelled' ) );

$b  = rsp_user( 'bob' );
$o8 = rsp_order( $b, $plan1, 'renew', array( 'rsp_account' => $acc3->uuid ) );
$o8->payment_complete( 'TEST-TXN-8' );
RSP_Jobs::process_queue();
$j8 = RSP_Jobs::for_order( $o8->get_id() )[0];
rsp_t( 'live', 'renewal of an account not owned by the payer -> review, account untouched', 'needs_review' === $j8->state && 0 === (int) $j8->account_id );
wp_set_current_user( $b );
rsp_t( 'live', 'checkout refuses to renew another customer\'s account', is_wp_error( RSP_Woocommerce::start_checkout( $b, $plan1, 'renew', array( 'account' => $acc3->uuid ) ) ) );

/* ================= Refund policy ================= */
rsp_manual();
wp_set_current_user( $a );
$o9 = rsp_order( $a, $plan1, 'new' );
$o9->payment_complete( 'TEST-TXN-9' );
$j9 = RSP_Jobs::for_order( $o9->get_id() )[0];
wc_create_refund( array( 'order_id' => $o9->get_id(), 'amount' => $o9->get_total(), 'reason' => 'test' ) );
rsp_t( 'refunds', 'full refund before activation cancels the job and account', 'failed' === RSP_Jobs::get( $j9->id )->state && 'cancelled' === RSP_Accounts::get( $j9->account_id )->status );
wc_create_refund( array( 'order_id' => $o2->get_id(), 'amount' => 1, 'reason' => 'partial test' ) );
rsp_t( 'refunds', 'partial refund flags review without changing the account', '' !== (string) wc_get_order( $o2->get_id() )->get_meta( '_rsp_needs_review' ) && 'active' === RSP_Accounts::get( $acc2->id )->status );

/* ================= Gifts ================= */
$og = rsp_order( $a, $plan3, 'gift', array( 'rsp_gift_email' => '' ) );
$og->payment_complete( 'TEST-TXN-G' );
do_action( 'woocommerce_order_status_completed', $og->get_id() );
$gifts = RSP_Gifts::purchased_by( $a );
$gift  = $gifts[0];
$code  = RSP_Gifts::code_for_owner( $gift, $a );
rsp_t( 'gifts', 'paid gift issues exactly one encrypted code', 1 === count( array_filter( $gifts, static function ( $g ) use ( $og ) { return (int) $g->order_id === $og->get_id(); } ) ) && 14 === strlen( $code ) && false === strpos( $gift->code_enc, str_replace( '-', '', $code ) ) );
rsp_t( 'gifts', 'non-owner cannot read code', '' === RSP_Gifts::code_for_owner( $gift, $b ) );
$GLOBALS['rsp_gift_code'] = $code;
update_option( 'rsp_test_gift_code', $code, false );
update_option( 'rsp_test_gift_user', $b, false );

/* ================= Referrals ================= */
RSP_Settings::update( array( 'referral_enabled' => 1, 'referral_days' => 30, 'referral_review_days' => 7, 'referral_reversal' => 'reverse_unapplied' ) );
$ref  = rsp_user( 'referrer' );
$fr   = rsp_user( 'friend' );
$code = RSP_Referrals::code_for( $ref );
rsp_t( 'referrals', 'self-referral rejected', is_wp_error( RSP_Referrals::bind( $ref, $code ) ) );
rsp_t( 'referrals', 'friend binds once', true === RSP_Referrals::bind( $fr, $code ) && is_wp_error( RSP_Referrals::bind( $fr, $code ) ) );
$of = rsp_order( $fr, $plan1, 'new' );
$of->payment_complete( 'TEST-TXN-R1' );
RSP_Referrals::process_rewards();
rsp_t( 'referrals', 'no reward during review period', 0 === RSP_Referrals::balance( $ref ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . RSP_Installer::table( 'referrals' ) . ' SET eligible_at = %s WHERE referee_id = %d', gmdate( 'Y-m-d H:i:s', time() - 60 ), $fr ) ); // phpcs:ignore
RSP_Referrals::process_rewards();
RSP_Referrals::process_rewards();
$of2 = rsp_order( $fr, $plan1, 'new' );
$of2->payment_complete( 'TEST-TXN-R2' );
RSP_Referrals::process_rewards();
rsp_t( 'referrals', 'reward credited exactly once (30 days) across repeated runs and later orders', 30 === RSP_Referrals::balance( $ref ) && 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RSP_Installer::table( 'ledger' ) . ' WHERE user_id = %d', $ref ) ) ); // phpcs:ignore
$s = RSP_Referrals::stats( $ref );
rsp_t( 'referrals', 'stats reflect real data', 1 === $s['invited'] && 1 === $s['converted'] && 30 === $s['earned'] );
$h = RSP_Referrals::history( $ref );
rsp_t( 'referrals', 'history hides invitee email', false === strpos( wp_json_encode( $h ), '@' ) );
wc_create_refund( array( 'order_id' => $of->get_id(), 'amount' => $of->get_total(), 'reason' => 'test' ) );
rsp_t( 'referrals', 'refund after reward reverses unused days (policy reverse_unapplied)', 0 === RSP_Referrals::balance( $ref ) );
// Apply days: provider cannot add arbitrary days -> staff review, balance reserved.
RSP_Referrals::ledger_add( $ref, 30, 'adjustment', 'test-adjust-' . wp_generate_uuid4() );
$oa = rsp_order( $ref, $plan1, 'new' );
$oa->payment_complete( 'TEST-TXN-R3' );
$ja = RSP_Jobs::for_order( $oa->get_id() )[0];
wp_set_current_user( 1 );
RSP_Jobs::manual_complete( $ja->id, array( 'username' => 'x', 'password' => 'y', 'expiry' => gmdate( 'Y-m-d', strtotime( '+20 days' ) ) ), 'test' );
$racc = RSP_Accounts::get( $ja->account_id );
$ap   = RSP_Referrals::apply_days( $ref, $racc->uuid );
rsp_t( 'referrals', 'applying days without provider support -> staff review, days reserved', ! is_wp_error( $ap ) && 'needs_review' === $ap['job']->state && 0 === RSP_Referrals::balance( $ref ) );
RSP_Jobs::cancel( $ap['job']->id, 'test cancel' );
rsp_t( 'referrals', 'cancelled reward job returns the days', 30 === RSP_Referrals::balance( $ref ) );

/* ================= Tickets & attachments ================= */
wp_set_current_user( $a );
$tmp = wp_tempnam( 'rsp.png' );
file_put_contents( $tmp, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ) ); // phpcs:ignore
$tid = RSP_Tickets::create( $a, array( 'subject' => 'Test ticket', 'category' => 'technical', 'body' => 'Hello there team', 'account_uuid' => $acc2->uuid ), array( 'name' => array( 'shot.png' ), 'tmp_name' => array( $tmp ), 'error' => array( 0 ), 'size' => array( filesize( $tmp ) ) ) );
$att = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RSP_Installer::table( 'attachments' ) . ' WHERE ticket_id = %d', $tid ) ); // phpcs:ignore
rsp_t( 'support', 'ticket with validated attachment stored privately', is_int( $tid ) && $att && '.bin' === substr( $att->stored_name, -4 ) && file_exists( RSP_Tickets::private_dir() . '/' . $att->stored_name ) );
rsp_t( 'support', 'private dir has deny rules', file_exists( RSP_Tickets::private_dir() . '/.htaccess' ) );
$php = wp_tempnam( 'evil.php' );
file_put_contents( $php, '<?php echo 1;' ); // phpcs:ignore
rsp_t( 'support', 'PHP upload rejected', is_wp_error( RSP_Tickets::create( $a, array( 'subject' => 'Bad', 'body' => 'Bad upload test' ), array( 'name' => array( 'evil.php' ), 'tmp_name' => array( $php ), 'error' => array( 0 ), 'size' => array( 13 ) ) ) ) );
rsp_t( 'isolation', 'other customer cannot view ticket', ! RSP_Tickets::can_view( RSP_Tickets::get( $tid ), $b ) );
rsp_t( 'isolation', 'other customer cannot download attachment', null === RSP_Tickets::authorize_attachment( (int) $att->id, $b ) && null !== RSP_Tickets::authorize_attachment( (int) $att->id, $a ) );
wp_set_current_user( 1 );
RSP_Tickets::reply( $tid, 1, 'Internal: check panel', true );
RSP_Tickets::reply( $tid, 1, 'We are on it', false );
rsp_t( 'support', 'internal notes hidden from customer; staff reply sets awaiting customer', 0 === count( array_filter( RSP_Tickets::messages( $tid, false ), static function ( $m ) { return false !== strpos( $m->body, 'Internal' ); } ) ) && 1 === count( array_filter( RSP_Tickets::messages( $tid, true ), static function ( $m ) { return false !== strpos( $m->body, 'Internal' ); } ) ) && 'awaiting_customer' === RSP_Tickets::get( $tid )->status );
rsp_t( 'isolation', 'reply to someone else\'s ticket refused', is_wp_error( RSP_Tickets::reply( $tid, $b, 'hijack' ) ) );

/* ================= Isolation: accounts / REST / orders ================= */
rsp_t( 'isolation', 'get_owned refuses other customer', null === RSP_Accounts::get_owned( $acc2->uuid, $b ) && null !== RSP_Accounts::get_owned( $acc2->uuid, $a ) );
wp_set_current_user( $b );
$req = new WP_REST_Request( 'POST', '/rsp/v1/accounts/' . $acc2->uuid . '/credentials' );
$res = rest_do_request( $req );
rsp_t( 'isolation', 'REST credential reveal for non-owner -> 404', 404 === $res->get_status() );
$res = rest_do_request( new WP_REST_Request( 'GET', '/rsp/v1/accounts' ) );
rsp_t( 'isolation', 'REST account list only returns own accounts', 0 === count( array_filter( $res->get_data(), static function ( $r ) use ( $acc2 ) { return $r['uuid'] === $acc2->uuid; } ) ) );
rsp_t( 'isolation', 'REST list never includes credentials', false === strpos( wp_json_encode( $res->get_data() ), 'password' ) );
wp_set_current_user( $a );
$res = rest_do_request( $req );
rsp_t( 'isolation', 'owner can reveal credentials (no-store)', 200 === $res->get_status() && ! empty( $res->get_data()['password'] ) );
wp_set_current_user( 0 );
rsp_t( 'isolation', 'anonymous REST -> 401', 401 === rest_do_request( $req )->get_status() );
$ob = RSP_Woocommerce::customer_orders( $b, array( 'hide_stale' => false ) );
rsp_t( 'isolation', 'customer order list scoped to customer', 0 === count( array_filter( $ob['orders'], static function ( $o ) use ( $a ) { return (int) $o->get_customer_id() === $a; } ) ) );

/* ================= Rendering (shortcodes / Elementor / RTL) ================= */
wp_set_current_user( $a );
$all_ok = true;
$bad    = array();
foreach ( RSP_Shortcodes::map() as $tag => $def ) {
	$html = do_shortcode( '[' . $tag . ']' );
	if ( false === strpos( $html, 'rsp-root' ) || false !== strpos( $html, 'manual_p' ) ) {
		$all_ok = false;
		$bad[]  = $tag;
	}
}
rsp_t( 'render', 'all shortcodes render for a customer without leaking credentials', $all_ok, implode( ',', $bad ) );
wp_set_current_user( 0 );
$html = do_shortcode( '[relay_accounts]' );
rsp_t( 'render', 'protected module shows sign-in to guests', false !== strpos( $html, 'name="pwd"' ) && false === strpos( $html, 'Lounge' ) );
add_filter( 'rsp_force_preview', '__return_true' );
$html = do_shortcode( '[relay_portal module="accounts"]' );
rsp_t( 'render', 'editor preview uses synthetic data only', false !== strpos( $html, 'Living room' ) && false === strpos( $html, 'Lounge' ) );
remove_filter( 'rsp_force_preview', '__return_true' );
if ( did_action( 'elementor/loaded' ) ) {
	if ( ! get_option( 'rsp_test_el_page' ) ) {
		$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Elementor Portal Test', 'post_name' => 'elementor-portal-test' ) );
		update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
		update_post_meta( $pid, '_elementor_template_type', 'wp-page' );
		update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c0ffee1', 'elType' => 'container', 'settings' => array(), 'isInner' => false, 'elements' => array( array( 'id' => 'c0ffee2', 'elType' => 'widget', 'widgetType' => 'rsp-portal-shell', 'settings' => array( 'rsp_module' => 'dashboard', 'rsp_color_accent' => '#e11d48', 'rsp_label_nav_accounts' => 'My lines' ), 'elements' => array() ), array( 'id' => 'c0ffee3', 'elType' => 'widget', 'widgetType' => 'rsp-plan-cards', 'settings' => array(), 'elements' => array() ) ) ) ) ) ) );
		update_option( 'rsp_test_el_page', $pid, false );
	}
	$wm = \Elementor\Plugin::$instance->widgets_manager;
	$ok = true;
	foreach ( RSP_Elementor::definitions() as $d ) {
		$ok = $ok && (bool) $wm->get_widget_types( $d[0] );
	}
	rsp_t( 'elementor', 'all 18 widgets registered in the Relay Stream Portal category', $ok && 18 === count( RSP_Elementor::definitions() ) );
	add_filter( 'rsp_force_preview', '__return_true' );
	$w = \Elementor\Plugin::$instance->elements_manager->create_element_instance( array( 'id' => 'abc1234', 'elType' => 'widget', 'widgetType' => 'rsp-portal-shell', 'settings' => array( 'rsp_module' => 'plans' ) ) );
	ob_start();
	$w->render_content();
	$out = ob_get_clean();
	remove_filter( 'rsp_force_preview', '__return_true' );
	rsp_t( 'elementor', 'Portal Shell widget renders (editor preview)', false !== strpos( $out, 'rsp-shell' ) && false !== strpos( $out, 'Relay Half-Year' ) );
	$data = (string) get_post_meta( (int) get_option( 'rsp_test_el_page', 0 ), '_elementor_data', true );
	rsp_t( 'elementor', 'Elementor page JSON holds no customer data', false === strpos( $data, '@example.test' ) && false === strpos( $data, 'manual_' ) );
}
wp_set_current_user( $a );
RSP_Settings::update( array( 'language' => 'ar' ) );
$html = RSP_Portal::render_shell( array( 'module' => 'dashboard' ) );
rsp_t( 'i18n', 'Arabic portal renders right-to-left', false !== strpos( $html, 'dir="rtl"' ) );
RSP_Settings::update( array( 'language' => '' ) );


/* ================= MegaOTT adapter + live flow (fixture) ================= */
$mo = new RSP_Fake_Megaott();
$mp = new RSP_Megaott_Provider( 'https://megaott.net/api/v1', $mo->token, array( 'megaott.net' ), 20, array( 'whatsapp_telegram' => '+100' ), $mo->transport() );
$r  = $mp->test_connection();
rsp_t( 'megaott', 'GET /user connection test with Bearer token, username masked', $r->ok() && '120.5' === $r->data['credits'] && false === strpos( wp_json_encode( $r->data ), 'reseller_demo' ) && 'Bearer ' . $mo->token === $mo->calls[0]['auth'] );
rsp_t( 'megaott', 'token never appears in redacted logs', false === strpos( wp_json_encode( $r->to_log() ), $mo->token ) );
$r = $mp->list_packages();
rsp_t( 'megaott', 'paginated /packages fully read with period metadata', $r->ok() && 3 === $r->data['count'] && 'hour' === $r->data['meta']['9']['period_type'] );
$bad = new RSP_Megaott_Provider( 'https://megaott.net/api/v1', 'wrong', array( 'megaott.net' ), 20, array(), $mo->transport() );
rsp_t( 'megaott', '401 is a definitive failure', 'failed' === $bad->test_connection()->outcome );
rsp_t( 'megaott', '422 on create (bad package) => failed, not ambiguous', 'failed' === $mp->create_account( array( 'months' => 1, 'package' => '77', 'username' => 'zz1' ) )->outcome );
rsp_t( 'megaott', 'unmapped package refused locally', 'failed' === $mp->create_account( array( 'months' => 1, 'package' => 'all', 'username' => 'x' ) )->outcome );

$mo                              = new RSP_Fake_Megaott();
RSP_Provider_Factory::$transport = $mo->transport();
RSP_Settings::update( array( 'api_mode' => 'live', 'api_mapping_verified' => 1, 'megaott_token_enc' => RSP_Crypto::encrypt( $mo->token ), 'megaott_base_url' => 'https://megaott.net/api/v1', 'api_allowed_hosts' => 'megaott.net', 'megaott_connections' => 2 ) );
$mplan = wc_get_product( $plan1 );
$mplan->update_meta_data( '_rsp_package', '4' );
$mplan->save();
$mplan3 = wc_get_product( $plan3 );
$mplan3->update_meta_data( '_rsp_package', '5' );
$mplan3->save();
$mu = rsp_user( 'mega' );
wp_set_current_user( $mu );
$m1 = rsp_order( $mu, $plan1, 'new', array( 'rsp_nickname' => 'Mega TV' ) );
$m1->payment_complete( 'M-1' );
RSP_Jobs::process_queue();
$mj  = RSP_Jobs::for_order( $m1->get_id() )[0];
$mac = RSP_Accounts::get( $mj->account_id );
$mc  = RSP_Accounts::credentials( $mac );
$sub = $mo->subs[ (int) $mac->provider_user_id ];
rsp_t( 'megaott', 'paid order -> POST /subscriptions -> verified by GET -> active', 'succeeded' === $mj->state && 'active' === $mac->status && 1 === $mo->creates() );
rsp_t( 'megaott', 'username chosen by the portal; password, server and M3U stored encrypted', $mc['username'] === $sub['username'] && 'PW' . $sub['id'] . 'X' === $mc['password'] && 0 === strpos( $mc['url'], 'http://line.mega.example.test/get.php' ) && false === strpos( $mac->credentials, $mc['password'] ) );
rsp_t( 'megaott', 'expiry and connection limit taken from the panel', substr( $mac->expires_at, 0, 10 ) === substr( $sub['expiring_at'], 0, 10 ) && 2 === (int) $mac->max_connections && 'rsp-' . $mac->uuid === $sub['note'] );

$m2       = rsp_order( $mu, $plan1, 'new' );
$mo->next = array( 'timeout_after_apply' );
$m2->payment_complete( 'M-2' );
RSP_Jobs::process_queue();
$mj2 = RSP_Jobs::get( RSP_Jobs::for_order( $m2->get_id() )[0]->id );
rsp_t( 'megaott', 'create timed out after applying -> scheduled for reconciliation (not review, not resent)', 'pending' === $mj2->state && 'reconcile_create' === $mj2->step && 2 === $mo->creates() );
RSP_Jobs::update( $mj2->id, array( 'next_attempt_at' => RSP_Installer::now() ) );
RSP_Jobs::process_queue();
$mj2 = RSP_Jobs::get( $mj2->id );
rsp_t( 'megaott', 'reconciled via by-username: adopted the existing line, no duplicate create', 'succeeded' === $mj2->state && 2 === $mo->creates() && 2 === count( $mo->subs ) );

$m3       = rsp_order( $mu, $plan1, 'new' );
$mo->next = array( 'timeout_before_apply' );
$m3->payment_complete( 'M-3' );
RSP_Jobs::process_queue();
$mj3 = RSP_Jobs::for_order( $m3->get_id() )[0];
RSP_Jobs::update( $mj3->id, array( 'next_attempt_at' => RSP_Installer::now() ) );
RSP_Jobs::process_queue();
RSP_Jobs::update( $mj3->id, array( 'next_attempt_at' => RSP_Installer::now() ) );
RSP_Jobs::process_queue();
$mj3 = RSP_Jobs::get( $mj3->id );
rsp_t( 'megaott', 'create lost before applying -> by-username 404 -> safely re-created once', 'succeeded' === $mj3->state && 3 === count( $mo->subs ) );

$before = $sub['expiring_at'];
$m4     = rsp_order( $mu, $plan3, 'renew', array( 'rsp_account' => $mac->uuid ) );
$m4->payment_complete( 'M-4' );
RSP_Jobs::process_queue();
$mj4  = RSP_Jobs::for_order( $m4->get_id() )[0];
$ext  = array_values( array_filter( $mo->calls, static function ( $c ) { return 'POST /subscriptions/{id}/extend' === $c['route']; } ) );
rsp_t( 'megaott', 'renewal uses /extend with the purchased plan package on the right subscription', 'succeeded' === $mj4->state && 1 === count( $ext ) && '5' === (string) $ext[0]['body']['package_id'] && RSP_Accounts::get( $mac->id )->expires_at > $mac->expires_at );
$m5       = rsp_order( $mu, $plan1, 'renew', array( 'rsp_account' => $mac->uuid ) );
$mo->next = array( null, 'timeout_after_apply' );
$m5->payment_complete( 'M-5' );
RSP_Jobs::process_queue();
$mj5 = RSP_Jobs::for_order( $m5->get_id() )[0];
$res = json_decode( $mj5->result, true );
rsp_t( 'megaott', 'extend timeout after success reconciled by expiry comparison', 'succeeded' === $mj5->state && 'reconcile' === $res['reconciled'] );
$prov = RSP_Provider_Factory::get();
rsp_t( 'megaott', 'deactivate / activate supported', $prov->set_active( $mac->provider_user_id, false )->ok() && false === $mo->subs[ (int) $mac->provider_user_id ]['active'] && $prov->set_active( $mac->provider_user_id, true )->ok() );
$mplan->update_meta_data( '_rsp_package', '' );
$mplan->save();
$mplan3->update_meta_data( '_rsp_package', '' );
$mplan3->save();
rsp_manual();

/* ================= Summary ================= */
$pass = count( array_filter( $GLOBALS['rsp_results'], static function ( $r ) { return $r['ok']; } ) );
$fail = count( $GLOBALS['rsp_results'] ) - $pass;
update_option( 'rsp_test_results', array( 'when' => gmdate( 'c' ), 'pass' => $pass, 'fail' => $fail, 'results' => $GLOBALS['rsp_results'] ), false );
rsp_manual();
WP_CLI::line( "\n$pass passed, $fail failed" );
if ( $fail ) {
	WP_CLI::halt( 1 );
}
