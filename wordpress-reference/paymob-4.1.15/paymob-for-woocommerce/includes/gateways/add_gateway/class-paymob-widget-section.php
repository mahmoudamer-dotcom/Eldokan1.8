<?php
/**
 * Affordability Widget settings section.
 *
 * Registers a custom field type (`paymob_affordability_widget_ui`) that renders the
 * full Affordability Widget administration UI inside the WooCommerce settings form
 * for `section=widget`. Settings persist to the `woocommerce_paymob_widget_settings`
 * option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paymob_Widget_Settings {

	public static function paymob_widget_setting( $settings, $current_section ) {
		if ( 'widget' !== $current_section ) {
			return $settings;
		}

		$new_settings = include __DIR__ . '/../../admin/paymob-widget-setting.php';

		if ( is_array( $new_settings ) ) {
			foreach ( $new_settings as $new_setting ) {
				if ( ! in_array( $new_setting, $settings, true ) ) {
					$settings[] = $new_setting;
				}
			}
		}

		if ( class_exists( 'Paymob_Style' ) ) {
			Paymob_Style::paymob_admin();
		}
		if ( class_exists( 'Paymob_Scripts' ) && method_exists( 'Paymob_Scripts', 'enqueue_paymob_widget_script' ) ) {
			Paymob_Scripts::enqueue_paymob_widget_script();
		}

		return $settings;
	}

	public static function render_ui() {
		$widget_settings = get_option( 'woocommerce_paymob_widget_settings', array() );
		if ( ! is_array( $widget_settings ) ) {
			$widget_settings = array();
		}

		$enabled              = isset( $widget_settings['enabled_widget'] ) && 'yes' === $widget_settings['enabled_widget'];
		$stored_integration   = isset( $widget_settings['integration_id'] ) ? (string) $widget_settings['integration_id'] : '';
		$min_product_enabled  = isset( $widget_settings['min_product_enabled'] ) && 'yes' === $widget_settings['min_product_enabled'];
		$min_product_amount   = isset( $widget_settings['min_product_amount'] ) ? (string) $widget_settings['min_product_amount'] : '';
		$min_cart_enabled     = isset( $widget_settings['min_cart_enabled'] ) && 'yes' === $widget_settings['min_cart_enabled'];
		$min_cart_amount      = isset( $widget_settings['min_cart_amount'] ) ? (string) $widget_settings['min_cart_amount'] : '';
		$widget_theme         = isset( $widget_settings['widget_theme'] ) ? (string) $widget_settings['widget_theme'] : 'primary';
		if ( ! in_array( $widget_theme, array( 'primary', 'light', 'dark' ), true ) ) {
			$widget_theme = 'primary';
		}

		$bank_installment_ids      = self::get_bank_installment_integration_ids();
		$existing_bank_ids         = self::get_existing_bank_installment_integration_ids();
		$selected_integration      = self::resolve_selected_integration_id( $stored_integration );
		$integration_id_count      = count( $bank_installment_ids );
		$has_integration_ids       = ! empty( $bank_installment_ids );
		$integrations_all_disabled = ! $has_integration_ids && ! empty( $existing_bank_ids );
		$integration_hint_text     = __( 'If you have a single Bank Installment Integration ID, it will be pre-selected automatically. For multiple IDs, select the one to use for the widget.', 'paymob-for-woocommerce' );

		if ( ! $has_integration_ids ) {
			$selected_integration = '';
		} elseif ( 1 === $integration_id_count ) {
			self::maybe_persist_single_integration_id( $widget_settings, $selected_integration );
		}
		// Lock the configuration cards (everything below the Enable Widget toggle) until the merchant
		// turns the widget on. When no integration IDs exist the harder `--dimmed` lock already applies.
		$enable_locked = $has_integration_ids && ! $enabled;

		$page_classes = array( 'paymob-aw-page' );
		if ( ! $has_integration_ids ) {
			$page_classes[] = 'paymob-aw-page--dimmed';
		}
		if ( $enable_locked ) {
			$page_classes[] = 'paymob-aw-page--enable-locked';
		}

		$integration_select_wrap_class = 'paymob-aw-select-wrap';
		if ( empty( $bank_installment_ids ) ) {
			$integration_select_wrap_class .= ' paymob-aw-select-wrap--disabled';
		} elseif ( '' === $selected_integration ) {
			$integration_select_wrap_class .= ' paymob-aw-select-wrap--placeholder';
		}

		echo '</table>'; // Close the WC form-table opened by the title field above.
		?>
		<style id="paymob-aw-integration-field-fix">
			.paymob-aw-field--integration abbr.required,
			.paymob-aw-field--integration span.required:not(.paymob-aw-req),
			.paymob-aw-field--integration .paymob-aw-integration-control abbr.required,
			.paymob-aw-field--integration .paymob-aw-integration-control span.required,
			.paymob-aw-page .red-star,
			.paymob-aw-field--integration .red-star {
				display: none !important;
				visibility: hidden !important;
				width: 0 !important;
				height: 0 !important;
				margin: 0 !important;
				padding: 0 !important;
				overflow: hidden !important;
				font-size: 0 !important;
				line-height: 0 !important;
			}
			.paymob-aw-field--integration .paymob-aw-field__label .paymob-aw-req {
				display: inline !important;
				color: #d63638 !important;
			}
			.paymob-aw-select-hint {
				display: none !important;
				visibility: hidden !important;
			}
			.paymob-aw-select-wrap--placeholder .paymob-aw-select,
			.wp-core-ui .paymob-aw-select-wrap--placeholder .paymob-aw-select {
				color: #98a2b3 !important;
				font-weight: 400 !important;
				opacity: 0.82;
			}
		</style>
		<div class="<?php echo esc_attr( implode( ' ', $page_classes ) ); ?>" data-active-theme="<?php echo esc_attr( $widget_theme ); ?>" data-has-integration-ids="<?php echo esc_attr( $has_integration_ids ? '1' : '0' ); ?>">
			<div class="paymob-aw-card paymob-aw-header">
				<div class="paymob-aw-header__icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round"/>
					</svg>
				</div>
				<div class="paymob-aw-header__content">
					<div class="paymob-aw-header__title">
						<h2><?php echo esc_html__( 'Affordability Widget', 'paymob-for-woocommerce' ); ?></h2>
						<span class="paymob-aw-badge paymob-aw-badge--new"><?php echo esc_html__( 'New', 'paymob-for-woocommerce' ); ?></span>
					</div>
					<p class="paymob-aw-header__desc">
						<?php echo esc_html__( "Display Bank Installment Plans on your product and cart pages. Shoppers can select a plan upfront — and it carries through automatically to Paymob's checkout, so they don't have to choose again.", 'paymob-for-woocommerce' ); ?>
					</p>
					<p class="paymob-aw-header__availability">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 22s-7-7.58-7-12a7 7 0 1 1 14 0c0 4.42-7 12-7 12Z" stroke="#475467" stroke-width="1.5"/><circle cx="12" cy="10" r="2.5" stroke="#475467" stroke-width="1.5"/></svg>
						<?php
						printf(
							/* translators: %s: Country name */
							esc_html__( 'Currently available for %s only.', 'paymob-for-woocommerce' ),
							'<strong>' . esc_html__( 'Egypt', 'paymob-for-woocommerce' ) . '</strong>'
						);
						?>
					</p>
				</div>
			</div>

			<div class="paymob-aw-card paymob-aw-row">
				<div class="paymob-aw-row__text">
					<h3><?php echo esc_html__( 'Enable widget', 'paymob-for-woocommerce' ); ?></h3>
					<p><?php echo esc_html__( 'Determines whether the Affordability Widget is displayed on product and cart pages.', 'paymob-for-woocommerce' ); ?></p>
				</div>
				<div class="paymob-aw-row__action">
					<label class="paymob-aw-toggle">
						<input type="hidden" name="enable" value="no" />
						<input type="checkbox" name="enable" value="yes" <?php checked( $enabled && $has_integration_ids ); ?> <?php disabled( ! $has_integration_ids ); ?> />
						<span class="paymob-aw-toggle__slider" aria-hidden="true"></span>
					</label>
				</div>
			</div>

			<?php if ( ! $has_integration_ids ) : ?>
				<div class="paymob-aw-notice paymob-aw-notice--warning">
					<div class="paymob-aw-notice__icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="#b54708" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</div>
					<div class="paymob-aw-notice__body">
						<?php if ( $integrations_all_disabled ) : ?>
							<strong><?php echo esc_html__( 'Bank Installment integration is disabled', 'paymob-for-woocommerce' ); ?></strong>
							<p>
								<?php
								printf(
									/* translators: %s: Payment Integrations settings link */
									esc_html__( 'Your Bank Installment Integration ID(s) are currently disabled. To use the Affordability Widget, enable at least one in %s.', 'paymob-for-woocommerce' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob_list_gateways' ) ) . '">' . esc_html__( 'Payment Integrations', 'paymob-for-woocommerce' ) . '</a>'
								);
								?>
							</p>
						<?php else : ?>
							<strong><?php echo esc_html__( 'No Bank Installment Integration ID found', 'paymob-for-woocommerce' ); ?></strong>
							<p>
								<?php
								printf(
									/* translators: %s: Support email */
									esc_html__( 'Please reach out to your account manager or contact us at %s to enable Bank Installment Plans on your account.', 'paymob-for-woocommerce' ),
									'<a href="mailto:support@paymob.com">support@paymob.com</a>'
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="paymob-aw-card paymob-aw-section">
				<div class="paymob-aw-section__head">
					<span class="paymob-aw-section__icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6" stroke="#344054" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</span>
					<h3><?php echo esc_html__( 'Bank installment configuration', 'paymob-for-woocommerce' ); ?></h3>
				</div>
				<div class="paymob-aw-field paymob-aw-field--integration">
					<label for="paymob_aw_integration_id" class="paymob-aw-field__label">
						<span class="paymob-aw-field__label-text"><?php echo esc_html__( 'Bank installment integration ID', 'paymob-for-woocommerce' ); ?></span>
						<span class="paymob-aw-req" title="<?php echo esc_attr__( 'Required', 'paymob-for-woocommerce' ); ?>">*</span>
					</label>
					<div class="paymob-aw-integration-control">
						<div class="<?php echo esc_attr( $integration_select_wrap_class ); ?>">
							<?php if ( 1 === $integration_id_count && '' !== $selected_integration ) : ?>
								<input type="hidden" name="paymob_aw_integration_id" value="<?php echo esc_attr( $selected_integration ); ?>" />
							<?php endif; ?>
							<select id="paymob_aw_integration_id" name="<?php echo esc_attr( 1 === $integration_id_count ? '' : 'paymob_aw_integration_id' ); ?>" class="paymob-aw-select" <?php disabled( empty( $bank_installment_ids ) ); ?><?php if ( 1 === $integration_id_count ) : ?> data-paymob-aw-single-id="1"<?php endif; ?>>
								<?php if ( 1 !== $integration_id_count ) : ?>
									<option value="" <?php selected( $selected_integration, '' ); ?>><?php echo esc_html__( 'Select integration ID', 'paymob-for-woocommerce' ); ?></option>
								<?php endif; ?>
								<?php foreach ( $bank_installment_ids as $id => $label ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $selected_integration, (string) $id ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="paymob-aw-select-chevron" aria-hidden="true">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</span>
						</div>
						<p class="paymob-aw-help">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#98a2b3" stroke-width="1.5"/><path d="M12 8v.01M11 12h1v4h1" stroke="#98a2b3" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<?php echo esc_html( $integration_hint_text ); ?>
						</p>
					</div>
				</div>
			</div>

			<div class="paymob-aw-card paymob-aw-section">
				<div class="paymob-aw-section__head">
					<span class="paymob-aw-section__icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M21 8.5 12 3 3 8.5M21 8.5 12 14 3 8.5M21 8.5v7L12 21l-9-5.5v-7" stroke="#344054" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</span>
					<h3><?php echo esc_html__( 'Minimum product amount', 'paymob-for-woocommerce' ); ?></h3>
				</div>
				<div class="paymob-aw-row paymob-aw-row--inline">
					<div class="paymob-aw-row__text">
						<h4><?php echo esc_html__( 'Set a minimum product price', 'paymob-for-woocommerce' ); ?></h4>
						<p><?php echo esc_html__( 'Hide the widget when the product price is below this amount.', 'paymob-for-woocommerce' ); ?></p>
					</div>
					<div class="paymob-aw-row__action">
						<label class="paymob-aw-toggle">
							<input type="hidden" name="min_product_enabled" value="no" />
							<input type="checkbox" name="min_product_enabled" value="yes" <?php checked( $min_product_enabled ); ?> data-paymob-aw-toggle-target="paymob-aw-min-product" />
							<span class="paymob-aw-toggle__slider" aria-hidden="true"></span>
						</label>
					</div>
				</div>
				<div class="paymob-aw-field paymob-aw-field--conditional" id="paymob-aw-min-product"<?php if ( ! $min_product_enabled ) : ?> hidden<?php endif; ?>>
					<label for="paymob_aw_min_product_amount"><?php echo esc_html__( 'Amount (EGP)', 'paymob-for-woocommerce' ); ?></label>
					<input type="number" min="0" step="0.01" id="paymob_aw_min_product_amount" name="min_product_amount" value="<?php echo esc_attr( $min_product_amount ); ?>" class="paymob-aw-input" placeholder="0.00" />
				</div>
			</div>

			<div class="paymob-aw-card paymob-aw-section">
				<div class="paymob-aw-section__head">
					<span class="paymob-aw-section__icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="20" r="1.5" stroke="#344054" stroke-width="1.6"/><circle cx="17" cy="20" r="1.5" stroke="#344054" stroke-width="1.6"/><path d="M3 4h2l2.5 12h12L22 7H6" stroke="#344054" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</span>
					<h3><?php echo esc_html__( 'Minimum cart amount', 'paymob-for-woocommerce' ); ?></h3>
				</div>
				<div class="paymob-aw-row paymob-aw-row--inline">
					<div class="paymob-aw-row__text">
						<h4><?php echo esc_html__( 'Set a minimum cart subtotal', 'paymob-for-woocommerce' ); ?></h4>
						<p><?php echo esc_html__( 'Hide the widget when the cart subtotal is below this amount.', 'paymob-for-woocommerce' ); ?></p>
					</div>
					<div class="paymob-aw-row__action">
						<label class="paymob-aw-toggle">
							<input type="hidden" name="min_cart_enabled" value="no" />
							<input type="checkbox" name="min_cart_enabled" value="yes" <?php checked( $min_cart_enabled ); ?> data-paymob-aw-toggle-target="paymob-aw-min-cart" />
							<span class="paymob-aw-toggle__slider" aria-hidden="true"></span>
						</label>
					</div>
				</div>
				<div class="paymob-aw-field paymob-aw-field--conditional" id="paymob-aw-min-cart"<?php if ( ! $min_cart_enabled ) : ?> hidden<?php endif; ?>>
					<label for="paymob_aw_min_cart_amount"><?php echo esc_html__( 'Amount (EGP)', 'paymob-for-woocommerce' ); ?></label>
					<input type="number" min="0" step="0.01" id="paymob_aw_min_cart_amount" name="min_cart_amount" value="<?php echo esc_attr( $min_cart_amount ); ?>" class="paymob-aw-input" placeholder="0.00" />
				</div>
			</div>

			<div class="paymob-aw-card paymob-aw-section">
				<div class="paymob-aw-section__head">
					<span class="paymob-aw-section__icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#344054" stroke-width="1.6"/><circle cx="8" cy="10" r="1" fill="#344054"/><circle cx="12" cy="7" r="1" fill="#344054"/><circle cx="16" cy="10" r="1" fill="#344054"/><circle cx="14" cy="15" r="1" fill="#344054"/></svg>
					</span>
					<h3><?php echo esc_html__( 'Widget theme', 'paymob-for-woocommerce' ); ?></h3>
				</div>
				<div class="paymob-aw-field">
					<label class="paymob-aw-field__label"><?php echo esc_html__( 'Select theme', 'paymob-for-woocommerce' ); ?></label>
					<div class="paymob-aw-themes" role="radiogroup">
						<?php
						$themes = array(
							'primary' => array( __( 'Primary', 'paymob-for-woocommerce' ), __( 'Branded, prominent', 'paymob-for-woocommerce' ) ),
							'light'   => array( __( 'Light', 'paymob-for-woocommerce' ), __( 'Subtle, neutral', 'paymob-for-woocommerce' ) ),
							'dark'    => array( __( 'Dark', 'paymob-for-woocommerce' ), __( 'Dark-mode storefronts', 'paymob-for-woocommerce' ) ),
						);
						foreach ( $themes as $theme_key => $theme_data ) :
							?>
							<label class="paymob-aw-theme-card" data-theme="<?php echo esc_attr( $theme_key ); ?>">
								<input type="radio" name="widget_theme" value="<?php echo esc_attr( $theme_key ); ?>" <?php checked( $widget_theme, $theme_key ); ?> />
								<span class="paymob-aw-theme-card__swatch paymob-aw-theme-card__swatch--<?php echo esc_attr( $theme_key ); ?>"></span>
								<span class="paymob-aw-theme-card__text">
									<strong><?php echo esc_html( $theme_data[0] ); ?></strong>
									<small><?php echo esc_html( $theme_data[1] ); ?></small>
								</span>
								<span class="paymob-aw-theme-card__check" aria-hidden="true">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12l5 5L20 7" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="paymob-aw-preview-wrap">
						<span class="paymob-aw-preview-label"><?php echo esc_html__( 'PREVIEW', 'paymob-for-woocommerce' ); ?></span>
						<div class="paymob-aw-preview paymob-aw-preview--<?php echo esc_attr( $widget_theme ); ?>" data-paymob-aw-preview>
							<div class="paymob-aw-preview__shell">
								<div class="paymob-aw-preview__top">
									<p class="paymob-aw-preview__pill"><?php echo esc_html__( '0% Interest Plans', 'paymob-for-woocommerce' ); ?></p>
									<div class="paymob-aw-preview__powered">
										<span><?php echo esc_html__( 'Powered by', 'paymob-for-woocommerce' ); ?></span>
										<img
											src="<?php echo esc_url( plugins_url( PAYMOB_PLUGIN_NAME . '/assets/img/paymob.png' ) ); ?>"
											alt="<?php echo esc_attr__( 'paymob', 'paymob-for-woocommerce' ); ?>"
											class="paymob-aw-preview__logo"
										/>
									</div>
								</div>
								<div class="paymob-aw-preview__card">
									<div class="paymob-aw-preview__body">
										<div class="paymob-aw-preview__content">
											<div class="paymob-aw-preview__copy">
												<p class="paymob-aw-preview__title"><?php echo esc_html__( 'Affordable installment plans', 'paymob-for-woocommerce' ); ?></p>
												<p class="paymob-aw-preview__subtitle">
													<span><?php echo esc_html__( 'starting from', 'paymob-for-woocommerce' ); ?></span>
													<strong><?php echo esc_html__( 'EGP 300', 'paymob-for-woocommerce' ); ?> / <?php echo esc_html__( 'month', 'paymob-for-woocommerce' ); ?></strong>
												</p>
											</div>
											<button type="button" class="paymob-aw-preview__cta"><?php echo esc_html__( 'View all plans', 'paymob-for-woocommerce' ); ?> ›</button>
										</div>
										<div class="paymob-aw-preview__visual" aria-hidden="true">
											<img
												class="paymob-aw-preview__illustration-img"
												src="<?php echo esc_url( plugins_url( PAYMOB_PLUGIN_NAME . '/assets/img/paymob-aw-zero-percent.png' ) ); ?>"
												alt=""
												width="110"
												height="90"
												loading="lazy"
												decoding="async"
											/>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<p class="paymob-aw-help paymob-aw-help--center">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="#98a2b3" stroke-width="1.5"/><path d="M12 8v.01M11 12h1v4h1" stroke="#98a2b3" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
						<?php echo esc_html__( 'The theme controls how the widget appears to shoppers on your storefront.', 'paymob-for-woocommerce' ); ?>
					</p>
				</div>
			</div>
		</div>
		<script>
		( function() {
			function onReady( fn ) {
				if ( 'loading' !== document.readyState ) { fn(); }
				else { document.addEventListener( 'DOMContentLoaded', fn ); }
			}
			onReady( function() {
				var page = document.querySelector( '.paymob-aw-page' );
				if ( ! page ) { return; }

				document.querySelectorAll( '[data-paymob-aw-toggle-target]' ).forEach( function( cb ) {
					cb.addEventListener( 'change', function() {
						var target = document.getElementById( cb.getAttribute( 'data-paymob-aw-toggle-target' ) );
						if ( target ) { target.hidden = ! cb.checked; }
					} );
				} );

				var preview = document.querySelector( '[data-paymob-aw-preview]' );
				document.querySelectorAll( '.paymob-aw-theme-card input[type="radio"]' ).forEach( function( radio ) {
					radio.addEventListener( 'change', function() {
						if ( ! preview ) { return; }
						preview.className = 'paymob-aw-preview paymob-aw-preview--' + radio.value;
						page.setAttribute( 'data-active-theme', radio.value );
					} );
				} );

				// Lock every configuration card until the Enable Widget toggle is on.
				// We only apply this when the harder "no integration IDs" lock (--dimmed) is not already in effect.
				var hasIntegrationIds = '1' === page.getAttribute( 'data-has-integration-ids' );
				var enableCheckbox    = page.querySelector( 'input[type="checkbox"][name="enable"]' );
				if ( hasIntegrationIds && enableCheckbox ) {
					var syncEnableLock = function() {
						page.classList.toggle( 'paymob-aw-page--enable-locked', ! enableCheckbox.checked );
					};
					enableCheckbox.addEventListener( 'change', syncEnableLock );
					syncEnableLock();
				}

				var integrationSelect = document.getElementById( 'paymob_aw_integration_id' );
				function stripIntegrationSelectValidation() {
					if ( ! integrationSelect ) {
						return;
					}
					integrationSelect.removeAttribute( 'required' );
					integrationSelect.setAttribute( 'aria-required', 'false' );
				}

				if ( integrationSelect ) {
					stripIntegrationSelectValidation();
					integrationSelect.addEventListener(
						'invalid',
						function( event ) {
							event.preventDefault();
							stripIntegrationSelectValidation();
						}
					);

					var syncIntegrationPlaceholderStyle = function() {
						var wrap = integrationSelect.closest( '.paymob-aw-select-wrap' );
						if ( ! wrap ) {
							return;
						}
						wrap.classList.toggle( 'paymob-aw-select-wrap--placeholder', '' === integrationSelect.value );
					};

					integrationSelect.addEventListener( 'change', syncIntegrationPlaceholderStyle );

					if ( integrationSelect.options.length ) {
						var realOptions = Array.prototype.filter.call( integrationSelect.options, function( option ) {
							return '' !== option.value;
						} );
						if ( 1 === realOptions.length ) {
							integrationSelect.value = realOptions[0].value;
						}
					}

					syncIntegrationPlaceholderStyle();
				}

				var mainForm = document.getElementById( 'mainform' );
				if ( mainForm ) {
					mainForm.addEventListener(
						'submit',
						function() {
							stripIntegrationSelectValidation();
							document.querySelectorAll( '.paymob-aw-page select' ).forEach( function( field ) {
								field.removeAttribute( 'required' );
								field.setAttribute( 'aria-required', 'false' );
							} );
						},
						true
					);
				}

				function cleanupIntegrationRequiredMarkers() {
					var integrationField = document.querySelector( '.paymob-aw-field--integration' );
					if ( ! integrationField ) {
						return;
					}

					stripIntegrationSelectValidation();

					integrationField.querySelectorAll( 'abbr.required, span.required, span.red-star, .paymob-aw-select-hint' ).forEach( function( marker ) {
						if ( ! marker.classList.contains( 'paymob-aw-req' ) ) {
							marker.remove();
						}
					} );
					document.querySelectorAll( '.paymob-aw-page .red-star' ).forEach( function( marker ) {
						marker.remove();
					} );
				}

				cleanupIntegrationRequiredMarkers();

				if ( window.MutationObserver ) {
					var integrationField = document.querySelector( '.paymob-aw-field--integration' );
					if ( integrationField ) {
						var markerObserver = new MutationObserver( cleanupIntegrationRequiredMarkers );
						markerObserver.observe( integrationField, { childList: true, subtree: true } );
						window.setTimeout( function() {
							markerObserver.disconnect();
						}, 10000 );
					}
				}
			} );
		} )();
		</script>
		<?php
		echo '<table class="form-table">'; // Re-open a form table so WC's subsequent closing tag is balanced.
	}

	public static function resolve_selected_integration_id( $stored_value = '' ) {
		$bank_installment_ids = self::get_bank_installment_integration_ids();
		$stored_value         = trim( (string) $stored_value );

		if ( '' !== $stored_value ) {
			foreach ( $bank_installment_ids as $id => $label ) {
				if ( (string) $id === $stored_value ) {
					return (string) $id;
				}
			}
		}

		if ( 1 === count( $bank_installment_ids ) ) {
			return (string) array_key_first( $bank_installment_ids );
		}

		return '';
	}

	/**
	 * Persist a single available integration ID so the widget works before the merchant saves settings.
	 *
	 * @param array  $widget_settings     Current widget settings.
	 * @param string $selected_integration Resolved integration ID.
	 */
	public static function maybe_persist_single_integration_id( $widget_settings, $selected_integration ) {
		if ( ! is_array( $widget_settings ) || '' === (string) $selected_integration ) {
			return;
		}

		$stored = isset( $widget_settings['integration_id'] ) ? trim( (string) $widget_settings['integration_id'] ) : '';
		if ( $stored === (string) $selected_integration ) {
			return;
		}

		$widget_settings['integration_id'] = (string) $selected_integration;
		update_option( 'woocommerce_paymob_widget_settings', $widget_settings );
	}

	/**
	 * Resolve the integration ID configured on a bank-installments gateway row.
	 *
	 * @param object $gateway         Gateway DB row.
	 * @param array  $gateway_options Gateway WooCommerce settings.
	 * @return string
	 */
	protected static function resolve_gateway_integration_id( $gateway, $gateway_options ) {
		if ( ! empty( $gateway_options['single_integration_id'] ) ) {
			return trim( (string) $gateway_options['single_integration_id'] );
		}

		if ( ! empty( $gateway->integration_id ) ) {
			return trim( (string) $gateway->integration_id );
		}

		if ( ! empty( $gateway->gateway_id ) && preg_match( '/^paymob-(\d+)-bank-installments/i', (string) $gateway->gateway_id, $matches ) ) {
			return (string) $matches[1];
		}

		return '';
	}

	/**
	 * Current Paymob mode (test/live) from main settings.
	 *
	 * @return string
	 */
	protected static function get_current_paymob_mode() {
		$main_options = get_option( 'woocommerce_paymob-main_settings', array() );
		return ! empty( $main_options['mode'] ) ? strtolower( (string) $main_options['mode'] ) : 'test';
	}

	/**
	 * Whether the general Pay with Paymob gateway is enabled.
	 *
	 * @return bool
	 */
	protected static function is_paymob_multi_app_enabled() {
		$paymob_settings = get_option( 'woocommerce_paymob_settings', array() );
		return is_array( $paymob_settings )
			&& ! empty( $paymob_settings['enabled'] )
			&& 'yes' === $paymob_settings['enabled'];
	}

	/**
	 * Split integration_id_hidden into individual integration entries.
	 *
	 * @param string $hidden_raw Raw hidden integration string.
	 * @return array<int, array{id: string, entry: string}>
	 */
	protected static function parse_integration_id_hidden_entries( $hidden_raw ) {
		$hidden_raw = trim( (string) $hidden_raw );
		if ( '' === $hidden_raw ) {
			return array();
		}

		$entries = preg_split( '/[\r\n,]+/', $hidden_raw );
		$parsed  = array();

		foreach ( $entries as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry || ! preg_match( '/^(\d+)\s*:/', $entry, $matches ) ) {
				continue;
			}

			$parsed[] = array(
				'id'    => (string) $matches[1],
				'entry' => $entry,
			);
		}

		return $parsed;
	}

	/**
	 * Bank installment integration IDs configured on standalone gateway rows.
	 *
	 * @return array<string, string>
	 */
	protected static function get_standalone_bank_installment_integration_ids( $enabled_only = true ) {
		$ids = array();

		if ( ! class_exists( 'PaymobAutoGenerate' ) ) {
			return $ids;
		}

		$current_mode = self::get_current_paymob_mode();
		$gateways     = PaymobAutoGenerate::get_db_gateways_data();

		if ( ! is_array( $gateways ) ) {
			return $ids;
		}

		foreach ( $gateways as $gateway ) {
			if ( empty( $gateway->gateway_id ) || false === stripos( $gateway->gateway_id, 'bank-installments' ) ) {
				continue;
			}

			$gateway_mode = isset( $gateway->mode ) ? strtolower( (string) $gateway->mode ) : $current_mode;
			if ( $gateway_mode !== $current_mode ) {
				continue;
			}

			$gateway_options = get_option( 'woocommerce_' . $gateway->gateway_id . '_settings', array() );
			if ( $enabled_only && ( empty( $gateway_options['enabled'] ) || 'yes' !== $gateway_options['enabled'] ) ) {
				continue;
			}

			$integration_id = self::resolve_gateway_integration_id( $gateway, is_array( $gateway_options ) ? $gateway_options : array() );
			if ( '' === $integration_id ) {
				continue;
			}

			$label = ! empty( $gateway->checkout_title ) ? (string) $gateway->checkout_title : __( 'Bank Installments', 'paymob-for-woocommerce' );
			$ids[ $integration_id ] = $integration_id . ' — ' . $label;
		}

		return $ids;
	}

	/**
	 * Bank installment integration IDs configured on Pay with Paymob (general / multi-app).
	 *
	 * @return array<string, string>
	 */
	public static function get_multi_app_bank_installment_integration_ids( $enabled_only = true ) {
		if ( $enabled_only && ! self::is_paymob_multi_app_enabled() ) {
			return array();
		}

		$paymob_settings = get_option( 'woocommerce_paymob_settings', array() );
		if ( ! is_array( $paymob_settings ) ) {
			return array();
		}

		$enabled_ids = array();
		if ( ! empty( $paymob_settings['integration_id'] ) && is_array( $paymob_settings['integration_id'] ) ) {
			foreach ( $paymob_settings['integration_id'] as $enabled_id ) {
				$enabled_ids[] = (string) $enabled_id;
			}
		}

		if ( $enabled_only && empty( $enabled_ids ) ) {
			return array();
		}

		$hidden_raw   = isset( $paymob_settings['integration_id_hidden'] ) ? (string) $paymob_settings['integration_id_hidden'] : '';
		$current_mode = self::get_current_paymob_mode();
		$ids          = array();

		foreach ( self::parse_integration_id_hidden_entries( $hidden_raw ) as $item ) {
			$entry = $item['entry'];
			$id    = $item['id'];

			if ( false === stripos( $entry, 'bank-installments' ) ) {
				continue;
			}

			if ( false === stripos( $entry, $current_mode ) ) {
				continue;
			}

			if ( $enabled_only && ! in_array( $id, $enabled_ids, true ) ) {
				continue;
			}

			$label      = __( 'Bank Installments', 'paymob-for-woocommerce' ) . ' (' . __( 'Pay with Paymob', 'paymob-for-woocommerce' ) . ')';
			$ids[ $id ] = $id . ' — ' . $label;
		}

		return $ids;
	}

	/**
	 * Whether an integration ID is available via Pay with Paymob (general / multi-app).
	 *
	 * @param string $integration_id Paymob integration ID.
	 * @return bool
	 */
	public static function is_integration_in_multi_app_bank_installments( $integration_id ) {
		$integration_id = trim( (string) $integration_id );
		if ( '' === $integration_id ) {
			return false;
		}

		$multi_app_ids = self::get_multi_app_bank_installment_integration_ids();
		return isset( $multi_app_ids[ $integration_id ] );
	}

	public static function get_bank_installment_integration_ids() {
		return self::collect_bank_installment_integration_ids( true );
	}

	/**
	 * Bank installment integration IDs present in Payment Integrations, including disabled ones.
	 *
	 * @return array<string, string>
	 */
	public static function get_existing_bank_installment_integration_ids() {
		return self::collect_bank_installment_integration_ids( false );
	}

	/**
	 * @param bool $enabled_only When true, only IDs the merchant has turned on.
	 * @return array<string, string>
	 */
	protected static function collect_bank_installment_integration_ids( $enabled_only ) {
		$ids = self::get_standalone_bank_installment_integration_ids( $enabled_only );

		foreach ( self::get_multi_app_bank_installment_integration_ids( $enabled_only ) as $integration_id => $label ) {
			if ( ! isset( $ids[ $integration_id ] ) ) {
				$ids[ $integration_id ] = $label;
			}
		}

		return $ids;
	}
}

add_action( 'woocommerce_admin_field_paymob_affordability_widget_ui', array( 'Paymob_Widget_Settings', 'render_ui' ) );
