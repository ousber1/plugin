<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Online Design Tool handler for Print Manager Pro.
 */
class PMP_Designer {

    /**
     * Save a design to the database.
     *
     * @param string $design_json JSON string of the Fabric.js canvas.
     * @param int    $product_id  Product ID.
     * @return int|false Design ID on success, false on failure.
     */
    public function save_design( $design_json, $product_id = 0 ) {
        global $wpdb;

        $user_id = get_current_user_id();

        // Validate JSON
        $decoded = json_decode( $design_json );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return false;
        }

        // Generate preview image from design data
        $preview_url = $this->generate_preview( $design_json );

        $table = $wpdb->prefix . 'print_designs';

        $result = $wpdb->insert( $table, array(
            'user_id'    => $user_id > 0 ? $user_id : null,
            'product_id' => $product_id > 0 ? $product_id : null,
            'design_json' => $design_json,
            'preview_url' => $preview_url,
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ) );

        if ( false === $result ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get a design by ID.
     *
     * @param int $design_id Design ID.
     * @return object|null Design object.
     */
    public function get_design( $design_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'print_designs';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $design_id
        ) );
    }

    /**
     * Get all designs for a user.
     *
     * @param int $user_id User ID.
     * @return array Array of design objects.
     */
    public function get_user_designs( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'print_designs';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );
    }

    /**
     * Update a design.
     *
     * @param int    $design_id   Design ID.
     * @param string $design_json Updated JSON.
     * @return bool Success.
     */
    public function update_design( $design_id, $design_json ) {
        global $wpdb;

        $decoded = json_decode( $design_json );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return false;
        }

        $table = $wpdb->prefix . 'print_designs';

        $result = $wpdb->update(
            $table,
            array(
                'design_json' => $design_json,
                'updated_at'  => current_time( 'mysql' ),
            ),
            array( 'id' => $design_id )
        );

        return false !== $result;
    }

    /**
     * Delete a design.
     *
     * @param int $design_id Design ID.
     * @return bool Success.
     */
    public function delete_design( $design_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'print_designs';
        return false !== $wpdb->delete( $table, array( 'id' => $design_id ) );
    }

    /**
     * Generate a preview placeholder URL.
     * The actual preview is generated client-side via Canvas.toDataURL().
     *
     * @param string $design_json Design JSON data.
     * @return string Preview URL or empty string.
     */
    private function generate_preview( $design_json ) {
        // Preview is generated client-side and can be saved separately
        // This returns empty; the client sends preview data separately if needed
        return '';
    }
}
