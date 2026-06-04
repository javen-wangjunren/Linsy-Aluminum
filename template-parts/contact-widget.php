<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$whatsapp_url = defined( 'LINSY_CONTACT_WHATSAPP_URL' ) ? LINSY_CONTACT_WHATSAPP_URL : '';
$phone_tel    = defined( 'LINSY_CONTACT_PHONE_TEL' ) ? LINSY_CONTACT_PHONE_TEL : '';
$email        = defined( 'LINSY_CONTACT_EMAIL' ) ? LINSY_CONTACT_EMAIL : '';

$links_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'linsy-contact-widget-links-' ) : 'linsy-contact-widget-links';
?>

<div class="linsy-contact-widget linsy-contact-widget__desktop" data-linsy-contact-widget="desktop">
	<button type="button" class="linsy-contact-widget__toggle" aria-expanded="true" aria-controls="<?php echo esc_attr( $links_id ); ?>">
		<span class="linsy-contact-widget__icon"><?php echo linsy_contact_widget_inline_svg( 'toggle' ); ?></span>
		<span class="screen-reader-text"><?php echo esc_html__( 'Toggle contact links', 'hello-elementor' ); ?></span>
	</button>

	<div id="<?php echo esc_attr( $links_id ); ?>" class="linsy-contact-widget__links">
		<?php if ( $whatsapp_url ) : ?>
			<a class="linsy-contact-widget__item linsy-contact-widget__item--whatsapp" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener">
				<span class="linsy-contact-widget__icon"><?php echo linsy_contact_widget_inline_svg( 'whatsapp' ); ?></span>
				<span class="screen-reader-text"><?php echo esc_html__( 'WhatsApp', 'hello-elementor' ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( $phone_tel ) : ?>
			<a class="linsy-contact-widget__item linsy-contact-widget__item--phone" href="<?php echo esc_url( 'tel:' . $phone_tel ); ?>">
				<span class="linsy-contact-widget__icon"><?php echo linsy_contact_widget_inline_svg( 'phone' ); ?></span>
				<span class="screen-reader-text"><?php echo esc_html__( 'Phone', 'hello-elementor' ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( $email ) : ?>
			<a class="linsy-contact-widget__item linsy-contact-widget__item--email" href="<?php echo esc_url( 'mailto:' . $email ); ?>" target="_blank" rel="noopener">
				<span class="linsy-contact-widget__icon"><?php echo linsy_contact_widget_inline_svg( 'email' ); ?></span>
				<span class="screen-reader-text"><?php echo esc_html__( 'Email', 'hello-elementor' ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</div>

<div class="linsy-contact-widget linsy-contact-widget__mobile" data-linsy-contact-widget="mobile">
	<div class="linsy-contact-widget__mobile-inner">
		<?php if ( $whatsapp_url ) : ?>
			<a class="linsy-contact-widget__item linsy-contact-widget__item--whatsapp" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener">
				<span class="linsy-contact-widget__icon"><?php echo linsy_contact_widget_inline_svg( 'whatsapp' ); ?></span>
				<span class="screen-reader-text"><?php echo esc_html__( 'WhatsApp', 'hello-elementor' ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( $phone_tel ) : ?>
			<a class="linsy-contact-widget__item linsy-contact-widget__item--phone" href="<?php echo esc_url( 'tel:' . $phone_tel ); ?>">
				<span class="linsy-contact-widget__icon"><?php echo linsy_contact_widget_inline_svg( 'phone' ); ?></span>
				<span class="screen-reader-text"><?php echo esc_html__( 'Phone', 'hello-elementor' ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( $email ) : ?>
			<a class="linsy-contact-widget__item linsy-contact-widget__item--email" href="<?php echo esc_url( 'mailto:' . $email ); ?>" target="_blank" rel="noopener">
				<span class="linsy-contact-widget__icon"><?php echo linsy_contact_widget_inline_svg( 'email' ); ?></span>
				<span class="screen-reader-text"><?php echo esc_html__( 'Email', 'hello-elementor' ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</div>

