<?php
/**
 * Plugin Name:       Missed Schedule Fixer
 * Description:       Publishes WordPress posts that missed their scheduled time (a common WP-Cron failure) instead of leaving them stuck as "Missed schedule".
 * Version:           1.0.0
 * Author:            Azan Umer
 * License:           MIT
 * Text Domain:       wmsf
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WMSF_OPTION', 'wmsf_settings' );

/** Default settings. Filterable via `wmsf_default_settings`. */
function wmsf_defaults() {
    return apply_filters( 'wmsf_default_settings', array(
        'grace_minutes' => 15,   // Only publish posts this far past their time.
        'lookback_days' => 7,    // Ignore posts scheduled longer ago than this.
        'notify_email'  => '',   // Optional: email a summary when posts are recovered.
    ) );
}

function wmsf_settings() {
    return wp_parse_args( get_option( WMSF_OPTION, array() ), wmsf_defaults() );
}

/** Register the hourly check (WP-Cron). */
function wmsf_activate() {
    if ( ! wp_next_scheduled( 'wmsf_check_missed' ) ) {
        wp_schedule_event( time(), 'hourly', 'wmsf_check_missed' );
    }
}
register_activation_hook( __FILE__, 'wmsf_activate' );

function wmsf_deactivate() {
    wp_clear_scheduled_hook( 'wmsf_check_missed' );
}
register_deactivation_hook( __FILE__, 'wmsf_deactivate' );

/**
 * Find and publish missed scheduled posts.
 *
 * A post counts as missed when its GMT date is older than now minus the
 * grace period, and not older than the lookback window. Returns the
 * number of posts published.
 */
function wmsf_check_missed() {
    $settings = wmsf_settings();
    $grace    = max( 0, (int) $settings['grace_minutes'] );
    $lookback = max( 1, (int) $settings['lookback_days'] );

    $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $grace * MINUTE_IN_SECONDS ) );
    $oldest = gmdate( 'Y-m-d H:i:s', time() - ( $lookback * DAY_IN_SECONDS ) );

    $missed = get_posts( array(
        'post_type'      => 'any',
        'post_status'    => 'future',
        'posts_per_page' => 100,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'date_query'     => array(
            array(
                'column' => 'post_date_gmt',
                'before' => $cutoff,
                'after'  => $oldest,
            ),
        ),
    ) );

    $published = 0;
    $titles    = array();
    foreach ( $missed as $post ) {
        // Double-check status in case another process already handled it.
        if ( 'future' !== get_post_status( $post->ID ) ) {
            continue;
        }
        wp_publish_post( $post->ID );
        $published++;
        $titles[] = get_the_title( $post->ID );
    }

    if ( $published > 0 && ! empty( $settings['notify_email'] ) && is_email( $settings['notify_email'] ) ) {
        wp_mail(
            $settings['notify_email'],
            sprintf( '[%s] %d missed post(s) published', get_bloginfo( 'name' ), $published ),
            "The following scheduled posts missed their time and were published:\n\n- " . implode( "\n- ", $titles )
        );
    }

    return $published;
}
add_action( 'wmsf_check_missed', 'wmsf_check_missed' );

/** Manual "Check now" button handler (Tools page). */
function wmsf_admin_init() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( isset( $_GET['wmsf_check'] ) && check_admin_referer( 'wmsf_check_now' ) ) {
        $count = wmsf_check_missed();
        add_settings_error( 'wmsf', 'wmsf_checked', sprintf( '%d missed post(s) published.', $count ), 'updated' );
    }
}
add_action( 'admin_init', 'wmsf_admin_init' );

/** Settings page under Tools. */
function wmsf_menu() {
    add_management_page(
        'Missed Schedule Fixer',
        'Missed Schedule',
        'manage_options',
        'wmsf',
        'wmsf_page'
    );
}
add_action( 'admin_menu', 'wmsf_menu' );

function wmsf_page() {
    if ( isset( $_POST['wmsf_save'] ) && check_admin_referer( 'wmsf_save_settings' ) ) {
        update_option( WMSF_OPTION, array(
            'grace_minutes' => max( 0, (int) $_POST['wmsf_grace'] ),
            'lookback_days' => max( 1, (int) $_POST['wmsf_lookback'] ),
            'notify_email'  => sanitize_email( wp_unslash( $_POST['wmsf_email'] ) ),
        ) );
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }
    settings_errors( 'wmsf' );
    $s = wmsf_settings();
    ?>
    <div class="wrap">
        <h1>Missed Schedule Fixer</h1>
        <p>Checks hourly for scheduled posts stuck in "future" status past their time and publishes them.</p>
        <form method="post">
            <?php wp_nonce_field( 'wmsf_save_settings' ); ?>
            <table class="form-table">
                <tr><th><label for="wmsf_grace">Grace period (minutes)</label></th>
                    <td><input type="number" id="wmsf_grace" name="wmsf_grace" value="<?php echo esc_attr( $s['grace_minutes'] ); ?>" min="0" class="small-text"> — posts are only recovered after this delay, so a post a few minutes late is not rushed.</td></tr>
                <tr><th><label for="wmsf_lookback">Lookback (days)</label></th>
                    <td><input type="number" id="wmsf_lookback" name="wmsf_lookback" value="<?php echo esc_attr( $s['lookback_days'] ); ?>" min="1" class="small-text"> — very old missed posts are left alone.</td></tr>
                <tr><th><label for="wmsf_email">Notify email (optional)</label></th>
                    <td><input type="email" id="wmsf_email" name="wmsf_email" value="<?php echo esc_attr( $s['notify_email'] ); ?>" class="regular-text"></td></tr>
            </table>
            <?php submit_button( 'Save Settings', 'primary', 'wmsf_save' ); ?>
        </form>
        <p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=wmsf&wmsf_check=1' ), 'wmsf_check_now' ) ); ?>">Check now</a></p>
    </div>
    <?php
}

/** Uninstall cleanup is handled by uninstall.php. */
