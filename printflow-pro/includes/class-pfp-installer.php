<?php
/**
 * Database installer for PrintFlow Pro.
 *
 * Creates all custom database tables needed by the plugin.
 *
 * @package PrintFlowPro
 */

defined( 'ABSPATH' ) || exit;

class PFP_Installer {

	/**
	 * Install database tables and default data.
	 */
	public static function install() {
		self::create_tables();
		self::create_default_data();
	}

	/**
	 * Create all custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// --- Material Categories ---
		$sql = "CREATE TABLE {$prefix}pfp_material_categories (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			parent_id bigint(20) UNSIGNED DEFAULT 0,
			description text,
			PRIMARY KEY (id),
			KEY slug (slug),
			KEY parent_id (parent_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Materials ---
		$sql = "CREATE TABLE {$prefix}pfp_materials (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			code varchar(50) NOT NULL,
			category_id bigint(20) UNSIGNED DEFAULT 0,
			unit varchar(50) NOT NULL DEFAULT 'pièce',
			quantity decimal(12,2) NOT NULL DEFAULT 0,
			min_alert_qty decimal(12,2) NOT NULL DEFAULT 0,
			purchase_cost decimal(12,2) NOT NULL DEFAULT 0,
			supplier_id bigint(20) UNSIGNED DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY code (code),
			KEY category_id (category_id),
			KEY supplier_id (supplier_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Stock Movements ---
		$sql = "CREATE TABLE {$prefix}pfp_stock_movements (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			material_id bigint(20) UNSIGNED NOT NULL,
			type varchar(20) NOT NULL,
			quantity decimal(12,2) NOT NULL,
			reference_type varchar(50) DEFAULT '',
			reference_id bigint(20) UNSIGNED DEFAULT 0,
			reason text,
			user_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY material_id (material_id),
			KEY type (type),
			KEY reference_type_id (reference_type, reference_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Material-Product Mapping ---
		$sql = "CREATE TABLE {$prefix}pfp_material_product_map (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			material_id bigint(20) UNSIGNED NOT NULL,
			product_id bigint(20) UNSIGNED NOT NULL,
			quantity_per_unit decimal(12,4) NOT NULL DEFAULT 1,
			unit varchar(50) NOT NULL DEFAULT 'pièce',
			PRIMARY KEY (id),
			KEY material_id (material_id),
			KEY product_id (product_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Suppliers ---
		$sql = "CREATE TABLE {$prefix}pfp_suppliers (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			company varchar(255) DEFAULT '',
			phone varchar(50) DEFAULT '',
			email varchar(255) DEFAULT '',
			city varchar(100) DEFAULT '',
			address text,
			relationship_type varchar(50) NOT NULL DEFAULT 'supplier',
			payment_terms text,
			performance_rating decimal(3,1) DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status),
			KEY relationship_type (relationship_type)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Purchase Orders ---
		$sql = "CREATE TABLE {$prefix}pfp_purchase_orders (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			supplier_id bigint(20) UNSIGNED NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'draft',
			total_amount decimal(12,2) NOT NULL DEFAULT 0,
			notes text,
			ordered_at datetime DEFAULT NULL,
			received_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY supplier_id (supplier_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Purchase Order Items ---
		$sql = "CREATE TABLE {$prefix}pfp_purchase_order_items (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			purchase_order_id bigint(20) UNSIGNED NOT NULL,
			material_id bigint(20) UNSIGNED NOT NULL,
			quantity decimal(12,2) NOT NULL,
			unit_price decimal(12,2) NOT NULL,
			total_price decimal(12,2) NOT NULL,
			PRIMARY KEY (id),
			KEY purchase_order_id (purchase_order_id),
			KEY material_id (material_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Supplier Payments ---
		$sql = "CREATE TABLE {$prefix}pfp_supplier_payments (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			supplier_id bigint(20) UNSIGNED NOT NULL,
			purchase_order_id bigint(20) UNSIGNED DEFAULT 0,
			amount decimal(12,2) NOT NULL,
			method varchar(50) NOT NULL DEFAULT 'cash',
			reference varchar(255) DEFAULT '',
			paid_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY supplier_id (supplier_id),
			KEY purchase_order_id (purchase_order_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Distributors ---
		$sql = "CREATE TABLE {$prefix}pfp_distributors (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			company varchar(255) DEFAULT '',
			phone varchar(50) DEFAULT '',
			email varchar(255) DEFAULT '',
			city varchar(100) DEFAULT '',
			address text,
			territory text,
			commission_rate decimal(5,2) DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Pricing Rules ---
		$sql = "CREATE TABLE {$prefix}pfp_pricing_rules (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			product_category varchar(100) DEFAULT '',
			rule_type varchar(50) NOT NULL,
			conditions longtext,
			multiplier decimal(8,4) DEFAULT 1,
			fixed_amount decimal(12,2) DEFAULT 0,
			priority int(11) NOT NULL DEFAULT 10,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY rule_type (rule_type),
			KEY status (status),
			KEY priority (priority)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Pricing Tiers ---
		$sql = "CREATE TABLE {$prefix}pfp_pricing_tiers (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			rule_id bigint(20) UNSIGNED NOT NULL,
			min_qty int(11) NOT NULL DEFAULT 1,
			max_qty int(11) NOT NULL DEFAULT 9999999,
			discount_percentage decimal(5,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY rule_id (rule_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Pricing Modifiers ---
		$sql = "CREATE TABLE {$prefix}pfp_pricing_modifiers (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			type varchar(50) NOT NULL,
			option_value varchar(255) NOT NULL,
			modifier_type varchar(20) NOT NULL DEFAULT 'multiplier',
			modifier_value decimal(12,4) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY type (type)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Quotes ---
		$sql = "CREATE TABLE {$prefix}pfp_quotes (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			quote_number varchar(50) NOT NULL,
			customer_id bigint(20) UNSIGNED DEFAULT 0,
			customer_name varchar(255) DEFAULT '',
			customer_email varchar(255) DEFAULT '',
			customer_phone varchar(50) DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'nouveau',
			total_amount decimal(12,2) NOT NULL DEFAULT 0,
			valid_until date DEFAULT NULL,
			notes text,
			converted_order_id bigint(20) UNSIGNED DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY quote_number (quote_number),
			KEY customer_id (customer_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Quote Items ---
		$sql = "CREATE TABLE {$prefix}pfp_quote_items (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			quote_id bigint(20) UNSIGNED NOT NULL,
			product_id bigint(20) UNSIGNED DEFAULT 0,
			description text NOT NULL,
			quantity int(11) NOT NULL DEFAULT 1,
			unit_price decimal(12,2) NOT NULL DEFAULT 0,
			total_price decimal(12,2) NOT NULL DEFAULT 0,
			specifications longtext,
			PRIMARY KEY (id),
			KEY quote_id (quote_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Quote History ---
		$sql = "CREATE TABLE {$prefix}pfp_quote_history (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			quote_id bigint(20) UNSIGNED NOT NULL,
			from_status varchar(30) DEFAULT '',
			to_status varchar(30) NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY quote_id (quote_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Production Jobs ---
		$sql = "CREATE TABLE {$prefix}pfp_production_jobs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			order_item_id bigint(20) UNSIGNED DEFAULT 0,
			status varchar(50) NOT NULL DEFAULT 'nouveau',
			assigned_to bigint(20) UNSIGNED DEFAULT 0,
			machine varchar(255) DEFAULT '',
			priority varchar(20) NOT NULL DEFAULT 'normal',
			estimated_time int(11) DEFAULT 0,
			actual_time int(11) DEFAULT 0,
			technical_notes text,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY status (status),
			KEY assigned_to (assigned_to),
			KEY priority (priority)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Production Checklists ---
		$sql = "CREATE TABLE {$prefix}pfp_production_checklists (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id bigint(20) UNSIGNED NOT NULL,
			item_text varchar(255) NOT NULL,
			is_checked tinyint(1) NOT NULL DEFAULT 0,
			checked_by bigint(20) UNSIGNED DEFAULT 0,
			checked_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY job_id (job_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Production Logs ---
		$sql = "CREATE TABLE {$prefix}pfp_production_logs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id bigint(20) UNSIGNED NOT NULL,
			from_status varchar(50) DEFAULT '',
			to_status varchar(50) NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Artwork Files ---
		$sql = "CREATE TABLE {$prefix}pfp_artwork_files (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED DEFAULT 0,
			order_item_id bigint(20) UNSIGNED DEFAULT 0,
			customer_id bigint(20) UNSIGNED DEFAULT 0,
			file_path text NOT NULL,
			original_filename varchar(255) NOT NULL,
			file_type varchar(20) NOT NULL,
			file_size bigint(20) UNSIGNED DEFAULT 0,
			version int(11) NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'pending',
			reviewed_by bigint(20) UNSIGNED DEFAULT 0,
			reviewed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Artwork Comments ---
		$sql = "CREATE TABLE {$prefix}pfp_artwork_comments (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			artwork_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			comment text NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY artwork_id (artwork_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Income ---
		$sql = "CREATE TABLE {$prefix}pfp_income (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED DEFAULT 0,
			amount decimal(12,2) NOT NULL,
			payment_method varchar(50) NOT NULL DEFAULT 'cash',
			reference varchar(255) DEFAULT '',
			category varchar(100) DEFAULT 'order',
			notes text,
			received_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY category (category),
			KEY received_at (received_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Expense Categories ---
		$sql = "CREATE TABLE {$prefix}pfp_expense_categories (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			parent_id bigint(20) UNSIGNED DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Expenses ---
		$sql = "CREATE TABLE {$prefix}pfp_expenses (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id bigint(20) UNSIGNED DEFAULT 0,
			amount decimal(12,2) NOT NULL,
			description text NOT NULL,
			payment_method varchar(50) NOT NULL DEFAULT 'cash',
			reference varchar(255) DEFAULT '',
			receipt_file varchar(500) DEFAULT '',
			expense_date date NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY category_id (category_id),
			KEY expense_date (expense_date)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Invoices ---
		$sql = "CREATE TABLE {$prefix}pfp_invoices (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED DEFAULT 0,
			customer_id bigint(20) UNSIGNED DEFAULT 0,
			invoice_number varchar(50) NOT NULL,
			total_amount decimal(12,2) NOT NULL DEFAULT 0,
			tax_amount decimal(12,2) NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'draft',
			due_date date DEFAULT NULL,
			paid_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY invoice_number (invoice_number),
			KEY order_id (order_id),
			KEY customer_id (customer_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Invoice Items ---
		$sql = "CREATE TABLE {$prefix}pfp_invoice_items (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_id bigint(20) UNSIGNED NOT NULL,
			description text NOT NULL,
			quantity int(11) NOT NULL DEFAULT 1,
			unit_price decimal(12,2) NOT NULL DEFAULT 0,
			total_price decimal(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY invoice_id (invoice_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Payments ---
		$sql = "CREATE TABLE {$prefix}pfp_payments (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_id bigint(20) UNSIGNED NOT NULL,
			amount decimal(12,2) NOT NULL,
			method varchar(50) NOT NULL DEFAULT 'cash',
			reference varchar(255) DEFAULT '',
			paid_at datetime NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY invoice_id (invoice_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Customer Notes ---
		$sql = "CREATE TABLE {$prefix}pfp_customer_notes (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			note text NOT NULL,
			type varchar(30) NOT NULL DEFAULT 'note',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY customer_id (customer_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Loyalty Points ---
		$sql = "CREATE TABLE {$prefix}pfp_loyalty_points (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) UNSIGNED NOT NULL,
			points int(11) NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'earned',
			reference varchar(255) DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY customer_id (customer_id),
			KEY type (type)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Delivery Zones ---
		$sql = "CREATE TABLE {$prefix}pfp_delivery_zones (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			city varchar(100) NOT NULL,
			region varchar(100) DEFAULT '',
			base_cost decimal(12,2) NOT NULL DEFAULT 0,
			estimated_days int(11) NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'active',
			PRIMARY KEY (id),
			KEY city (city),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Deliveries ---
		$sql = "CREATE TABLE {$prefix}pfp_deliveries (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			assigned_to bigint(20) UNSIGNED DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'pending',
			delivery_zone_id bigint(20) UNSIGNED DEFAULT 0,
			tracking_ref varchar(255) DEFAULT '',
			delivery_cost decimal(12,2) NOT NULL DEFAULT 0,
			notes text,
			proof_photo varchar(500) DEFAULT '',
			scheduled_at datetime DEFAULT NULL,
			delivered_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY assigned_to (assigned_to),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Delivery Logs ---
		$sql = "CREATE TABLE {$prefix}pfp_delivery_logs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			delivery_id bigint(20) UNSIGNED NOT NULL,
			from_status varchar(30) DEFAULT '',
			to_status varchar(30) NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY delivery_id (delivery_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Notification Templates ---
		$sql = "CREATE TABLE {$prefix}pfp_notification_templates (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type varchar(100) NOT NULL,
			channel varchar(20) NOT NULL DEFAULT 'email',
			subject varchar(255) NOT NULL DEFAULT '',
			body longtext NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY event_type (event_type),
			KEY channel (channel)
		) {$charset_collate};";
		dbDelta( $sql );

		// --- Notification Log ---
		$sql = "CREATE TABLE {$prefix}pfp_notification_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			template_id bigint(20) UNSIGNED DEFAULT 0,
			recipient varchar(255) NOT NULL,
			channel varchar(20) NOT NULL DEFAULT 'email',
			status varchar(20) NOT NULL DEFAULT 'sent',
			sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			error_message text,
			PRIMARY KEY (id),
			KEY status (status),
			KEY sent_at (sent_at)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	/**
	 * Create default data (expense categories, delivery zones, etc.).
	 */
	private static function create_default_data() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		// Default expense categories.
		$categories = array(
			array( 'name' => 'Matières premières', 'slug' => 'matieres-premieres' ),
			array( 'name' => 'Frais de livraison', 'slug' => 'frais-livraison' ),
			array( 'name' => 'Salaires', 'slug' => 'salaires' ),
			array( 'name' => 'Loyer', 'slug' => 'loyer' ),
			array( 'name' => 'Électricité', 'slug' => 'electricite' ),
			array( 'name' => 'Eau', 'slug' => 'eau' ),
			array( 'name' => 'Internet et téléphone', 'slug' => 'internet-telephone' ),
			array( 'name' => 'Maintenance équipement', 'slug' => 'maintenance-equipement' ),
			array( 'name' => 'Marketing', 'slug' => 'marketing' ),
			array( 'name' => 'Fournitures bureau', 'slug' => 'fournitures-bureau' ),
			array( 'name' => 'Impôts et taxes', 'slug' => 'impots-taxes' ),
			array( 'name' => 'Assurances', 'slug' => 'assurances' ),
			array( 'name' => 'Divers', 'slug' => 'divers' ),
		);

