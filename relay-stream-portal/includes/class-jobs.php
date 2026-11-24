<?php
/**
 * Provisioning operations queue.
 *
 * States: pending -> working -> succeeded | failed | needs_review.
 * Guarantees:
 *  - One job per idempotency key (UNIQUE index): duplicate payment events cannot create duplicate work.
 *  - Jobs are claimed with an atomic conditional UPDATE; accounts are locked per job.
 *  - The step is persisted BEFORE a remote write ('send'), so a crash or timeout is detected and
 *    reconciled instead of being replayed blindly.
 *  - Creates have no documented lookup/idempotency, so ambiguous creates go to staff review.
 *  - Renewals are reconciled by comparing the device_info expiry before and after.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Jobs.
 */
class RSP_Jobs {

	const STATES       = array( 'pending', 'working', 'succeeded', 'failed', 'needs_review' );
	const MAX_ATTEMPTS = 5;
	const STALE_AFTER  = 600;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( 'rsp_process_queue', array( __CLASS__, 'process_queue' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Every-minute schedule.
	 *
	 * @param array $s Schedules.
	 * @return array
	 */
	public static function cron_schedules( $s ) {
		$s['rsp_minute'] = array( 'interval' => 60, 'display' => 'Every minute (Relay Stream Portal)' );
		return $s;
	}

	/**
	 * Ensure cron events exist.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'rsp_process_queue' ) ) {
			wp_schedule_event( time() + 60, 'rsp_minute', 'rsp_process_queue' );
		}
		if ( ! wp_next_scheduled( 'rsp_daily' ) ) {
			wp_schedule_event( time() + 300, 'daily', 'rsp_daily' );
		}
	}

	/**
	 * Table.
	 *
	 * @return string
	 */
	private static function t() {
		return RSP_Installer::table( 'jobs' );
	}

	/**
	 * State label.
	 *
	 * @param string $state State.
	 * @return string
	 */
	public static function state_label( $state ) {
		$l = array(
			'pending'      => __( 'Pending', 'relay-stream-portal' ),
			'working'      => __( 'Working', 'relay-stream-portal' ),
			'succeeded'    => __( 'Succeeded', 'relay-stream-portal' ),
			'failed'       => __( 'Failed', 'relay-stream-portal' ),
			'needs_review' => __( 'Needs review', 'relay-stream-portal' ),
		);
		return isset( $l[ $state ] ) ? $l[ $state ] : $state;
	}

	/**
	 * Enqueue a job. Returns the existing job when the idempotency key is already used.
	 *
	 * @param string $key Idempotency key.
	 * @param string $type create|renew|reward_extend.
	 * @param array  $args account_id, user_id, order_id, order_item_id, payload.
	 * @return object|null Job row.
	 */
	public static function enqueue( $key, $type, array $args ) {
		global $wpdb;
		$now = RSP_Installer::now();
		// INSERT IGNORE relies on the UNIQUE(idempotency_key) index: concurrent duplicates collapse to one row.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::t() . ' (uuid, idempotency_key, type, state, step, account_id, user_id, order_id, order_item_id, payload, next_attempt_at, created_at, updated_at) VALUES (%s,%s,%s,%s,%s,%d,%d,%d,%d,%s,%s,%s,%s)', // phpcs:ignore
				RSP_Installer::uuid(),
				substr( $key, 0, 120 ),
				$type,
				'pending',
				'start',
				isset( $args['account_id'] ) ? (int) $args['account_id'] : 0,
				isset( $args['user_id'] ) ? (int) $args['user_id'] : 0,
				isset( $args['order_id'] ) ? (int) $args['order_id'] : 0,
				isset( $args['order_item_id'] ) ? (int) $args['order_item_id'] : 0,
				wp_json_encode( isset( $args['payload'] ) ? $args['payload'] : array() ),
				$now,
				$now,
				$now
			)
		);
		$inserted = (int) $wpdb->rows_affected;
		$job      = self::get_by_key( $key );
		if ( $inserted && $job ) {
			RSP_Audit::log( 'job_enqueued', 'job', $job->id, '', array( 'type' => $type, 'order_id' => $job->order_id ), 0 );
			if ( ! RSP_Provider_Factory::is_live() ) {
				self::to_review( $job, __( 'Manual mode: enter the account details to complete activation.', 'relay-stream-portal' ), false );
			} elseif ( ! defined( 'RSP_TESTING' ) ) {
				spawn_cron();
			}
			$job = self::get( $job->id );
		}
		return $job;
	}

