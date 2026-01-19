<?php
/**
 * Customer processing order email.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-processing-order.php.
 *
 * @package WooCommerce\Templates\Emails
 */

defined( 'ABSPATH' ) || exit;

$email_heading = __( 'Merci pour ta Commande', 'loterie-manager' );

do_action( 'woocommerce_email_header', $email_heading, $email );

$first_name = $order ? $order->get_billing_first_name() : '';
$greeting   = $first_name ? sprintf( __( 'Bonjour %s,', 'loterie-manager' ), $first_name ) : __( 'Bonjour,', 'loterie-manager' );

echo '<p>' . esc_html( $greeting ) . '</p>';
echo '<p>' . esc_html__( 'Nous avons bien reçu ta commande, elle est maintenant en cours de traitement.', 'loterie-manager' ) . '</p>';

$loterie_names = array();

if ( class_exists( 'Loterie_Manager' ) ) {
    $loterie_names = Loterie_Manager::instance()->get_order_loterie_titles_for_email( $order );
}

if ( ! empty( $loterie_names ) ) {
    $names_text = implode( ', ', $loterie_names );
    if ( count( $loterie_names ) > 1 ) {
        $sentence = sprintf(
            __( 'Tu es officiellement inscrit pour les concours suivants : %s.', 'loterie-manager' ),
            $names_text
        );
    } else {
        $sentence = sprintf(
            __( 'Tu es officiellement inscrit pour le concours « %s ».', 'loterie-manager' ),
            $names_text
        );
    }

    echo '<p>' . esc_html( $sentence ) . '</p>';
}

echo '<p>' . esc_html__( 'Voici un petit rappel de ce que tu as commandé :', 'loterie-manager' ) . '</p>';

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
