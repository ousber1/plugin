<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database handler for Print Manager Pro.
 */
class PMP_Database {

    /**
     * Create all custom database tables.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Expenses table
        $table_expenses = $wpdb->prefix . 'print_expenses';
        $sql_expenses = "CREATE TABLE {$table_expenses} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            category varchar(100) NOT NULL DEFAULT '',
            description text NOT NULL,
            amount decimal(12,2) NOT NULL DEFAULT 0.00,
            expense_date date NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY expense_date (expense_date)
        ) {$charset_collate};";
        dbDelta( $sql_expenses );

        // Machines table
        $table_machines = $wpdb->prefix . 'print_machines';
        $sql_machines = "CREATE TABLE {$table_machines} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            machine_name varchar(255) NOT NULL DEFAULT '',
            cost_per_hour decimal(10,2) NOT NULL DEFAULT 0.00,
            maintenance_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            status varchar(50) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta( $sql_machines );

        // Cost settings table
        $table_cost = $wpdb->prefix . 'print_cost_settings';
        $sql_cost = "CREATE TABLE {$table_cost} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL DEFAULT '',
            setting_value varchar(255) NOT NULL DEFAULT '',
            description varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) {$charset_collate};";
        dbDelta( $sql_cost );

        // Print orders tracking table
        $table_orders = $wpdb->prefix . 'print_orders';
        $sql_orders = "CREATE TABLE {$table_orders} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            configuration text NOT NULL,
            design_id bigint(20) unsigned DEFAULT NULL,
            file_path varchar(500) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) {$charset_collate};";
        dbDelta( $sql_orders );

        // Designs table
        $table_designs = $wpdb->prefix . 'print_designs';
        $sql_designs = "CREATE TABLE {$table_designs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            product_id bigint(20) unsigned DEFAULT NULL,
            design_json longtext NOT NULL,
            preview_url varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY product_id (product_id)
        ) {$charset_collate};";
        dbDelta( $sql_designs );

        // Suppliers table
        $table_suppliers = $wpdb->prefix . 'print_suppliers';
        $sql_suppliers = "CREATE TABLE {$table_suppliers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            address text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta( $sql_suppliers );

        // Clients table
        $table_clients = $wpdb->prefix . 'print_clients';
        $sql_clients = "CREATE TABLE {$table_clients} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            company varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            address text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) {$charset_collate};";
        dbDelta( $sql_clients );

        // Quotes table
        $table_quotes = $wpdb->prefix . 'print_quotes';
        $sql_quotes = "CREATE TABLE {$table_quotes} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned DEFAULT NULL,
            quote_data longtext NOT NULL,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            status varchar(50) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) {$charset_collate};";
        dbDelta( $sql_quotes );

        // Workflow states table
        $table_workflow = $wpdb->prefix . 'print_workflow';
        $sql_workflow = "CREATE TABLE {$table_workflow} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            slug varchar(255) NOT NULL DEFAULT '',
            description text DEFAULT NULL,
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";
        dbDelta( $sql_workflow );

        update_option( 'pmp_db_version', PMP_VERSION );
    }

    /**
     * Drop all custom tables (used on uninstall).
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'print_expenses',
            $wpdb->prefix . 'print_machines',
            $wpdb->prefix . 'print_cost_settings',
            $wpdb->prefix . 'print_orders',
            $wpdb->prefix . 'print_designs',
            $wpdb->prefix . 'print_suppliers',
            $wpdb->prefix . 'print_clients',
            $wpdb->prefix . 'print_quotes',
            $wpdb->prefix . 'print_workflow',
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }

        delete_option( 'pmp_db_version' );
    }
}