	/**
	 * Get by id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ) ); // phpcs:ignore
	}

	/**
	 * Get by key.
	 *
	 * @param string $key Key.
	 * @return object|null
	 */
	public static function get_by_key( $key ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE idempotency_key = %s', $key ) ); // phpcs:ignore
	}

	/**
	 * Jobs for an order.
	 *
	 * @param int $order_id Order.
	 * @return object[]
	 */
	public static function for_order( $order_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE order_id = %d ORDER BY id ASC', $order_id ) ); // phpcs:ignore
	}

	/**
	 * Open (non-terminal or review) job for an account.
	 *
	 * @param int $account_id Account.
	 * @return object|null
	 */
	public static function open_for_account( $account_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE account_id = %d AND state IN ('pending','working','needs_review') ORDER BY id DESC LIMIT 1", $account_id ) ); // phpcs:ignore
	}

	/**
	 * Aggregate provisioning status for an order.
	 *
	 * @param int $order_id Order.
	 * @return string none|pending|working|succeeded|failed|needs_review
	 */
	public static function order_status( $order_id ) {
		$jobs = self::for_order( $order_id );
		if ( ! $jobs ) {
			return 'none';
		}
		$states = wp_list_pluck( $jobs, 'state' );
		foreach ( array( 'needs_review', 'failed', 'working', 'pending' ) as $s ) {
			if ( in_array( $s, $states, true ) ) {
				return $s;
			}
		}
		return 'succeeded';
	}

	/**
	 * Patch a job.
	 *
	 * @param int   $id Id.
	 * @param array $data Data.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = RSP_Installer::now();
		$wpdb->update( self::t(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Decode payload.
	 *
	 * @param object $job Job.
	 * @return array
	 */
	public static function payload( $job ) {
		$p = json_decode( (string) $job->payload, true );
		return is_array( $p ) ? $p : array();
	}

	/**
	 * Move to review.
	 *
	 * @param object $job Job.
	 * @param string $reason Reason.
	 * @param bool   $flag_account Mark a not-yet-active account as "under review" (false for routine manual work).
	 */
	public static function to_review( $job, $reason, $flag_account = true ) {
		self::update( $job->id, array( 'state' => 'needs_review', 'review_reason' => substr( $reason, 0, 255 ), 'locked_by' => null, 'locked_at' => null ) );
		if ( $flag_account && $job->account_id && 'create' === $job->type ) {
			$acc = RSP_Accounts::get( $job->account_id );
			if ( $acc && in_array( $acc->status, array( 'pending_activation', 'pending_payment' ), true ) ) {
				RSP_Accounts::update( $acc->id, array( 'status' => 'review', 'review_reason' => substr( $reason, 0, 255 ) ) );
			}
		}
		do_action( 'rsp_job_needs_review', $job, $reason );
	}

