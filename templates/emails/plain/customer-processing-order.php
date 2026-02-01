<?php
/**
 * Customer processing order email (plain text).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-processing-order.php.
 *
 * @package WooCommerce\Templates\Emails\Plain
 */

defined( 'ABSPATH' ) || exit;

$email_heading = __( 'Merci pour ta Commande', 'loterie-manager' );

echo '= ' . esc_html( $email_heading ) . " =\n\n";

$first_name = $order ? $order->get_billing_first_name() : '';
$greeting   = $first_name ? sprintf( __( 'Bonjour %s,', 'loterie-manager' ), $first_name ) : __( 'Bonjour,', 'loterie-manager' );

echo esc_html( $greeting ) . "\n\n";
echo esc_html__( 'Nous avons bien reçu ta commande, elle est maintenant en cours de traitement.', 'loterie-manager' ) . "\n\n";

$loterie_names = array();
$ticket_total  = 0;

if ( class_exists( 'Loterie_Manager' ) ) {
    $loterie_names = Loterie_Manager::instance()->get_order_loterie_titles_for_email( $order );
    $ticket_total  = Loterie_Manager::instance()->get_order_ticket_total_for_email( $order );
}

if ( ! empty( $loterie_names ) ) {
    $names_text = implode( ', ', $loterie_names );
    $tickets_text = '';
    if ( $ticket_total > 0 ) {
        $tickets_text = ' ' . sprintf(
            _n( 'avec %d ticket', 'avec %d tickets', $ticket_total, 'loterie-manager' ),
            $ticket_total
        );
    }
    if ( count( $loterie_names ) > 1 ) {
        $sentence = sprintf(
            __( 'Tu es officiellement inscrit pour les concours suivants : %1$s%2$s.', 'loterie-manager' ),
            $names_text,
            $tickets_text
        );
    } else {
        $sentence = sprintf(
            __( 'Tu es officiellement inscrit pour le concours « %1$s »%2$s.', 'loterie-manager' ),
            $names_text,
            $tickets_text
        );
    }

    echo esc_html( $sentence ) . "\n\n";
}

echo esc_html__( 'Voici un petit rappel de ce que tu as commandé :', 'loterie-manager' ) . "\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
    echo "\n" . wp_strip_all_tags( wptexturize( $additional_content ) ) . "\n\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

do_action( 'woocommerce_email_footer', $email );