		foreach ( $categories as $cat ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$prefix}pfp_expense_categories WHERE slug = %s",
					$cat['slug']
				)
			);
			if ( ! $exists ) {
				$wpdb->insert( "{$prefix}pfp_expense_categories", $cat );
			}
		}

		// Default delivery zones (Morocco cities).
		$zones = array(
			array( 'name' => 'Casablanca', 'city' => 'Casablanca', 'region' => 'Casablanca-Settat', 'base_cost' => 0, 'estimated_days' => 1 ),
			array( 'name' => 'Rabat', 'city' => 'Rabat', 'region' => 'Rabat-Salé-Kénitra', 'base_cost' => 30, 'estimated_days' => 1 ),
			array( 'name' => 'Marrakech', 'city' => 'Marrakech', 'region' => 'Marrakech-Safi', 'base_cost' => 40, 'estimated_days' => 2 ),
			array( 'name' => 'Fès', 'city' => 'Fès', 'region' => 'Fès-Meknès', 'base_cost' => 40, 'estimated_days' => 2 ),
			array( 'name' => 'Tanger', 'city' => 'Tanger', 'region' => 'Tanger-Tétouan-Al Hoceïma', 'base_cost' => 45, 'estimated_days' => 2 ),
			array( 'name' => 'Agadir', 'city' => 'Agadir', 'region' => 'Souss-Massa', 'base_cost' => 50, 'estimated_days' => 3 ),
			array( 'name' => 'Meknès', 'city' => 'Meknès', 'region' => 'Fès-Meknès', 'base_cost' => 40, 'estimated_days' => 2 ),
			array( 'name' => 'Oujda', 'city' => 'Oujda', 'region' => 'Oriental', 'base_cost' => 55, 'estimated_days' => 3 ),
			array( 'name' => 'Kénitra', 'city' => 'Kénitra', 'region' => 'Rabat-Salé-Kénitra', 'base_cost' => 35, 'estimated_days' => 1 ),
			array( 'name' => 'Tétouan', 'city' => 'Tétouan', 'region' => 'Tanger-Tétouan-Al Hoceïma', 'base_cost' => 45, 'estimated_days' => 2 ),
			array( 'name' => 'Salé', 'city' => 'Salé', 'region' => 'Rabat-Salé-Kénitra', 'base_cost' => 30, 'estimated_days' => 1 ),
			array( 'name' => 'Mohammedia', 'city' => 'Mohammedia', 'region' => 'Casablanca-Settat', 'base_cost' => 20, 'estimated_days' => 1 ),
			array( 'name' => 'El Jadida', 'city' => 'El Jadida', 'region' => 'Casablanca-Settat', 'base_cost' => 35, 'estimated_days' => 2 ),
			array( 'name' => 'Béni Mellal', 'city' => 'Béni Mellal', 'region' => 'Béni Mellal-Khénifra', 'base_cost' => 45, 'estimated_days' => 2 ),
			array( 'name' => 'Nador', 'city' => 'Nador', 'region' => 'Oriental', 'base_cost' => 55, 'estimated_days' => 3 ),
			array( 'name' => 'Settat', 'city' => 'Settat', 'region' => 'Casablanca-Settat', 'base_cost' => 30, 'estimated_days' => 1 ),
			array( 'name' => 'Autres villes', 'city' => 'Autre', 'region' => 'Autre', 'base_cost' => 60, 'estimated_days' => 3 ),
		);

		foreach ( $zones as $zone ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$prefix}pfp_delivery_zones WHERE city = %s",
					$zone['city']
				)
			);
			if ( ! $exists ) {
				$wpdb->insert( "{$prefix}pfp_delivery_zones", $zone );
			}
		}

		// Default notification templates.
		$templates = array(
			array(
				'event_type' => 'order_confirmed',
				'channel'    => 'email',
				'subject'    => 'Confirmation de votre commande #{order_number}',
				'body'       => "Bonjour {customer_name},\n\nMerci pour votre commande #{order_number}.\n\nNous avons bien reçu votre commande et elle est en cours de traitement.\n\nVous pouvez suivre l'état de votre commande depuis votre espace client.\n\nCordialement,\n{business_name}",
			),
			array(
				'event_type' => 'file_approved',
				'channel'    => 'email',
				'subject'    => 'Votre fichier a été validé - Commande #{order_number}',
				'body'       => "Bonjour {customer_name},\n\nVotre fichier pour la commande #{order_number} a été validé par notre équipe.\n\nVotre commande est maintenant en cours de production.\n\nCordialement,\n{business_name}",
			),
			array(
				'event_type' => 'order_ready',
				'channel'    => 'email',
				'subject'    => 'Votre commande #{order_number} est prête',
				'body'       => "Bonjour {customer_name},\n\nVotre commande #{order_number} est prête pour la livraison.\n\nNotre équipe de livraison vous contactera prochainement.\n\nCordialement,\n{business_name}",
			),
			array(
				'event_type' => 'order_delivered',
				'channel'    => 'email',
				'subject'    => 'Votre commande #{order_number} a été livrée',
				'body'       => "Bonjour {customer_name},\n\nVotre commande #{order_number} a été livrée avec succès.\n\nNous espérons que vous êtes satisfait(e) de nos services.\n\nN'hésitez pas à nous contacter pour toute question.\n\nCordialement,\n{business_name}",
			),
			array(
				'event_type' => 'low_stock_alert',
				'channel'    => 'email',
				'subject'    => 'Alerte stock bas - {material_name}',
				'body'       => "Attention,\n\nLe stock de {material_name} (Code: {material_code}) est bas.\n\nQuantité actuelle: {current_qty} {unit}\nSeuil minimum: {min_qty} {unit}\n\nVeuillez passer une commande auprès de votre fournisseur.\n\nPrintFlow Pro",
			),
			array(
				'event_type' => 'quote_sent',
				'channel'    => 'email',
				'subject'    => 'Votre devis #{quote_number}',
				'body'       => "Bonjour {customer_name},\n\nVeuillez trouver ci-joint votre devis #{quote_number}.\n\nCe devis est valable jusqu'au {valid_until}.\n\nN'hésitez pas à nous contacter pour toute question.\n\nCordialement,\n{business_name}",
			),
		);

		foreach ( $templates as $template ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$prefix}pfp_notification_templates WHERE event_type = %s AND channel = %s",
					$template['event_type'],
					$template['channel']
				)
			);
			if ( ! $exists ) {
				$wpdb->insert( "{$prefix}pfp_notification_templates", $template );
			}
		}
	}
}