	/**
	 * Cron worker.
	 *
	 * @param int $limit Max jobs.
	 * @return int Processed count.
	 */
	public static function process_queue( $limit = 10 ) {
		global $wpdb;
		self::recover_stale();
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::t() . " WHERE state = 'pending' AND next_attempt_at <= %s ORDER BY id ASC LIMIT %d", RSP_Installer::now(), max( 1, (int) $limit ) ) ); // phpcs:ignore
		$n   = 0;
		foreach ( $ids as $id ) {
			if ( self::run( (int) $id ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Jobs stuck in 'working' (worker died mid-call) are reconciled, never replayed.
	 */
	public static function recover_stale() {
		global $wpdb;
		$cut  = gmdate( 'Y-m-d H:i:s', time() - self::STALE_AFTER );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . " WHERE state = 'working' AND locked_at < %s", $cut ) ); // phpcs:ignore
		foreach ( (array) $rows as $job ) {
			$pl = self::payload( $job );
			if ( 'send' === $job->step && 'create' === $job->type && ! empty( $pl['username'] ) && method_exists( RSP_Provider_Factory::get(), 'find_created' ) ) {
				self::update( $job->id, array( 'state' => 'pending', 'step' => 'reconcile_create', 'locked_by' => null, 'locked_at' => null ) );
			} elseif ( 'send' === $job->step && 'create' === $job->type ) {
				self::to_review( $job, 'Worker stopped while creating the remote account. Check the panel before retrying.' );
			} elseif ( 'send' === $job->step ) {
				self::update( $job->id, array( 'state' => 'pending', 'step' => 'reconcile', 'locked_by' => null, 'locked_at' => null ) );
			} else {
				self::update( $job->id, array( 'state' => 'pending', 'locked_by' => null, 'locked_at' => null ) );
			}
			if ( $job->account_id ) {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . RSP_Installer::table( 'accounts' ) . ' SET lock_token = NULL, lock_expires = NULL WHERE id = %d AND lock_expires < %d', $job->account_id, time() ) ); // phpcs:ignore
			}
		}
	}

	/**
	 * Claim and run one job.
	 *
	 * @param int $id Job id.
	 * @return bool Whether this worker ran it.
	 */
	public static function run( $id ) {
		global $wpdb;
		$token   = bin2hex( random_bytes( 16 ) );
		$claimed = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::t() . " SET state = 'working', locked_by = %s, locked_at = %s, attempts = attempts + 1 WHERE id = %d AND state = 'pending'", $token, RSP_Installer::now(), $id ) ); // phpcs:ignore
		if ( 1 !== (int) $claimed ) {
			return false;
		}
		$job  = self::get( $id );
		$lock = $job->account_id ? RSP_Accounts::lock( (int) $job->account_id ) : 'none';
		if ( ! $lock ) {
			// Another job holds this account; try again shortly without counting the attempt.
			self::update( $id, array( 'state' => 'pending', 'locked_by' => null, 'locked_at' => null, 'attempts' => max( 0, (int) $job->attempts - 1 ), 'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + 30 ) ) );
			return false;
		}
		try {
			$provider = RSP_Provider_Factory::get();
			$payload  = self::payload( $job );
			if ( 'manual' === $provider->slug() || ! empty( $payload['manual'] ) ) {
				self::to_review( $job, __( 'Manual mode: enter the account details to complete activation.', 'relay-stream-portal' ), false );
			} elseif ( 'create' === $job->type ) {
				self::run_create( $job, $provider );
			} elseif ( in_array( $job->type, array( 'renew', 'reward_extend' ), true ) ) {
				self::run_renew( $job, $provider );
			} else {
				self::to_review( $job, 'Unknown job type.' );
			}
		} catch ( \Throwable $e ) {
			self::to_review( $job, 'Unexpected error: ' . RSP_Audit::redact_string( $e->getMessage() ) );
		} finally {
			if ( $job->account_id && 'none' !== $lock ) {
				RSP_Accounts::unlock( (int) $job->account_id, $lock );
			}
		}
		return true;
	}

	/**
	 * Requeue with backoff or fail permanently.
	 *
	 * @param object              $job Job.
	 * @param RSP_Provider_Result $r Result.
	 * @param string              $step Step to resume at.
	 */
	private static function retry_or_fail( $job, RSP_Provider_Result $r, $step ) {
		$attempts = (int) self::get( $job->id )->attempts;
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			self::to_review( $job, 'Gave up after ' . $attempts . ' attempts: ' . $r->error );
			return;
		}
		self::update(
			$job->id,
			array(
				'state'           => 'pending',
				'step'            => $step,
				'last_error'      => substr( $r->error, 0, 500 ),
				'result'          => wp_json_encode( $r->to_log() ),
				'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + min( 3600, 60 * pow( 2, $attempts ) ) ),
				'locked_by'       => null,
				'locked_at'       => null,
			)
		);
	}

	/**
	 * Create flow.
	 *
	 * @param object                 $job Job.
	 * @param RSP_Provider_Interface $provider Provider.
	 */
	private static function run_create( $job, RSP_Provider_Interface $provider ) {
		$p       = self::payload( $job );
		$account = RSP_Accounts::get( $job->account_id );
		if ( ! $account ) {
			self::to_review( $job, 'Local account record is missing.' );
			return;
		}
		$can_find = method_exists( $provider, 'find_created' );

		// A previous attempt may have been applied remotely: look up the identifier we chose before sending.
		if ( 'reconcile_create' === $job->step ) {
			$f = $can_find ? $provider->find_created( array( 'username' => isset( $p['username'] ) ? $p['username'] : '', 'type' => $account->provider_type ) ) : new RSP_Provider_Result( 'ambiguous', array(), 'Provider has no create lookup.' );
			self::update( $job->id, array( 'result' => wp_json_encode( $f->to_log() ) ) );
			if ( $f->ok() ) {
				self::adopt_created( $job, $account, $provider, $f );
				$job->step = 'verify';
			} elseif ( ! empty( $f->data['not_found'] ) ) {
				RSP_Audit::log( 'create_reconciled_absent', 'job', $job->id, '', array(), 0 );
				self::update( $job->id, array( 'step' => 'start' ) );
				$job->step = 'start';
			} else {
				self::to_review( $job, 'Could not confirm whether the panel created this account: ' . $f->error );
				return;
			}
		}

		if ( 'start' === $job->step ) {
			// Guard: if identifiers already exist, the remote account was created earlier. Never create twice.
			if ( ! empty( $account->credentials ) || ! empty( $account->provider_user_id ) ) {
				self::update( $job->id, array( 'step' => 'verify' ) );
				$job->step = 'verify';
			} else {
				// Choose the username up front and persist it, so a lost response can be reconciled.
				if ( $can_find && 'mag' !== ( isset( $p['type'] ) ? $p['type'] : 'm3u' ) && empty( $p['username'] ) ) {
					$p['username'] = RSP_Megaott_Provider::generate_username();
					self::update( $job->id, array( 'payload' => wp_json_encode( $p ) ) );
				}
				self::update( $job->id, array( 'step' => 'send' ) );
				$r = $provider->create_account(
					array(
						'months'          => (int) $p['months'],
						'package'         => isset( $p['package'] ) ? $p['package'] : 'all',
						'template'        => isset( $p['template'] ) ? $p['template'] : '',
						'max_connections' => isset( $p['max_connections'] ) ? (int) $p['max_connections'] : 0,
						'type'            => isset( $p['type'] ) ? $p['type'] : 'm3u',
						'note'            => RSP_Settings::get( 'api_note_prefix', 'rsp' ) . '-' . $account->uuid,
						'mac'             => isset( $p['mac'] ) ? $p['mac'] : '',
						'username'        => isset( $p['username'] ) ? $p['username'] : '',
					)
				);
				self::update( $job->id, array( 'result' => wp_json_encode( $r->to_log() ) ) );
				if ( $r->ok() ) {
					self::adopt_created( $job, $account, $provider, $r );
					$job->step = 'verify';
				} elseif ( 'ambiguous' === $r->outcome ) {
					RSP_Audit::log( 'job_ambiguous', 'job', $job->id, '', $r->to_log(), 0 );
					if ( ! empty( $r->data['user_id'] ) ) {
						RSP_Accounts::update( $account->id, array( 'provider_user_id' => (string) $r->data['user_id'] ) );
					}
					if ( $can_find && ! empty( $p['username'] ) ) {
						// Reconcile on the next run (gives the panel a moment to settle).
						self::update( $job->id, array( 'state' => 'pending', 'step' => 'reconcile_create', 'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + 30 ), 'locked_by' => null, 'locked_at' => null, 'last_error' => substr( $r->error, 0, 500 ) ) );
						return;
					}
					self::to_review( $job, 'The panel may have created this account (' . $r->error . '). The API has no lookup without credentials, so confirm in the panel and complete manually.' );
					return;
				} else {
					self::retry_or_fail( $job, $r, 'start' );
					return;
				}
			}
		}

		if ( 'verify' === $job->step ) {
			$account = RSP_Accounts::get( $job->account_id );
			$lookup  = $provider->lookup_account( self::provider_creds( $account, $p ), $account->provider_type );
			if ( ! $lookup->ok() ) {
				self::retry_or_fail( $job, $lookup, 'verify' );
				return;
			}
			self::complete( $job, $account, $lookup->data['expire'], array( 'reconciled' => 'verified' ) );
		}
	}

	/**
	 * Store identifiers returned by a create (or found by reconciliation).
	 *
	 * @param object                 $job Job.
	 * @param object                 $account Account.
	 * @param RSP_Provider_Interface $provider Provider.
	 * @param RSP_Provider_Result    $r Result.
	 */
	private static function adopt_created( $job, $account, $provider, RSP_Provider_Result $r ) {
		RSP_Accounts::set_credentials( $account->id, $r->data['credentials'] );
		$upd = array( 'provider' => $provider->slug(), 'provider_user_id' => (string) $r->data['user_id'] );
		if ( isset( $r->data['max_connections'] ) && null !== $r->data['max_connections'] ) {
			$upd['max_connections'] = (int) $r->data['max_connections'];
		}
		RSP_Accounts::update( $account->id, $upd );
		self::update( $job->id, array( 'step' => 'verify' ) );
	}

	/**
	 * Decrypted credentials plus the provider identifiers adapters may need.
	 *
	 * @param object $account Account.
	 * @param array  $payload Job payload.
	 * @return array
	 */
	public static function provider_creds( $account, array $payload = array() ) {
		$c = RSP_Accounts::credentials( $account );
		if ( ! empty( $account->provider_user_id ) ) {
			$c['provider_user_id'] = (string) $account->provider_user_id;
		}
		$c['package'] = ! empty( $payload['package'] ) && 'all' !== $payload['package'] ? (string) $payload['package'] : (string) $account->package_id;
		return $c;
	}

	/**
	 * Renew / reward extension flow with before/after expiry reconciliation.
	 *
	 * @param object                 $job Job.
	 * @param RSP_Provider_Interface $provider Provider.
	 */
	private static function run_renew( $job, RSP_Provider_Interface $provider ) {
		$p       = self::payload( $job );
		$account = RSP_Accounts::get( $job->account_id );
		if ( ! $account || ( empty( $account->credentials ) && empty( $account->provider_user_id ) ) ) {
			self::to_review( $job, 'Account has no stored provider identifiers; renew manually.' );
			return;
		}
		if ( (int) $account->user_id !== (int) $job->user_id ) {
			self::to_review( $job, 'Ownership changed since purchase; staff must confirm the target account.' );
			return;
		}
		$creds = self::provider_creds( $account, $p );
		$type  = $account->provider_type;

		if ( 'start' === $job->step ) {
			$before = $provider->lookup_account( $creds, $type );
			if ( ! $before->ok() ) {
				self::retry_or_fail( $job, $before, 'start' );
				return;
			}
			self::update( $job->id, array( 'pre_expiry' => $before->data['expire'] . ' 00:00:00', 'step' => 'send' ) );
			$job->pre_expiry = $before->data['expire'] . ' 00:00:00';
			$r               = $provider->renew_account( $creds, (int) $p['months'], $type );
			self::update( $job->id, array( 'result' => wp_json_encode( $r->to_log() ) ) );
			if ( 'failed' === $r->outcome ) {
				self::retry_or_fail( $job, $r, 'start' );
				return;
			}
			// Success or ambiguous: both are confirmed by comparing expiry.
			$job->step = 'success' === $r->outcome ? 'verify' : 'reconcile';
			self::update( $job->id, array( 'step' => $job->step ) );
		}

		if ( in_array( $job->step, array( 'verify', 'reconcile', 'send' ), true ) ) {
			$after = $provider->lookup_account( $creds, $type );
			if ( ! $after->ok() ) {
				self::retry_or_fail( $job, $after, $job->step );
				return;
			}
			$pre = $job->pre_expiry ? substr( $job->pre_expiry, 0, 10 ) : '';
			if ( $pre && $after->data['expire'] > $pre ) {
				self::complete( $job, $account, $after->data['expire'], array( 'reconciled' => $job->step ) );
				return;
			}
			$why = 'verify' === $job->step
				? 'Panel reported renewal success but the expiry did not move.'
				: 'Request timed out; reconciliation shows the expiry unchanged. It is safe to retry once you confirm in the panel.';
			self::to_review( $job, $why );
		}
	}

	/**
	 * Mark success after the panel confirmed the expiry.
	 *
	 * @param object $job Job.
	 * @param object $account Account.
	 * @param string $expire Y-m-d.
	 * @param array  $extra Extra log.
	 * @param int    $actor Actor.
	 */
	public static function complete( $job, $account, $expire, array $extra = array(), $actor = 0 ) {
		$p      = self::payload( $job );
		$fields = array(
			'status'        => 'active',
			'expires_at'    => substr( $expire, 0, 10 ) . ' 23:59:59',
			'review_reason' => '',
		);
		if ( empty( $account->activated_at ) ) {
			$fields['activated_at'] = RSP_Installer::now();
		}
		if ( ! empty( $p['plan_label'] ) && 'reward_extend' !== $job->type ) {
			$fields['plan_label'] = $p['plan_label'];
		}
		if ( isset( $p['package'] ) && 'reward_extend' !== $job->type ) {
			$fields['package_id'] = (string) $p['package'];
		}
		if ( strtotime( $fields['expires_at'] . ' UTC' ) < time() ) {
			$fields['status'] = 'expired';
		}
		RSP_Accounts::update( $account->id, $fields );
		self::update(
			$job->id,
			array(
				'state'      => 'succeeded',
				'step'       => 'done',
				'last_error' => '',
				'locked_by'  => null,
				'locked_at'  => null,
				'result'     => wp_json_encode( array_merge( json_decode( (string) self::get( $job->id )->result, true ) ?: array(), array( 'confirmed_expiry' => substr( $expire, 0, 10 ) ), $extra ) ),
			)
		);
		RSP_Audit::log( 'job_succeeded', 'job', $job->id, '', array( 'account' => $account->uuid, 'expiry' => substr( $expire, 0, 10 ) ), $actor );
		do_action( 'rsp_job_succeeded', self::get( $job->id ), RSP_Accounts::get( $account->id ) );
	}

	/**
	 * Staff action: retry a failed / review job. For a create that may have succeeded, staff must confirm.
	 *
	 * @param int    $id Job.
	 * @param string $reason Reason.
	 * @param bool   $confirmed_not_created Staff confirmed the panel has no such account.
	 * @return true|WP_Error
	 */
	public static function staff_retry( $id, $reason, $confirmed_not_created = false ) {
		$job = self::get( $id );
		if ( ! $job || ! in_array( $job->state, array( 'failed', 'needs_review' ), true ) ) {
			return new WP_Error( 'rsp_state', __( 'Only failed or review jobs can be retried.', 'relay-stream-portal' ) );
		}
		if ( ! RSP_Provider_Factory::is_live() ) {
			return new WP_Error( 'rsp_manual', __( 'Live mode is off. Complete this job manually.', 'relay-stream-portal' ) );
		}
		$step = $job->step;
		$pl = self::payload( $job );
		if ( 'create' === $job->type && in_array( $step, array( 'send', 'reconcile_create' ), true ) && ! empty( $pl['username'] ) && method_exists( RSP_Provider_Factory::get(), 'find_created' ) ) {
			$step                  = 'reconcile_create';
			$confirmed_not_created = true; // The adapter verifies by lookup before any new create.
		}
		if ( 'create' === $job->type && in_array( $step, array( 'send', 'start' ), true ) ) {
			$acc = RSP_Accounts::get( $job->account_id );
			if ( $acc && empty( $acc->credentials ) && 'send' === $step && ! $confirmed_not_created ) {
				return new WP_Error( 'rsp_confirm', __( 'Confirm in the panel that no account was created before retrying a create.', 'relay-stream-portal' ) );
			}
			$step = 'start';
		}
		if ( in_array( $job->type, array( 'renew', 'reward_extend' ), true ) ) {
			$step = 'start';
		}
		self::update( $id, array( 'state' => 'pending', 'step' => $step, 'attempts' => 0, 'next_attempt_at' => RSP_Installer::now(), 'review_reason' => '' ) );
		if ( $job->account_id ) {
			$acc = RSP_Accounts::get( $job->account_id );
			if ( $acc && 'review' === $acc->status ) {
				RSP_Accounts::update( $acc->id, array( 'status' => 'pending_activation', 'review_reason' => '' ) );
			}
		}
		RSP_Audit::log( 'job_retry', 'job', $id, $reason, array( 'step' => $step, 'confirmed_not_created' => $confirmed_not_created ) );
		return true;
	}

	/**
	 * Staff action: reconcile a renewal by reading the current expiry.
	 *
	 * @param int    $id Job.
	 * @param string $reason Reason.
	 * @return true|WP_Error
	 */
	public static function staff_reconcile( $id, $reason ) {
		$job = self::get( $id );
		if ( ! $job || ! RSP_Provider_Factory::is_live() || ! $job->account_id ) {
			return new WP_Error( 'rsp_reconcile', __( 'Reconciliation needs live mode and a linked account.', 'relay-stream-portal' ) );
		}
		$acc   = RSP_Accounts::get( $job->account_id );
		$creds = self::provider_creds( $acc, self::payload( $job ) );
		if ( empty( $creds['username'] ) && empty( $creds['mac'] ) && empty( $creds['provider_user_id'] ) ) {
			return new WP_Error( 'rsp_reconcile', __( 'No stored provider identifiers to look up.', 'relay-stream-portal' ) );
		}
		$r = RSP_Provider_Factory::get()->lookup_account( $creds, $acc->provider_type );
		RSP_Audit::log( 'job_reconcile', 'job', $id, $reason, $r->to_log() );
		if ( ! $r->ok() ) {
			return new WP_Error( 'rsp_reconcile', $r->error );
		}
		$pre = $job->pre_expiry ? substr( $job->pre_expiry, 0, 10 ) : '';
		if ( 'create' === $job->type || ( $pre && $r->data['expire'] > $pre ) ) {
			self::complete( $job, $acc, $r->data['expire'], array( 'reconciled' => 'staff' ), get_current_user_id() );
			return true;
		}
		/* translators: %s: date */
		return new WP_Error( 'rsp_unchanged', sprintf( __( 'Expiry is still %s; the renewal was not applied. You may retry.', 'relay-stream-portal' ), $r->data['expire'] ) );
	}

	/**
	 * Staff action: complete manually with confirmed details.
	 *
	 * @param int    $id Job.
	 * @param array  $fields username,password,url,mac,server,expiry(Y-m-d),max_connections,provider_user_id.
	 * @param string $reason Reason.
	 * @return true|WP_Error
	 */
	public static function manual_complete( $id, array $fields, $reason ) {
		$job = self::get( $id );
		if ( ! $job || 'succeeded' === $job->state ) {
			return new WP_Error( 'rsp_state', __( 'Job not found or already completed.', 'relay-stream-portal' ) );
		}
		if ( empty( $fields['expiry'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fields['expiry'] ) ) {
			return new WP_Error( 'rsp_expiry', __( 'Enter the confirmed expiry date (YYYY-MM-DD).', 'relay-stream-portal' ) );
		}
		if ( '' === trim( (string) $reason ) ) {
			return new WP_Error( 'rsp_reason', __( 'A reason is required for manual completion.', 'relay-stream-portal' ) );
		}
		$acc = RSP_Accounts::get( $job->account_id );
		if ( ! $acc ) {
			return new WP_Error( 'rsp_account', __( 'Linked account not found.', 'relay-stream-portal' ) );
		}
		$creds = array_filter( array_intersect_key( $fields, array_flip( array( 'username', 'password', 'url', 'mac', 'server' ) ) ), 'strlen' );
		if ( $creds ) {
			RSP_Accounts::set_credentials( $acc->id, array_merge( RSP_Accounts::credentials( $acc ), $creds ) );
		}
		$upd = array();
		if ( isset( $fields['max_connections'] ) && '' !== $fields['max_connections'] ) {
			$upd['max_connections'] = max( 0, (int) $fields['max_connections'] );
		}
		if ( ! empty( $fields['provider_user_id'] ) ) {
			$upd['provider_user_id'] = sanitize_text_field( $fields['provider_user_id'] );
		}
		if ( $upd ) {
			RSP_Accounts::update( $acc->id, $upd );
		}
		RSP_Audit::log( 'manual_completion', 'job', $id, $reason, array( 'account' => $acc->uuid, 'expiry' => $fields['expiry'], 'fields_set' => array_keys( $creds ) ) );
		self::complete( $job, RSP_Accounts::get( $acc->id ), $fields['expiry'], array( 'reconciled' => 'manual' ), get_current_user_id() );
		return true;
	}

	/**
	 * Staff action: cancel/fail a job.
	 *
	 * @param int    $id Job.
	 * @param string $reason Reason.
	 * @param int    $actor Actor.
	 */
	public static function cancel( $id, $reason, $actor = null ) {
		$job = self::get( $id );
		if ( ! $job || in_array( $job->state, array( 'succeeded', 'working' ), true ) ) {
			return;
		}
		self::update( $id, array( 'state' => 'failed', 'last_error' => substr( 'Cancelled: ' . $reason, 0, 500 ) ) );
		RSP_Audit::log( 'job_cancelled', 'job', $id, $reason, array(), $actor );
		do_action( 'rsp_job_cancelled', self::get( $id ) );
	}
}
