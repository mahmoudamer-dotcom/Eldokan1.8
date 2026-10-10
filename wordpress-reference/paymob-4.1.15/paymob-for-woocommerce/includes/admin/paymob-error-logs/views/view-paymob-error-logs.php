<?php
/**
 * Paymob Error Logs admin view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defaults when this view is analysed or included without caller-provided vars.
if ( ! isset( $logs ) || ! is_array( $logs ) ) {
	$logs = array();
}
if ( ! isset( $filters ) || ! is_array( $filters ) ) {
	$filters = array(
		'source'    => '',
		'context'   => '',
		'http_code' => '',
		'date_from' => '',
		'date_to'   => '',
		'search'    => '',
	);
}
if ( ! isset( $sources ) || ! is_array( $sources ) ) {
	$sources = array();
}
if ( ! isset( $contexts ) || ! is_array( $contexts ) ) {
	$contexts = array();
}
?>
<div class="wrap paymob-error-logs-page">
	<div class="paymob-log-hero">
		<div>
			<h1><?php echo esc_html__( 'Paymob Error Logs', 'paymob-for-woocommerce' ); ?></h1>
			<p><?php echo esc_html__( 'Checkout, gateway, and Paymob API errors captured from live payment flows.', 'paymob-for-woocommerce' ); ?></p>
		</div>
		<span class="paymob-log-total"><?php
		/* translators: %d: number of filtered log entries */
		printf( esc_html__( '%d result(s)', 'paymob-for-woocommerce' ), count( $logs ) );
		?></span>
	</div>

	<?php if ( isset( $_GET['cleared'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'All Paymob error logs were cleared.', 'paymob-for-woocommerce' ); ?></p></div>
	<?php endif; ?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="paymob-log-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( Paymob_Error_Logs::MENU_SLUG ); ?>" />

		<label class="paymob-log-filter">
			<span><?php echo esc_html__( 'Source', 'paymob-for-woocommerce' ); ?></span>
			<select name="source">
				<option value=""><?php echo esc_html__( 'All sources', 'paymob-for-woocommerce' ); ?></option>
				<?php foreach ( $sources as $source ) : ?>
					<option value="<?php echo esc_attr( $source ); ?>" <?php selected( $filters['source'], $source ); ?>><?php echo esc_html( $source ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="paymob-log-filter">
			<span><?php echo esc_html__( 'Context', 'paymob-for-woocommerce' ); ?></span>
			<select name="context">
				<option value=""><?php echo esc_html__( 'All contexts', 'paymob-for-woocommerce' ); ?></option>
				<?php foreach ( $contexts as $context ) : ?>
					<option value="<?php echo esc_attr( $context ); ?>" <?php selected( $filters['context'], $context ); ?>><?php echo esc_html( $context ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>

		<label class="paymob-log-filter paymob-log-filter--small">
			<span><?php echo esc_html__( 'HTTP Code', 'paymob-for-woocommerce' ); ?></span>
			<input type="text" name="http_code" value="<?php echo esc_attr( $filters['http_code'] ); ?>" placeholder="401" />
		</label>

		<label class="paymob-log-filter">
			<span><?php echo esc_html__( 'From', 'paymob-for-woocommerce' ); ?></span>
			<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
		</label>

		<label class="paymob-log-filter">
			<span><?php echo esc_html__( 'To', 'paymob-for-woocommerce' ); ?></span>
			<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
		</label>

		<label class="paymob-log-filter paymob-log-filter--search">
			<span><?php echo esc_html__( 'Search', 'paymob-for-woocommerce' ); ?></span>
			<input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php echo esc_attr__( 'Message, URL, raw response...', 'paymob-for-woocommerce' ); ?>" />
		</label>

		<div class="paymob-log-filter-actions">
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Apply Filter', 'paymob-for-woocommerce' ); ?></button>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Paymob_Error_Logs::MENU_SLUG ) ); ?>"><?php echo esc_html__( 'Reset', 'paymob-for-woocommerce' ); ?></a>
		</div>
	</form>

	<div class="paymob-log-toolbar">
		<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob-main' ) ); ?>"><?php echo esc_html__( 'Back to Paymob Settings', 'paymob-for-woocommerce' ); ?></a>

		<div class="paymob-log-toolbar__actions">
			<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="paymob_export_error_logs" />
				<?php wp_nonce_field( 'paymob_export_error_logs' ); ?>
				<input type="hidden" name="source" value="<?php echo esc_attr( $filters['source'] ); ?>" />
				<input type="hidden" name="context" value="<?php echo esc_attr( $filters['context'] ); ?>" />
				<input type="hidden" name="http_code" value="<?php echo esc_attr( $filters['http_code'] ); ?>" />
				<input type="hidden" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
				<input type="hidden" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
				<input type="hidden" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" />
				<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Export CSV', 'paymob-for-woocommerce' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all logs?', 'paymob-for-woocommerce' ) ); ?>');">
				<input type="hidden" name="action" value="paymob_clear_error_logs" />
				<?php wp_nonce_field( 'paymob_clear_error_logs' ); ?>
				<button type="submit" class="button button-link-delete"><?php echo esc_html__( 'Clear Logs', 'paymob-for-woocommerce' ); ?></button>
			</form>
		</div>
	</div>

	<div class="paymob-log-grid">
		<?php if ( empty( $logs ) ) : ?>
			<div class="paymob-log-empty"><?php echo esc_html__( 'No errors found for the current filters.', 'paymob-for-woocommerce' ); ?></div>
		<?php else : ?>
			<?php foreach ( $logs as $log ) : ?>
				<?php
				$meta      = isset( $log['meta'] ) && is_array( $log['meta'] ) ? $log['meta'] : array();
				$source    = isset( $log['source'] ) ? (string) $log['source'] : '';
				$context   = isset( $log['context'] ) ? (string) $log['context'] : '';
				$message   = isset( $log['message'] ) ? (string) $log['message'] : '';
				$time      = isset( $log['time'] ) ? (string) $log['time'] : '';
				$method    = isset( $meta['request_method'] ) ? (string) $meta['request_method'] : '';
				$http_code = isset( $meta['http_code'] ) ? (string) $meta['http_code'] : '';
				$url       = isset( $meta['request_url'] ) ? (string) $meta['request_url'] : '';
				$raw       = isset( $meta['response_raw'] ) ? (string) $meta['response_raw'] : '';
				?>
				<div class="paymob-log-card">
					<div class="paymob-log-card__head">
						<span class="paymob-log-badge"><?php echo esc_html( strtoupper( $source ) ); ?></span>
						<time><?php echo esc_html( $time ); ?></time>
					</div>
					<p class="paymob-log-card__message"><?php echo esc_html( $message ); ?></p>
					<div class="paymob-log-card__meta">
						<span><?php echo esc_html__( 'Context:', 'paymob-for-woocommerce' ); ?> <?php echo esc_html( $context ); ?></span>
						<?php if ( '' !== $method || '' !== $http_code ) : ?>
							<div class="paymob-log-meta-line">
								<?php if ( '' !== $method ) : ?>
									<code><?php echo esc_html( $method ); ?></code>
								<?php endif; ?>
								<?php if ( '' !== $http_code ) : ?>
									<strong><?php echo esc_html__( 'HTTP', 'paymob-for-woocommerce' ); ?></strong>
									<?php echo esc_html( $http_code ); ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if ( '' !== $url ) : ?>
							<p class="paymob-log-meta-url"><?php echo esc_html( $url ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $raw ) : ?>
							<details class="paymob-log-details" open>
								<summary><?php echo esc_html__( 'Raw HTTP body (before JSON decode)', 'paymob-for-woocommerce' ); ?></summary>
								<pre class="paymob-log-response-raw"><?php echo esc_html( $raw ); ?></pre>
							</details>
						<?php elseif ( '' !== $method || '' !== $http_code || '' !== $url ) : ?>
							<details class="paymob-log-details">
								<summary><?php echo esc_html__( 'Raw HTTP body (before JSON decode)', 'paymob-for-woocommerce' ); ?></summary>
								<pre class="paymob-log-response-raw paymob-log-response-raw--empty"><?php echo esc_html__( 'No response body was returned by Paymob.', 'paymob-for-woocommerce' ); ?></pre>
							</details>
						<?php endif; ?>
						<?php if ( ! empty( $meta ) ) : ?>
							<?php foreach ( $meta as $key => $value ) : ?>
								<?php if ( in_array( $key, array( 'request_method', 'http_code', 'request_url', 'response_raw' ), true ) || '' === (string) $value ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<div class="paymob-log-meta-line">
									<strong><?php echo esc_html( str_replace( '_', ' ', $key ) ); ?>:</strong>
									<?php echo esc_html( $value ); ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
