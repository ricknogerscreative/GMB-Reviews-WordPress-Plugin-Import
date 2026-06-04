<?php
// includes/class-admin-page.php

class EDOA_RS_Admin_Page {

	const SLUG  = 'edoa-review-sync';
	const NONCE = 'edoa_rs_sync';

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_edoa_rs_run', array( $this, 'handle_run' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=testimonial',
			'Review Sync',
			'Review Sync',
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$last = get_option( 'edoa_rs_last_result' );
		$next = wp_next_scheduled( EDOA_RS_CRON_HOOK );
		echo '<div class="wrap"><h1>EDOA Review Sync</h1>';
		echo '<p>Next scheduled sync: ' . ( $next ? esc_html( date_i18n( 'Y-m-d H:i', $next ) ) : 'not scheduled' ) . '</p>';
		if ( $last ) {
			echo '<p><strong>Last run:</strong> ' . esc_html( $last['message'] ?? '' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="edoa_rs_run">';
		wp_nonce_field( self::NONCE );
		submit_button( 'Run Sync Now' );
		echo '</form></div>';
	}

	public function handle_run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		check_admin_referer( self::NONCE );
		$result = ( new EDOA_Review_Sync() )->run();
		update_option( 'edoa_rs_last_result', $result );
		wp_safe_redirect( add_query_arg(
			array( 'post_type' => 'testimonial', 'page' => self::SLUG ),
			admin_url( 'edit.php' )
		) );
		exit;
	}
}
