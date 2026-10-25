<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
delete_option( 'wmsf_settings' );
wp_clear_scheduled_hook( 'wmsf_check_missed' );
