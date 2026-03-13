<?php
/**
 * Singleton trait for PrintFlow Pro classes.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

trait PFP_Singleton {

	/**
	 * Singleton instance.
	 *
	 * @var static|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return static
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
