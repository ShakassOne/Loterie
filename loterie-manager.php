<?php
/*
Plugin Name: WinShirt Loterie Manager
Plugin URI: https://github.com/ShakassOne/loterie-winshirt
Description: Gestion des loteries pour WooCommerce.
Version: 1.3.13
Author: Shakass Communication
Author URI: https://shakass.com
Text Domain: loterie-winshirt
Domain Path: /languages
*/


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Loterie_Manager' ) ) {

    final class Loterie_Manager {

        const VERSION = '1.3.13';

        /**
         * Meta key storing total ticket capacity for a loterie (post).
         */
        const META_TICKET_CAPACITY = '_lm_ticket_capacity';

        /**
         * Meta key storing prize description for a loterie (post).
         */
        const META_LOT_DESCRIPTION = '_lm_lot_description';

        /**
         * Meta key storing loterie end date.
         */
        const META_END_DATE = '_lm_end_date';

        /**
         * Meta key storing loterie start date.
         */
        const META_START_DATE = '_lm_start_date';

        /**
         * Meta key storing manual status override for a loterie.
         */
        const META_STATUS_OVERRIDE = '_lm_loterie_status';

        /**
         * Meta key storing number of tickets sold.
         */
        const META_TICKETS_SOLD = '_lm_tickets_sold';

        /**
         * Meta key storing the reassignment mode for a specific loterie.
         */
        const META_REASSIGNMENT_MODE = '_lm_reassignment_mode';

        /**
         * Meta key storing audit logs for a loterie.
         */
        const META_AUDIT_LOG = '_lm_audit_log';

        /**
         * Meta key storing draw history for a loterie.
         */
        const META_DRAW_HISTORY = '_lm_draw_history';

        /**
         * Meta key storing manual draw reports for a loterie.
         */
        const META_MANUAL_DRAW_REPORTS = '_lm_manual_draw_reports';

        /**
         * Meta key storing ticket allocation per product purchase.
         */
        const META_PRODUCT_TICKET_ALLOCATION = '_lm_product_ticket_allocation';

        /**
         * Meta key storing loterie targets for a product.
         */
        const META_PRODUCT_TARGET_LOTERIES = '_lm_product_target_lotteries';

        /**
         * Option key storing global lottery settings.
         */
        const OPTION_SETTINGS = 'lm_lottery_settings';

        /**
         * Singleton instance.
         *
         * @var Loterie_Manager
         */
        private static $instance = null;

        /**
         * Cache for loterie statistics during a request lifecycle.
         *
         * @var array<int, array<string, mixed>>
         */
        private $lottery_stats_cache = array();

        /**
         * Cache for the most advanced loterie ID during a request lifecycle.
         *
         * @var int|null
         */
        private $most_advanced_loterie_id = null;

        /**
         * Bootstraps the plugin.
         */
        private function __construct() {
            $this->register_hooks();
        }

        /**
         * Retrieves the plugin instance.
         *
         * @return Loterie_Manager
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Registers WordPress and WooCommerce hooks.
         */
        private function register_hooks() {
            add_action( 'init', array( $this, 'load_textdomain' ) );
            add_action( 'init', array( $this, 'register_account_endpoint' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
            add_action( 'wp_ajax_lm_filter_loteries', array( $this, 'ajax_filter_loteries' ) );
            add_action( 'wp_ajax_nopriv_lm_filter_loteries', array( $this, 'ajax_filter_loteries' ) );

            if ( is_admin() ) {
                add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
                add_action( 'admin_post_lm_toggle_reassignment', array( $this, 'handle_toggle_reassignment' ) );
                add_action( 'admin_post_lm_save_settings', array( $this, 'handle_save_settings' ) );
                add_action( 'admin_post_lm_toggle_loterie_reassignment', array( $this, 'handle_loterie_reassignment_toggle' ) );
                add_action( 'admin_post_lm_export_participants', array( $this, 'handle_export_participants' ) );
                add_action( 'admin_post_lm_manual_draw', array( $this, 'handle_manual_draw' ) );
                add_action( 'admin_post_lm_download_draw_report', array( $this, 'handle_download_draw_report' ) );
                add_action( 'admin_post_lm_reset_lottery_counters', array( $this, 'handle_reset_lottery_counters' ) );
            }

            // Product fields.
            add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_product_fields' ) );
            add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );
            add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_lottery_selection_data' ) );
            add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_hidden_lottery_field' ) );
            add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_lottery_selection' ), 10, 3 );

            // Product add to cart data handling.
            add_filter( 'woocommerce_add_cart_item_data', array( $this, 'append_cart_item_data' ), 10, 3 );
            add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_cart_item_data' ), 10, 3 );
            add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
            add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'store_order_item_data' ), 10, 4 );
            add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'filter_order_item_meta_key' ), 10, 3 );
            add_filter( 'woocommerce_order_item_display_meta_value', array( $this, 'filter_order_item_meta_value' ), 10, 3 );
            add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_item_meta' ) );
            add_action( 'woocommerce_email_after_order_table', array( $this, 'render_email_loterie_thumbnails' ), 15, 4 );
            add_action( 'woocommerce_order_status_completed', array( $this, 'sync_order_ticket_counts' ) );
            add_action( 'woocommerce_order_status_processing', array( $this, 'sync_order_ticket_counts' ) );
            add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );

            // Loterie meta boxes on posts.
            add_action( 'add_meta_boxes', array( $this, 'register_loterie_meta_box' ) );
            add_action( 'save_post_post', array( $this, 'save_loterie_meta' ), 10, 2 );

            // Frontend shortcodes.
            add_shortcode( 'lm_loterie', array( $this, 'render_loterie_shortcode' ) );
            add_shortcode( 'lm_loterie_summary', array( $this, 'render_loterie_summary_shortcode' ) );
            add_shortcode( 'lm_loterie_sold', array( $this, 'render_loterie_sold_shortcode' ) );
            add_shortcode( 'lm_loterie_grid', array( $this, 'render_loterie_grid_shortcode' ) );

            // Account area.
            add_filter( 'woocommerce_account_menu_items', array( $this, 'register_account_menu' ) );
            add_action( 'woocommerce_account_lm-tickets_endpoint', array( $this, 'render_account_tickets' ) );
            add_action( 'template_redirect', array( $this, 'handle_ticket_reassignment' ) );
        }

        /**
         * Loads the plugin textdomain.
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'loterie-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**
         * Registers the "Mes tickets" account endpoint.
         */
        public function register_account_endpoint() {
            add_rewrite_endpoint( 'lm-tickets', EP_ROOT | EP_PAGES );
        }

        /**
         * Enqueues frontend assets.
         */
        public function enqueue_frontend_assets() {
            wp_enqueue_style(
                'loterie-manager-frontend',
                plugins_url( 'assets/css/frontend.css', __FILE__ ),
                array(),
                self::VERSION
            );

            wp_enqueue_script(
                'loterie-manager-frontend',
                plugins_url( 'assets/js/frontend.js', __FILE__ ),
                array( 'jquery' ),
                self::VERSION,
                true
            );

            wp_localize_script(
                'loterie-manager-frontend',
                'LoterieManager',
                array(
                    'i18n' => array(
                        'select_loterie' => __( 'Veuillez sélectionner au moins une loterie.', 'loterie-manager' ),
                        'modal_title'     => __( 'Sélectionnez la loterie pour vos tickets', 'loterie-manager' ),
                        'confirm'         => __( 'Confirmer', 'loterie-manager' ),
                        'cancel'          => __( 'Annuler', 'loterie-manager' ),
                        'ticket_limit_reached_single' => __( 'Vous ne pouvez sélectionner qu\'une loterie pour ce produit.', 'loterie-manager' ),
                        'ticket_limit_reached_plural' => __( 'Vous ne pouvez sélectionner que %s loteries pour ce produit.', 'loterie-manager' ),
                        'default_lottery_label'       => __( 'Loterie #%d', 'loterie-manager' ),
                    ),
                )
            );

            wp_register_script(
                'loterie-manager-lottery-filters',
                plugins_url( 'assets/js/loterie-filters.js', __FILE__ ),
                array( 'jquery' ),
                self::VERSION,
                true
            );

            wp_localize_script(
                'loterie-manager-lottery-filters',
                'LoterieManagerFilters',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'lm_filter_loteries' ),
                    'i18n'     => array(
                        'loading'    => __( 'Chargement des loteries…', 'loterie-manager' ),
                        'no_results' => __( 'Aucune loterie ne correspond à votre recherche.', 'loterie-manager' ),
                        'error'      => __( 'Une erreur est survenue lors du chargement des loteries.', 'loterie-manager' ),
                    ),
                )
            );
        }

        /**
         * Handles AJAX requests for filtering lotteries.
         */
        public function ajax_filter_loteries() {
            if ( ! check_ajax_referer( 'lm_filter_loteries', 'nonce', false ) ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'Jeton de sécurité invalide.', 'loterie-manager' ),
                    ),
                    400
                );
            }

            $status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
            $category = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
            $search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
            $sort     = isset( $_POST['sort'] ) ? sanitize_key( wp_unslash( $_POST['sort'] ) ) : '';
            $layout   = isset( $_POST['layout'] ) ? sanitize_key( wp_unslash( $_POST['layout'] ) ) : '';
            $columns  = isset( $_POST['columns'] ) ? absint( $_POST['columns'] ) : 0;
            $columns_tablet = isset( $_POST['columns_tablet'] ) ? absint( $_POST['columns_tablet'] ) : 0;
            $columns_mobile = isset( $_POST['columns_mobile'] ) ? absint( $_POST['columns_mobile'] ) : 0;
            $manual_order   = isset( $_POST['manual_order'] ) ? (bool) absint( $_POST['manual_order'] ) : false;

            $empty_message = isset( $_POST['empty_message'] ) ? sanitize_text_field( wp_unslash( $_POST['empty_message'] ) ) : '';

            $query_args = array();
            if ( isset( $_POST['query_args'] ) ) {
                $raw_query_args = wp_unslash( $_POST['query_args'] );
                if ( is_string( $raw_query_args ) && '' !== $raw_query_args ) {
                    $decoded = json_decode( $raw_query_args, true );
                    if ( is_array( $decoded ) ) {
                        $query_args = $decoded;
                    }
                }
            }

            $html = $this->get_filtered_lotteries_html(
                array(
                    'status'         => $status,
                    'category'       => $category,
                    'search'         => $search,
                    'sort'           => $sort,
                    'layout'         => $layout,
                    'columns'        => $columns,
                    'columns_tablet' => $columns_tablet,
                    'columns_mobile' => $columns_mobile,
                    'empty_message'  => $empty_message,
                    'manual_order'   => $manual_order,
                    'query_args'     => $query_args,
                )
            );

            wp_send_json_success(
                array(
                    'html' => $html,
                )
            );
        }

        /**
         * Renders WooCommerce product custom fields.
         */
        public function render_product_fields() {
            if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
                return;
            }

            global $post;

            echo '<div class="options_group">';

            woocommerce_wp_text_input(
                array(
                    'id'                => 'lm_ticket_allocation',
                    'label'             => __( 'Tickets attribués', 'loterie-manager' ),
                    'description'       => __( 'Nombre de tickets générés pour chaque achat de ce produit.', 'loterie-manager' ),
                    'desc_tip'          => true,
                    'type'              => 'number',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'step' => '1',
                    ),
                    'value'             => get_post_meta( $post->ID, self::META_PRODUCT_TICKET_ALLOCATION, true ),
                )
            );

            $selected_loteries = (array) get_post_meta( $post->ID, self::META_PRODUCT_TARGET_LOTERIES, true );
            $loteries          = get_posts(
                array(
                    'post_type'      => 'post',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                )
            );

            echo '<p class="form-field"><label for="lm_lottery_targets">' . esc_html__( 'Loteries cibles', 'loterie-manager' ) . '</label>';
            echo '<select id="lm_lottery_targets" name="lm_lottery_targets[]" class="wc-enhanced-select" multiple="multiple" data-placeholder="' . esc_attr__( 'Choisissez les loteries', 'loterie-manager' ) . '">';
            foreach ( $loteries as $loterie ) {
                printf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr( $loterie->ID ),
                    selected( in_array( (string) $loterie->ID, array_map( 'strval', $selected_loteries ), true ), true, false ),
                    esc_html( $loterie->post_title )
                );
            }
            echo '</select>';
            echo '<span class="description">' . esc_html__( 'Sélectionnez les loteries parmi les articles disponibles.', 'loterie-manager' ) . '</span></p>';
            echo '</div>';
        }

        /**
         * Saves WooCommerce product custom fields.
         *
         * @param int $post_id Product ID.
         */
        public function save_product_fields( $post_id ) {
            $ticket_allocation = isset( $_POST['lm_ticket_allocation'] ) ? intval( wp_unslash( $_POST['lm_ticket_allocation'] ) ) : 0;
            update_post_meta( $post_id, self::META_PRODUCT_TICKET_ALLOCATION, max( 0, $ticket_allocation ) );

            $loteries = isset( $_POST['lm_lottery_targets'] ) ? (array) $_POST['lm_lottery_targets'] : array();
            $loteries = array_filter(
                array_map(
                    static function ( $value ) {
                        $value = intval( $value );
                        return $value > 0 ? $value : null;
                    },
                    $loteries
                )
            );

            update_post_meta( $post_id, self::META_PRODUCT_TARGET_LOTERIES, $loteries );
        }

        /**
         * Adds a hidden field to store selected loteries.
         */
        public function render_hidden_lottery_field() {
            echo '<input type="hidden" name="lm_lottery_selection" value="" class="lm-lottery-selection" />';
        }

        /**
         * Parses raw selection data from requests into a flat list of loterie IDs.
         *
         * @param mixed $raw Raw selection payload (string, array or object).
         *
         * @return array<int>
         */
        private function parse_lottery_selection_input( $raw ) {
            if ( is_array( $raw ) ) {
                $values = $raw;
            } elseif ( is_object( $raw ) ) {
                $values = (array) $raw;
            } else {
                $values = array();

                if ( is_string( $raw ) ) {
                    $raw = trim( $raw );

                    if ( '' !== $raw ) {
                        $decoded = null;

                        if ( '[' === $raw[0] || '{' === $raw[0] ) {
                            $decoded = json_decode( $raw, true );
                        }

                        if ( is_array( $decoded ) || is_object( $decoded ) ) {
                            $values = (array) $decoded;
                        } else {
                            $values = explode( ',', $raw );
                        }
                    }
                }
            }

            $flattened = $this->flatten_lottery_selection_values( $values );

            $ids = array_map( 'absint', $flattened );
            $ids = array_filter(
                $ids,
                static function ( $value ) {
                    return $value > 0;
                }
            );

            return array_values( array_unique( $ids ) );
        }

        /**
         * Flattens selection structures to raw scalar values.
         *
         * @param mixed $values Raw selection structure.
         *
         * @return array<int|string>
         */
        private function flatten_lottery_selection_values( $values ) {
            $result = array();

            foreach ( (array) $values as $value ) {
                if ( is_array( $value ) ) {
                    if ( isset( $value['id'] ) ) {
                        $result[] = $value['id'];
                    } else {
                        $result = array_merge( $result, $this->flatten_lottery_selection_values( $value ) );
                    }
                } elseif ( is_object( $value ) ) {
                    if ( isset( $value->id ) ) {
                        $result[] = $value->id;
                    } else {
                        $result = array_merge( $result, $this->flatten_lottery_selection_values( (array) $value ) );
                    }
                } elseif ( is_scalar( $value ) ) {
                    $result[] = $value;
                }
            }

            return $result;
        }

        /**
         * Validates loterie selection when adding a product to cart.
         *
         * @param bool $passed     Validation flag.
         * @param int  $product_id Product ID.
         * @param int  $quantity   Quantity.
         *
         * @return bool
         */
        public function validate_lottery_selection( $passed, $product_id, $quantity ) {
            $targets = (array) get_post_meta( $product_id, self::META_PRODUCT_TARGET_LOTERIES, true );

            if ( empty( $targets ) ) {
                return $passed;
            }

            $selection = $this->parse_lottery_selection_input(
                isset( $_POST['lm_lottery_selection'] ) ? wp_unslash( $_POST['lm_lottery_selection'] ) : array()
            );

            if ( empty( $selection ) ) {
                if ( 1 === count( $targets ) ) {
                    $single_target = absint( reset( $targets ) );
                    $selection     = array( $single_target );
                    $_POST['lm_lottery_selection'] = (string) $single_target;
                } else {
                    if ( function_exists( 'wc_add_notice' ) ) {
                        wc_add_notice( __( 'Veuillez sélectionner au moins une loterie.', 'loterie-manager' ), 'error' );
                    }
                    return false;
                }
            }

            $ticket_limit = intval( get_post_meta( $product_id, self::META_PRODUCT_TICKET_ALLOCATION, true ) );

            if ( $ticket_limit > 0 && count( $selection ) > $ticket_limit ) {
                if ( function_exists( 'wc_add_notice' ) ) {
                    $message = ( 1 === $ticket_limit )
                        ? __( 'Vous ne pouvez sélectionner qu\'une loterie pour ce produit.', 'loterie-manager' )
                        : sprintf( __( 'Vous ne pouvez sélectionner que %d loteries pour ce produit.', 'loterie-manager' ), $ticket_limit );

                    wc_add_notice( $message, 'error' );
                }

                return false;
            }

            return $passed;
        }

        /**
         * Outputs lottery selection data on the product page.
         */
        public function render_lottery_selection_data() {
            if ( ! class_exists( 'WC_Product' ) ) {
                return;
            }

            global $product;

            if ( ! $product instanceof WC_Product ) {
                return;
            }

            $loteries = (array) get_post_meta( $product->get_id(), self::META_PRODUCT_TARGET_LOTERIES, true );

            if ( empty( $loteries ) ) {
                return;
            }

            $data              = array();
            $ticket_limit_raw  = get_post_meta( $product->get_id(), self::META_PRODUCT_TICKET_ALLOCATION, true );
            $ticket_limit_attr = '' === $ticket_limit_raw ? '' : intval( $ticket_limit_raw );
            foreach ( $loteries as $loterie_id ) {
                $post = get_post( $loterie_id );
                if ( ! $post ) {
                    continue;
                }

                $data[] = array(
                    'id'        => $post->ID,
                    'title'     => get_the_title( $post ),
                    'capacity'  => intval( get_post_meta( $post->ID, self::META_TICKET_CAPACITY, true ) ),
                    'sold'      => intval( get_post_meta( $post->ID, self::META_TICKETS_SOLD, true ) ),
                    'start_date'=> get_post_meta( $post->ID, self::META_START_DATE, true ),
                    'end_date'  => get_post_meta( $post->ID, self::META_END_DATE, true ),
                    'prize'     => get_post_meta( $post->ID, self::META_LOT_DESCRIPTION, true ),
                );
            }

            if ( empty( $data ) ) {
                return;
            }

            printf(
                '<div class="lm-lottery-data" data-ticket-limit="%s" data-lotteries="%s"></div>',
                esc_attr( $ticket_limit_attr ),
                esc_attr( wp_json_encode( $data ) )
            );
        }

        /**
         * Adds loterie selection information to cart items.
         *
         * @param array $cart_item_data Current cart data.
         * @param int   $product_id     Product ID.
         * @param int   $variation_id   Variation ID.
         *
         * @return array
         */
        public function append_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
            $selection = $this->parse_lottery_selection_input(
                isset( $_POST['lm_lottery_selection'] ) ? wp_unslash( $_POST['lm_lottery_selection'] ) : array()
            );

            if ( ! empty( $selection ) ) {
                $cart_item_data['lm_lottery_selection'] = array_values( $selection );
            }

            $ticket_allocation = intval( get_post_meta( $product_id, self::META_PRODUCT_TICKET_ALLOCATION, true ) );
            if ( $ticket_allocation > 0 ) {
                $cart_item_data['lm_ticket_allocation'] = $ticket_allocation;
            }

            return $cart_item_data;
        }

        /**
         * Restores cart item data from the session.
         *
         * @param array $cart_item Cart item data.
         * @param array $values    Session values.
         * @param string $key      Cart item key.
         *
         * @return array
         */
        public function restore_cart_item_data( $cart_item, $values, $key ) {
            if ( isset( $values['lm_lottery_selection'] ) ) {
                $cart_item['lm_lottery_selection'] = $values['lm_lottery_selection'];
            }

            if ( isset( $values['lm_ticket_allocation'] ) ) {
                $cart_item['lm_ticket_allocation'] = $values['lm_ticket_allocation'];
            }

            return $cart_item;
        }

        /**
         * Displays cart item data on the cart and checkout pages.
         *
         * @param array $item_data Display data.
         * @param array $cart_item Cart item details.
         *
         * @return array
         */
        public function display_cart_item_data( $item_data, $cart_item ) {
            if ( empty( $cart_item['lm_lottery_selection'] ) ) {
                return $item_data;
            }

            $names = array();
            foreach ( $cart_item['lm_lottery_selection'] as $loterie_id ) {
                $post = get_post( $loterie_id );
                if ( $post ) {
                    $names[] = get_the_title( $post );
                }
            }

            if ( ! empty( $names ) ) {
                $item_data[] = array(
                    'name'  => __( 'Loteries', 'loterie-manager' ),
                    'value' => implode( ', ', array_map( 'esc_html', $names ) ),
                );
            }

            return $item_data;
        }

        /**
         * Adjusts the display key for custom order item meta.
         *
         * @param string        $display_key Display key.
         * @param WC_Meta_Data  $meta        Meta object.
         * @param WC_Order_Item $item        Order item.
         *
         * @return string
         */
        public function filter_order_item_meta_key( $display_key, $meta, $item ) {
            if ( isset( $meta->key ) && 'lm_lottery_selection' === $meta->key ) {
                return __( 'Loteries', 'loterie-manager' );
            }

            if ( isset( $meta->key ) && 'lm_ticket_allocation' === $meta->key ) {
                return __( 'Tickets', 'loterie-manager' );
            }

            return $display_key;
        }

        /**
         * Adjusts the display value for custom order item meta.
         *
         * @param string        $display_value Display value.
         * @param WC_Meta_Data  $meta          Meta object.
         * @param WC_Order_Item $item          Order item.
         *
         * @return string
         */
        public function filter_order_item_meta_value( $display_value, $meta, $item ) {
            if ( isset( $meta->key ) && 'lm_lottery_selection' === $meta->key ) {
                $names = $this->get_order_item_loterie_names( $item );

                if ( ! empty( $names ) ) {
                    $names = array_map( static function ( $name ) {
                        return sanitize_text_field( $name );
                    }, $names );

                    return implode( ', ', $names );
                }

                return '';
            }

            if ( isset( $meta->key ) && 'lm_ticket_allocation' === $meta->key ) {
                $allocation = intval( $meta->value );
                if ( $allocation <= 0 ) {
                    $allocation = 1;
                }

                $quantity = ( $item && method_exists( $item, 'get_quantity' ) ) ? intval( $item->get_quantity() ) : 1;
                if ( $quantity <= 0 ) {
                    $quantity = 1;
                }

                $tickets_total = max( 1, $allocation * $quantity );
                $names         = $this->get_order_item_loterie_names( $item );

                $names = array_map( static function ( $name ) {
                    return sanitize_text_field( $name );
                }, $names );

                if ( empty( $names ) ) {
                    return sprintf(
                        _n( '%d ticket', '%d tickets', $tickets_total, 'loterie-manager' ),
                        $tickets_total
                    );
                }

                $names_list = implode( ', ', $names );

                if ( count( $names ) === 1 ) {
                    return sprintf(
                        _n(
                            '%1$d ticket pour la loterie : %2$s',
                            '%1$d tickets pour la loterie : %2$s',
                            $tickets_total,
                            'loterie-manager'
                        ),
                        $tickets_total,
                        $names_list
                    );
                }

                return sprintf(
                    _n(
                        '%1$d ticket pour les loteries : %2$s',
                        '%1$d tickets pour les loteries : %2$s',
                        $tickets_total,
                        'loterie-manager'
                    ),
                    $tickets_total,
                    $names_list
                );
            }

            return $display_value;
        }

        /**
         * Hides technical order item meta from customer facing views.
         *
         * @param array $hidden_keys Hidden keys.
         *
         * @return array
         */
        public function hide_order_item_meta( $hidden_keys ) {
            $hidden_keys[] = 'lm_ticket_allocation';
            $hidden_keys[] = 'lm_ticket_distribution';

            return array_values( array_unique( $hidden_keys ) );
        }

        /**
         * Retrieves sanitized loterie names for a given order item.
         *
         * @param WC_Order_Item $item Order item.
         *
         * @return array<int, string>
         */
        private function get_order_item_loterie_names( $item ) {
            if ( ! $item ) {
                return array();
            }

            $selection = (array) $item->get_meta( 'lm_lottery_selection', true );
            $selection = array_map( 'intval', $selection );
            $selection = array_filter(
                array_unique( $selection ),
                static function ( $value ) {
                    return $value > 0;
                }
            );

            if ( empty( $selection ) ) {
                return array();
            }

            $names = array();

            foreach ( $selection as $loterie_id ) {
                $title = get_the_title( $loterie_id );

                if ( '' !== $title ) {
                    $names[] = wp_strip_all_tags( $title );
                }
            }

            return $names;
        }

        /**
         * Outputs selected loterie thumbnails in WooCommerce emails.
         *
         * @param WC_Order        $order         Order instance.
         * @param bool            $sent_to_admin Whether the email is sent to an admin.
         * @param bool            $plain_text    Whether the email is in plain text mode.
         * @param WC_Email|string $email         Email object or identifier.
         */
        public function render_email_loterie_thumbnails( $order, $sent_to_admin, $plain_text, $email ) {
            if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                return;
            }

            $loteries = $this->get_order_loterie_details( $order );

            if ( empty( $loteries ) ) {
                return;
            }

            $heading = __( 'Loteries sélectionnées', 'loterie-manager' );

            if ( $plain_text ) {
                echo PHP_EOL . $heading . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                foreach ( $loteries as $loterie ) {
                    $line = '- ' . $loterie['title'];

                    if ( ! empty( $loterie['permalink'] ) ) {
                        $line .= ' <' . esc_url_raw( $loterie['permalink'] ) . '>';
                    }

                    echo $line . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                echo PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                return;
            }

            echo '<div class="lm-email-loteries" style="margin:24px 0 0;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<p style="margin:0 0 12px;font-weight:600;font-size:16px;color:#111;">' . esc_html( $heading ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

            $index = 0;

            foreach ( $loteries as $loterie ) {
                if ( 0 === $index % 3 ) {
                    if ( 0 !== $index ) {
                        echo '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }

                    echo '<tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                $cell_styles = 'padding:0 12px 12px 0;text-align:center;vertical-align:top;';
                echo '<td style="' . esc_attr( $cell_styles ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                $link_open  = '';
                $link_close = '';

                if ( ! empty( $loterie['permalink'] ) ) {
                    $link_open  = '<a href="' . esc_url( $loterie['permalink'] ) . '" style="text-decoration:none;color:#111;">';
                    $link_close = '</a>';
                }

                $image_styles = 'display:block;border-radius:8px;width:120px;max-width:100%;height:auto;border:1px solid #e5e5e5;margin:0 auto 8px;';
                $image_html   = '';

                if ( ! empty( $loterie['image_url'] ) ) {
                    $image_html = '<img src="' . esc_url( $loterie['image_url'] ) . '" alt="' . esc_attr( $loterie['title'] ) . '" style="' . esc_attr( $image_styles ) . '" />';
                }

                echo $link_open . $image_html . '<span style="display:block;font-size:13px;line-height:1.4;margin:0;color:#111;">' . esc_html( $loterie['title'] ) . '</span>' . $link_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                $index++;
            }

            if ( 0 !== $index ) {
                $remaining = $index % 3;

                if ( 0 !== $remaining ) {
                    for ( $i = $remaining; $i < 3; $i++ ) {
                        echo '<td style="padding:0 12px 12px 0;"></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                }

                echo '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            echo '</table></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        /**
         * Collects selected loterie details for a given order.
         *
         * @param WC_Order $order Order instance.
         *
         * @return array<int, array<string, string>>
         */
        private function get_order_loterie_details( $order ) {
            if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                return array();
            }

            $details = array();

            foreach ( $order->get_items() as $item ) {
                $selection = (array) $item->get_meta( 'lm_lottery_selection', true );
                $selection = array_map( 'intval', $selection );
                $selection = array_filter(
                    array_unique( $selection ),
                    static function ( $value ) {
                        return $value > 0;
                    }
                );

                if ( empty( $selection ) ) {
                    continue;
                }

                foreach ( $selection as $loterie_id ) {
                    if ( isset( $details[ $loterie_id ] ) ) {
                        continue;
                    }

                    $post = get_post( $loterie_id );

                    if ( ! $post || 'publish' !== $post->post_status ) {
                        continue;
                    }

                    $title = get_the_title( $post );
                    $title = '' !== $title ? wp_strip_all_tags( $title ) : sprintf( __( 'Loterie #%d', 'loterie-manager' ), $loterie_id );

                    $image_url = get_the_post_thumbnail_url( $post, 'woocommerce_thumbnail' );

                    if ( ! $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
                        $image_url = wc_placeholder_img_src();
                    }

                    $permalink = get_permalink( $post );

                    $details[ $loterie_id ] = array(
                        'title'     => $title,
                        'permalink' => $permalink ? $permalink : '',
                        'image_url' => $image_url ? $image_url : '',
                    );
                }
            }

            return $details;
        }

        /**
         * Stores loterie data on order line items.
         *
         * @param WC_Order_Item_Product $item   Order item instance.
         * @param string                $key    Cart item key.
         * @param array                 $values Cart item values.
         * @param WC_Order              $order  Order instance.
         */
        public function store_order_item_data( $item, $key, $values, $order ) {
            if ( ! empty( $values['lm_lottery_selection'] ) ) {
                $item->add_meta_data( 'lm_lottery_selection', $values['lm_lottery_selection'] );
            }

            $ticket_allocation = isset( $values['lm_ticket_allocation'] ) ? intval( $values['lm_ticket_allocation'] ) : 0;
            if ( $ticket_allocation > 0 ) {
                $item->add_meta_data( 'lm_ticket_allocation', $ticket_allocation );
            }

            $quantity             = isset( $values['quantity'] ) ? intval( $values['quantity'] ) : $item->get_quantity();
            $effective_allocation = $ticket_allocation > 0 ? $ticket_allocation : 1;

            if ( $quantity > 0 && ! empty( $values['lm_lottery_selection'] ) ) {
                $tickets_total = max( 1, $effective_allocation * $quantity );
                $distribution = $this->normalize_ticket_distribution( (array) $values['lm_lottery_selection'], $tickets_total );
                $distribution = array_map( 'intval', $distribution );

                if ( ! empty( $distribution ) ) {
                    $item->add_meta_data( 'lm_ticket_distribution', $distribution );
                }
            }
        }

        /**
         * Synchronises ticket counts for each loterie when an order is completed.
         *
         * @param int $order_id Order ID.
         */
        public function sync_order_ticket_counts( $order_id ) {
            if ( ! function_exists( 'wc_get_order' ) ) {
                return;
            }

            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                return;
            }

            $loterie_ids = array();

            foreach ( $order->get_items() as $item ) {
                $distribution = $this->get_item_ticket_distribution( $item );

                if ( empty( $distribution ) ) {
                    continue;
                }

                foreach ( $distribution as $loterie_id ) {
                    $loterie_id = intval( $loterie_id );
                    if ( $loterie_id <= 0 ) {
                        continue;
                    }

                    $loterie_ids[ $loterie_id ] = true;
                }
            }

            if ( empty( $loterie_ids ) ) {
                return;
            }

            $this->refresh_loterie_counters( array_keys( $loterie_ids ) );

            $order->update_meta_data( '_lm_ticket_counts_synced', 'yes' );
            $order->save();
        }

        /**
         * Registers the loterie meta box on posts.
         */
        public function register_loterie_meta_box() {
            add_meta_box(
                'lm-loterie-meta',
                __( 'Paramètres de loterie', 'loterie-manager' ),
                array( $this, 'render_loterie_meta_box' ),
                'post',
                'normal',
                'default'
            );
        }

        /**
         * Returns manual status options for loterie posts.
         *
         * @return array<string, array<string, string>>
         */
        private function get_loterie_status_options() {
            return array(
                'active'    => array(
                    'label' => __( 'En cours', 'loterie-manager' ),
                    'class' => 'is-active',
                ),
                'upcoming'  => array(
                    'label' => __( 'À venir', 'loterie-manager' ),
                    'class' => 'is-upcoming',
                ),
                'cancelled' => array(
                    'label' => __( 'Annulée', 'loterie-manager' ),
                    'class' => 'is-cancelled',
                ),
                'suspended' => array(
                    'label' => __( 'Suspendue', 'loterie-manager' ),
                    'class' => 'is-suspended',
                ),
                'ended'     => array(
                    'label' => __( 'Terminée', 'loterie-manager' ),
                    'class' => 'is-ended',
                ),
            );
        }

        /**
         * Renders the loterie meta box.
         *
         * @param WP_Post $post Current post.
         */
        public function render_loterie_meta_box( $post ) {
            wp_nonce_field( 'lm_save_loterie_meta', 'lm_loterie_nonce' );

            $capacity   = intval( get_post_meta( $post->ID, self::META_TICKET_CAPACITY, true ) );
            $lot        = get_post_meta( $post->ID, self::META_LOT_DESCRIPTION, true );
            $start_date = get_post_meta( $post->ID, self::META_START_DATE, true );
            $end_date   = get_post_meta( $post->ID, self::META_END_DATE, true );
            $status          = get_post_meta( $post->ID, self::META_STATUS_OVERRIDE, true );
            $status_options  = $this->get_loterie_status_options();

            ?>
            <p>
                <label for="lm_ticket_capacity"><strong><?php esc_html_e( 'Capacité totale de tickets', 'loterie-manager' ); ?></strong></label><br />
                <input type="number" id="lm_ticket_capacity" name="lm_ticket_capacity" min="0" step="1" value="<?php echo esc_attr( $capacity ); ?>" />
            </p>
            <p>
                <label for="lm_loterie_status"><strong><?php esc_html_e( 'Statut affiché', 'loterie-manager' ); ?></strong></label><br />
                <select id="lm_loterie_status" name="lm_loterie_status">
                    <option value=""<?php selected( '', $status ); ?>><?php esc_html_e( 'Automatique (calculé par le plugin)', 'loterie-manager' ); ?></option>
                    <?php foreach ( $status_options as $value => $data ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>"<?php selected( $status, $value ); ?>><?php echo esc_html( $data['label'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="lm_lot_description"><strong><?php esc_html_e( 'Description du lot', 'loterie-manager' ); ?></strong></label><br />
                <textarea id="lm_lot_description" name="lm_lot_description" rows="3" style="width:100%;"><?php echo esc_textarea( $lot ); ?></textarea>
            </p>
            <p>
                <label for="lm_start_date"><strong><?php esc_html_e( 'Date de début', 'loterie-manager' ); ?></strong></label><br />
                <input type="date" id="lm_start_date" name="lm_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
            </p>
            <p>
                <label for="lm_end_date"><strong><?php esc_html_e( 'Date de fin', 'loterie-manager' ); ?></strong></label><br />
                <input type="date" id="lm_end_date" name="lm_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
            </p>
            <?php
        }

        /**
         * Saves loterie metadata.
         *
         * @param int     $post_id Post ID.
         * @param WP_Post $post    Post object.
         */
        public function save_loterie_meta( $post_id, $post ) {
            if ( ! isset( $_POST['lm_loterie_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_loterie_nonce'] ) ), 'lm_save_loterie_meta' ) ) {
                return;
            }

            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            $capacity = isset( $_POST['lm_ticket_capacity'] ) ? intval( wp_unslash( $_POST['lm_ticket_capacity'] ) ) : 0;
            update_post_meta( $post_id, self::META_TICKET_CAPACITY, max( 0, $capacity ) );

            $status_override = isset( $_POST['lm_loterie_status'] ) ? sanitize_key( wp_unslash( $_POST['lm_loterie_status'] ) ) : '';
            $status_options  = $this->get_loterie_status_options();
            if ( '' === $status_override || ! isset( $status_options[ $status_override ] ) ) {
                delete_post_meta( $post_id, self::META_STATUS_OVERRIDE );
            } else {
                update_post_meta( $post_id, self::META_STATUS_OVERRIDE, $status_override );
            }

            $lot = isset( $_POST['lm_lot_description'] ) ? wp_kses_post( wp_unslash( $_POST['lm_lot_description'] ) ) : '';
            update_post_meta( $post_id, self::META_LOT_DESCRIPTION, $lot );

            $start_date = isset( $_POST['lm_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_start_date'] ) ) : '';
            update_post_meta( $post_id, self::META_START_DATE, $start_date );

            $end_date = isset( $_POST['lm_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_end_date'] ) ) : '';
            update_post_meta( $post_id, self::META_END_DATE, $end_date );
        }

        /**
         * Retrieves the most advanced loterie based on tickets sold.
         *
         * @return int Loterie post ID or 0 if none found.
         */
        private function get_most_advanced_loterie_id() {
            if ( null !== $this->most_advanced_loterie_id ) {
                return $this->most_advanced_loterie_id;
            }

            $query = new WP_Query(
                array(
                    'post_type'           => 'post',
                    'post_status'         => 'publish',
                    'posts_per_page'      => 1,
                    'meta_key'            => self::META_TICKETS_SOLD,
                    'orderby'             => 'meta_value_num',
                    'order'               => 'DESC',
                    'ignore_sticky_posts' => true,
                    'no_found_rows'       => true,
                    'fields'              => 'ids',
                    'meta_query'          => array(
                        array(
                            'key'     => self::META_TICKETS_SOLD,
                            'value'   => 0,
                            'compare' => '>=',
                            'type'    => 'NUMERIC',
                        ),
                    ),
                )
            );

            $post_id = 0;

            if ( $query->have_posts() ) {
                $post_id = intval( $query->posts[0] );
            }

            wp_reset_postdata();

            $this->most_advanced_loterie_id = $post_id;

            return $this->most_advanced_loterie_id;
        }

        /**
         * Builds a reusable context for loterie display components.
         *
         * @param int $post_id Loterie post ID.
         *
         * @return array<string, mixed>
         */
        private function get_loterie_display_context( $post_id ) {
            $post_id = absint( $post_id );
            if ( ! $post_id ) {
                return array();
            }

            $stats        = $this->get_lottery_stats( $post_id );
            $capacity     = isset( $stats['capacity'] ) ? intval( $stats['capacity'] ) : intval( get_post_meta( $post_id, self::META_TICKET_CAPACITY, true ) );
            $sold         = isset( $stats['valid_tickets'] ) ? intval( $stats['valid_tickets'] ) : max( 0, intval( get_post_meta( $post_id, self::META_TICKETS_SOLD, true ) ) );
            $lot          = get_post_meta( $post_id, self::META_LOT_DESCRIPTION, true );
            $start_date   = get_post_meta( $post_id, self::META_START_DATE, true );
            $end_date     = get_post_meta( $post_id, self::META_END_DATE, true );
            $is_featured  = (bool) get_post_meta( $post_id, '_lm_is_featured', true );
            $start_time   = $start_date ? strtotime( $start_date ) : get_post_time( 'U', false, $post_id );
            $end_time     = $end_date ? strtotime( $end_date ) : false;
            $now          = current_time( 'timestamp' );
            if ( isset( $stats['status_code'] ) ) {
                $is_active = in_array( $stats['status_code'], array( 'active', 'complete' ), true );
            } else {
                $is_active = ! $start_time || $now >= $start_time;
                if ( $is_active && $end_time ) {
                    $is_active = $end_time >= $now;
                }
            }
            $progress     = isset( $stats['progress'] ) ? floatval( $stats['progress'] ) : ( $capacity > 0 ? min( 100, round( ( $sold / max( $capacity, 1 ) ) * 100, 2 ) ) : 0 );

            $elapsed_days_count = null;
            if ( $start_time ) {
                if ( $now < $start_time ) {
                    $elapsed_days_count = 0;
                } else {
                    $elapsed_days = floor( max( 0, $now - $start_time ) / DAY_IN_SECONDS );
                    $elapsed_days_count = $elapsed_days + 1;
                }
            }

            if ( isset( $stats['status_label'] ) ) {
                $status_label = $stats['status_label'];
            } else {
                $status_label = $is_active ? __( 'Active', 'loterie-manager' ) : __( 'Terminée', 'loterie-manager' );
            }

            if ( isset( $stats['status_class'] ) ) {
                $status_class = $stats['status_class'];
            } else {
                $status_class = $is_active ? 'is-active' : 'is-ended';
            }

            $status_display_code = isset( $stats['status_display_code'] ) ? $stats['status_display_code'] : ( $is_active ? 'active' : 'ended' );
            $status_manual_code  = isset( $stats['status_manual_code'] ) ? $stats['status_manual_code'] : '';

            $lot_value_label = '';
            if ( '' !== $lot && null !== $lot ) {
                $normalized_value = preg_replace( '/[^0-9,\.\-]/', '', (string) $lot );
                $normalized_value = str_replace( ',', '.', $normalized_value );
                if ( is_numeric( $normalized_value ) ) {
                    $value           = floatval( $normalized_value );
                    $decimals        = floor( $value ) !== $value ? 2 : 0;
                    $lot_value_label = sprintf( __( 'Valeur : %s €', 'loterie-manager' ), number_format_i18n( $value, $decimals ) );
                }

                if ( '' === $lot_value_label ) {
                    $lot_value_label = $lot;
                }
            }

            $countdown_boxes = array();
            if ( $is_active && $end_time ) {
                $diff = max( 0, $end_time - $now );

                $days    = floor( $diff / DAY_IN_SECONDS );
                $hours   = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
                $minutes = floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

                $countdown_boxes = array(
                    array(
                        'value' => $days,
                        'label' => __( 'JOURS', 'loterie-manager' ),
                    ),
                    array(
                        'value' => $hours,
                        'label' => __( 'HEURES', 'loterie-manager' ),
                    ),
                    array(
                        'value' => $minutes,
                        'label' => __( 'MINUTES', 'loterie-manager' ),
                    ),
                );
            }

            $ticket_count       = $sold;
            $ticket_count_label = sprintf(
                _n( '%s ticket', '%s tickets', $ticket_count, 'loterie-manager' ),
                number_format_i18n( $ticket_count )
            );

            $goal_label = $capacity > 0
                ? sprintf( __( 'Objectif : %s', 'loterie-manager' ), number_format_i18n( $capacity ) )
                : '';

            if ( $capacity > 0 ) {
                $tickets_label = sprintf(
                    __( '%1$d tickets vendus sur %2$d', 'loterie-manager' ),
                    $sold,
                    $capacity
                );
            } else {
                $tickets_label = sprintf(
                    __( '%d tickets valides', 'loterie-manager' ),
                    $sold
                );
            }

            $formatted_draw_date = $end_time
                ? sprintf( __( 'Tirage le %s', 'loterie-manager' ), date_i18n( get_option( 'date_format' ), $end_time ) )
                : '';

            return array(
                'post_id'            => $post_id,
                'title'              => get_the_title( $post_id ),
                'capacity'           => $capacity,
                'sold'               => $sold,
                'progress'           => $progress,
                'elapsed_days_count' => $elapsed_days_count,
                'lot_value_label'    => $lot_value_label,
                'countdown_boxes'    => $countdown_boxes,
                'ticket_count_label' => $ticket_count_label,
                'goal_label'         => $goal_label,
                'status_label'       => $status_label,
                'status_class'       => $status_class,
                'status_display_code'=> $status_display_code,
                'status_manual_code' => $status_manual_code,
                'is_featured'        => $is_featured,
                'start_date'         => $start_date,
                'end_date'           => $end_date,
                'formatted_draw_date'=> $formatted_draw_date,
                'permalink'          => get_permalink( $post_id ),
                'thumbnail'          => get_the_post_thumbnail( $post_id, 'large', array( 'class' => 'lm-lottery-card__image', 'loading' => 'lazy' ) ),
                'tickets_label'      => $tickets_label,
            );
        }

        /**
         * Renders the HTML card for a loterie based on its context.
         *
         * @param array<string, mixed> $context Loterie context data.
         *
         * @return string
         */
        private function render_loterie_card( $context ) {
            if ( empty( $context ) || empty( $context['post_id'] ) ) {
                return '';
            }

            $post_id            = absint( $context['post_id'] );
            $thumbnail          = isset( $context['thumbnail'] ) ? $context['thumbnail'] : '';
            $status_label       = isset( $context['status_label'] ) ? $context['status_label'] : '';
            $status_class       = isset( $context['status_class'] ) ? $context['status_class'] : '';
            $is_featured        = ! empty( $context['is_featured'] );
            $lot_value_label    = isset( $context['lot_value_label'] ) ? $context['lot_value_label'] : '';
            $countdown_boxes    = isset( $context['countdown_boxes'] ) ? (array) $context['countdown_boxes'] : array();
            $ticket_count_label = isset( $context['ticket_count_label'] ) ? $context['ticket_count_label'] : '';
            $goal_label         = isset( $context['goal_label'] ) ? $context['goal_label'] : '';
            $progress           = isset( $context['progress'] ) ? floatval( $context['progress'] ) : 0.0;
            $formatted_draw_date= isset( $context['formatted_draw_date'] ) ? $context['formatted_draw_date'] : '';
            $permalink          = isset( $context['permalink'] ) ? $context['permalink'] : '';
            $title              = isset( $context['title'] ) ? $context['title'] : '';

            ob_start();
            ?>
            <article class="lm-lottery-card tilt-card<?php echo $is_featured ? ' lm-lottery-card--featured' : ''; ?>" data-loterie-id="<?php echo esc_attr( $post_id ); ?>">
                <div class="tilt-card__inner">
                    <div class="lm-lottery-card__media">
                        <div class="lm-lottery-card__media-ratio">
                            <?php
                            if ( $thumbnail ) {
                                echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            } else {
                                ?>
                                <div class="lm-lottery-card__placeholder" aria-hidden="true"></div>
                                <?php
                            }
                            ?>
                        </div>
                        <?php if ( $status_label ) : ?>
                            <span class="lm-lottery-card__badge lm-lottery-card__badge--status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                        <?php endif; ?>
                        <?php if ( $is_featured ) : ?>
                            <span class="lm-lottery-card__badge lm-lottery-card__badge--featured"><?php esc_html_e( 'En vedette', 'loterie-manager' ); ?></span>
                        <?php endif; ?>
                        <span class="lm-lottery-card__gradient" aria-hidden="true"></span>
                        <div class="lm-lottery-card__info">
                            <?php if ( $title ) : ?>
                                <h3 class="lm-lottery-card__title"><?php echo esc_html( $title ); ?></h3>
                            <?php endif; ?>
                            <?php if ( $lot_value_label ) : ?>
                                <div class="lm-lottery-card__value"><?php echo esc_html( $lot_value_label ); ?></div>
                            <?php endif; ?>

                            <?php if ( ! empty( $countdown_boxes ) ) : ?>
                                <div class="lm-lottery-card__countdown" role="status" aria-live="polite">
                                    <?php foreach ( $countdown_boxes as $box ) :
                                        $value = isset( $box['value'] ) ? $box['value'] : '';
                                        $label = isset( $box['label'] ) ? $box['label'] : '';
                                        ?>
                                        <div class="lm-lottery-card__countdown-box">
                                            <div class="lm-lottery-card__countdown-value"><?php echo esc_html( $value ); ?></div>
                                            <div class="lm-lottery-card__countdown-label"><?php echo esc_html( $label ); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="lm-lottery-card__meta">
                                <svg class="lm-lottery-card__meta-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                    <path d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H6C4.93913 15 3.92172 15.4214 3.17157 16.1716C2.42143 16.9217 2 17.9391 2 19V21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M9 11C10.6569 11 12 9.65685 12 8C12 6.34315 10.6569 5 9 5C7.34315 5 6 6.34315 6 8C6 9.65685 7.34315 11 9 11Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M21 21V19C20.9993 18.1137 20.7044 17.2531 20.1614 16.5499C19.6184 15.8466 18.8572 15.3403 18 15.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M16.5 5.1C17.3604 5.33864 18.1241 5.84529 18.6684 6.55097C19.2127 7.25665 19.5086 8.11976 19.5086 9.01C19.5086 9.90024 19.2127 10.7634 18.6684 11.4691C18.1241 12.1747 17.3604 12.6814 16.5 12.92" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <?php if ( $ticket_count_label ) : ?>
                                    <span class="lm-lottery-card__participants"><?php echo esc_html( $ticket_count_label ); ?></span>
                                <?php endif; ?>
                                <?php if ( $goal_label ) : ?>
                                    <span class="lm-lottery-card__goal"><?php echo esc_html( $goal_label ); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( $progress ); ?>" aria-valuemin="0" aria-valuemax="100">
                                <span class="progress-bar-fill" style="width: <?php echo esc_attr( $progress ); ?>%;"></span>
                            </div>
                        </div>
                    </div>

                    <div class="lm-lottery-card__footer">
                        <div class="lm-lottery-card__footer-date">
                            <svg class="lm-lottery-card__footer-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                <path d="M8 2V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M16 2V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M3.5 9.08997H20.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M21 8.5V18C21 19.1046 20.1046 20 19 20H5C3.89543 20 3 19.1046 3 18V8.5C3 7.39543 3.89543 6.5 5 6.5H19C20.1046 6.5 21 7.39543 21 8.5Z" stroke="currentColor" stroke-width="1.5" />
                                <path d="M7.75 13H7.75999" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M12 13H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M16.25 13H16.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M7.75 16.5H7.75999" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M12 16.5H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M16.25 16.5H16.26" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <?php if ( $formatted_draw_date ) : ?>
                                <span><?php echo esc_html( $formatted_draw_date ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ( $permalink ) : ?>
                            <a class="lm-lottery-card__cta" href="<?php echo esc_url( $permalink ); ?>">
                                <?php esc_html_e( 'Participer', 'loterie-manager' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php

            return (string) ob_get_clean();
        }

        /**
         * Normalizes a grid column value to an integer between 0 and 6.
         *
         * @param mixed $value Raw value.
         *
         * @return int
         */
        private function normalize_grid_columns_value( $value ) {
            $columns = absint( $value );

            if ( $columns < 1 ) {
                return 0;
            }

            return min( $columns, 6 );
        }

        /**
         * Sanitizes query arguments provided by the shortcode wrapper.
         *
         * @param mixed $query_args Raw query arguments.
         *
         * @return array<string, mixed>
         */
        private function sanitize_lottery_query_args( $query_args ) {
            if ( ! is_array( $query_args ) ) {
                return array();
            }

            $sanitized = array();

            if ( isset( $query_args['post_status'] ) ) {
                $statuses = $query_args['post_status'];
                if ( ! is_array( $statuses ) ) {
                    $statuses = array( $statuses );
                }

                $statuses = array_filter(
                    array_map(
                        static function ( $status ) {
                            return sanitize_key( (string) $status );
                        },
                        $statuses
                    )
                );

                if ( ! empty( $statuses ) ) {
                    $sanitized['post_status'] = array_values( array_unique( $statuses ) );
                }
            }

            if ( isset( $query_args['posts_per_page'] ) ) {
                $posts_per_page = intval( $query_args['posts_per_page'] );
                if ( 0 === $posts_per_page ) {
                    $posts_per_page = -1;
                }

                $sanitized['posts_per_page'] = $posts_per_page;
            }

            if ( isset( $query_args['post__in'] ) ) {
                $post_in = array_filter( array_map( 'absint', (array) $query_args['post__in'] ) );
                if ( ! empty( $post_in ) ) {
                    $sanitized['post__in'] = array_values( array_unique( $post_in ) );
                }
            }

            if ( isset( $query_args['post__not_in'] ) ) {
                $post_not_in = array_filter( array_map( 'absint', (array) $query_args['post__not_in'] ) );
                if ( ! empty( $post_not_in ) ) {
                    $sanitized['post__not_in'] = array_values( array_unique( $post_not_in ) );
                }
            }

            if ( isset( $query_args['category_name'] ) ) {
                $categories = array_filter( array_map( 'sanitize_title', preg_split( '/[\s,]+/', (string) $query_args['category_name'] ) ) );
                if ( ! empty( $categories ) ) {
                    $sanitized['category_name'] = implode( ',', $categories );
                }
            }

            if ( isset( $query_args['orderby'] ) ) {
                $allowed_orderby = array( 'post__in', 'date', 'title', 'menu_order', 'modified', 'rand' );
                $orderby         = strtolower( (string) $query_args['orderby'] );

                if ( in_array( $orderby, $allowed_orderby, true ) ) {
                    $sanitized['orderby'] = $orderby;
                }
            }

            if ( isset( $query_args['order'] ) ) {
                $order = strtoupper( (string) $query_args['order'] );
                if ( in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
                    $sanitized['order'] = $order;
                }
            }

            return $sanitized;
        }

        /**
         * Builds the HTML markup for a collection of loterie cards.
         *
         * @param array<int, string> $cards           Rendered cards.
         * @param string             $layout          Layout identifier.
         * @param int                $columns         Desktop columns.
         * @param int                $columns_tablet  Tablet columns.
         * @param int                $columns_mobile  Mobile columns.
         *
         * @return string
         */
        private function render_lottery_collection_markup( $cards, $layout, $columns, $columns_tablet, $columns_mobile ) {
            if ( empty( $cards ) ) {
                return '';
            }

            $layout = ( 'grid' === $layout ) ? 'grid' : 'list';

            if ( 'grid' !== $layout ) {
                $items = array();
                foreach ( $cards as $card_html ) {
                    if ( '' === trim( $card_html ) ) {
                        continue;
                    }

                    $items[] = '<div class="lm-lottery-list__item">' . $card_html . '</div>';
                }

                if ( empty( $items ) ) {
                    return '';
                }

                return '<div class="lm-lottery-list__items">' . implode( '', $items ) . '</div>';
            }

            $columns        = $this->normalize_grid_columns_value( $columns );
            $columns_tablet = $this->normalize_grid_columns_value( $columns_tablet );
            $columns_mobile = $this->normalize_grid_columns_value( $columns_mobile );

            if ( $columns > 0 && 0 === $columns_tablet ) {
                $columns_tablet = min( $columns, 3 );
            }

            if ( ( $columns > 0 || $columns_tablet > 0 ) && 0 === $columns_mobile ) {
                $columns_mobile = 1;
            }

            $grid_classes = array( 'lm-lottery-grid' );
            $grid_styles  = array();

            if ( $columns > 0 || $columns_tablet > 0 || $columns_mobile > 0 ) {
                $grid_classes[] = 'lm-lottery-grid--has-config';
            }

            if ( $columns > 0 ) {
                $grid_styles[] = '--lm-grid-template-columns: repeat(' . $columns . ', minmax(0, 1fr))';
                $grid_styles[] = '--lm-grid-min-width: 0px';
            }

            if ( $columns_tablet > 0 ) {
                $grid_styles[] = '--lm-grid-template-columns-tablet: repeat(' . $columns_tablet . ', minmax(0, 1fr))';
            }

            if ( $columns_mobile > 0 ) {
                $grid_styles[] = '--lm-grid-template-columns-mobile: repeat(' . $columns_mobile . ', minmax(0, 1fr))';
            }

            $items = array();
            foreach ( $cards as $card_html ) {
                if ( '' === trim( $card_html ) ) {
                    continue;
                }

                $items[] = '<div class="lm-lottery-grid__item">' . $card_html . '</div>';
            }

            if ( empty( $items ) ) {
                return '';
            }

            $attributes = array(
                'class' => implode( ' ', array_map( 'sanitize_html_class', $grid_classes ) ),
            );

            if ( ! empty( $grid_styles ) ) {
                $attributes['style'] = implode( '; ', $grid_styles );
            }

            $attribute_string = '';
            foreach ( $attributes as $attribute => $value ) {
                if ( '' === $value ) {
                    continue;
                }

                $attribute_string .= sprintf( ' %s="%s"', $attribute, esc_attr( $value ) );
            }

            return '<div' . $attribute_string . '>' . implode( '', $items ) . '</div>';
        }

        /**
         * Renders loterie data via shortcode.
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_loterie_shortcode( $atts ) {
            $atts = shortcode_atts(
                array(
                    'id'   => '',
                    'sort' => 'date_desc',
                ),
                $atts,
                'lm_loterie'
            );

            $lottery_id = $atts['id'];

            if ( '' !== $lottery_id ) {
                if ( 'most_advanced' === $lottery_id ) {
                    $lottery_id = $this->get_most_advanced_loterie_id();
                }

                $lottery_id = absint( $lottery_id );

                if ( ! $lottery_id ) {
                    return '';
                }

                $context = $this->get_loterie_display_context( $lottery_id );
                if ( empty( $context ) ) {
                    return '';
                }

                return $this->render_loterie_card( $context );
            }

            wp_enqueue_script( 'loterie-manager-lottery-filters' );

            $sort_key     = $this->normalize_loterie_sort_key( $atts['sort'] );
            $sort_options = $this->get_loterie_sort_options();
            $categories   = get_categories(
                array(
                    'hide_empty' => true,
                )
            );

            $form_uid      = uniqid( 'lm-lottery-filters-' );
            $status_field  = $form_uid . '-status';
            $category_field = $form_uid . '-category';
            $search_field  = $form_uid . '-search';
            $sort_field    = $form_uid . '-sort';

            $initial_html = $this->get_filtered_lotteries_html(
                array(
                    'sort' => $sort_key,
                )
            );

            ob_start();
            ?>
            <div class="lm-lottery-list" data-default-sort="<?php echo esc_attr( $sort_key ); ?>">
                <form class="lm-lottery-filters" action="#" method="post" novalidate>
                    <div class="lm-lottery-filters__field lm-lottery-filters__field--status">
                        <label for="<?php echo esc_attr( $status_field ); ?>"><?php esc_html_e( 'Statut des loteries', 'loterie-manager' ); ?></label>
                        <select id="<?php echo esc_attr( $status_field ); ?>" name="status">
                            <option value="" selected="selected"><?php esc_html_e( 'Tous les statuts', 'loterie-manager' ); ?></option>
                            <option value="active"><?php esc_html_e( 'En cours', 'loterie-manager' ); ?></option>
                            <option value="upcoming"><?php esc_html_e( 'À venir', 'loterie-manager' ); ?></option>
                            <option value="cancelled"><?php esc_html_e( 'Annulées', 'loterie-manager' ); ?></option>
                            <option value="suspended"><?php esc_html_e( 'Suspendues', 'loterie-manager' ); ?></option>
                            <option value="ended"><?php esc_html_e( 'Terminées', 'loterie-manager' ); ?></option>
                        </select>
                    </div>

                    <div class="lm-lottery-filters__field lm-lottery-filters__field--category">
                        <label for="<?php echo esc_attr( $category_field ); ?>"><?php esc_html_e( 'Catégorie', 'loterie-manager' ); ?></label>
                        <select id="<?php echo esc_attr( $category_field ); ?>" name="category">
                            <option value="" selected="selected"><?php esc_html_e( 'Toutes les catégories', 'loterie-manager' ); ?></option>
                            <?php foreach ( $categories as $category ) : ?>
                                <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="lm-lottery-filters__field lm-lottery-filters__field--search">
                        <label for="<?php echo esc_attr( $search_field ); ?>"><?php esc_html_e( 'Recherche', 'loterie-manager' ); ?></label>
                        <input type="search" id="<?php echo esc_attr( $search_field ); ?>" name="search" placeholder="<?php esc_attr_e( 'Rechercher une loterie', 'loterie-manager' ); ?>" />
                    </div>

                    <div class="lm-lottery-filters__field lm-lottery-filters__field--sort">
                        <label for="<?php echo esc_attr( $sort_field ); ?>"><?php esc_html_e( 'Trier par', 'loterie-manager' ); ?></label>
                        <select id="<?php echo esc_attr( $sort_field ); ?>" name="sort">
                            <?php foreach ( $sort_options as $key => $option ) :
                                $label = isset( $option['label'] ) ? $option['label'] : $key;
                                ?>
                                <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $sort_key, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="lm-lottery-filters__actions">
                        <button type="submit" class="lm-lottery-filters__submit"><?php esc_html_e( 'Filtrer', 'loterie-manager' ); ?></button>
                        <button type="reset" class="lm-lottery-filters__reset"><?php esc_html_e( 'Réinitialiser', 'loterie-manager' ); ?></button>
                    </div>
                </form>

                <div class="lm-lottery-list__loading" role="status" aria-live="polite" aria-hidden="true"></div>
                <div class="lm-lottery-list__results" aria-live="polite" aria-busy="false">
                    <?php echo $initial_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        /**
         * Normalizes the requested sort key.
         *
         * @param string $sort Requested sort key.
         *
         * @return string
         */
        private function normalize_loterie_sort_key( $sort ) {
            $sort      = sanitize_key( (string) $sort );
            $options   = $this->get_loterie_sort_options();

            return isset( $options[ $sort ] ) ? $sort : 'date_desc';
        }

        /**
         * Returns available sort options for the front-end filters.
         *
         * @return array<string, array<string, mixed>>
         */
        private function get_loterie_sort_options() {
            return array(
                'date_desc'  => array(
                    'orderby' => 'date',
                    'order'   => 'DESC',
                    'label'   => __( 'Plus récentes', 'loterie-manager' ),
                ),
                'date_asc'   => array(
                    'orderby' => 'date',
                    'order'   => 'ASC',
                    'label'   => __( 'Plus anciennes', 'loterie-manager' ),
                ),
                'title_asc'  => array(
                    'orderby' => 'title',
                    'order'   => 'ASC',
                    'label'   => __( 'Titre A → Z', 'loterie-manager' ),
                ),
                'title_desc' => array(
                    'orderby' => 'title',
                    'order'   => 'DESC',
                    'label'   => __( 'Titre Z → A', 'loterie-manager' ),
                ),
            );
        }

        /**
         * Normalizes the loterie status filter.
         *
         * @param string $status Requested status filter.
         *
         * @return string
         */
        private function normalize_loterie_status_filter( $status ) {
            $status   = sanitize_key( (string) $status );
            $allowed  = array( 'active', 'ended', 'upcoming', 'cancelled', 'suspended' );

            return in_array( $status, $allowed, true ) ? $status : '';
        }

        /**
         * Generates the filtered loterie list markup.
         *
         * @param array<string, mixed> $args Filter arguments.
         *
         * @return string
         */
        private function get_filtered_lotteries_html( $args = array() ) {
            $defaults = array(
                'status'         => '',
                'category'       => 0,
                'search'         => '',
                'sort'           => 'date_desc',
                'layout'         => 'list',
                'columns'        => 0,
                'columns_tablet' => 0,
                'columns_mobile' => 0,
                'empty_message'  => __( 'Aucune loterie ne correspond à votre recherche.', 'loterie-manager' ),
                'manual_order'   => false,
                'query_args'     => array(),
            );

            $args = wp_parse_args( $args, $defaults );

            $status        = $this->normalize_loterie_status_filter( $args['status'] );
            $category      = absint( $args['category'] );
            $search        = sanitize_text_field( (string) $args['search'] );
            $sort_key      = $this->normalize_loterie_sort_key( $args['sort'] );
            $layout        = sanitize_key( (string) $args['layout'] );
            $columns       = $this->normalize_grid_columns_value( $args['columns'] );
            $columns_tablet = $this->normalize_grid_columns_value( $args['columns_tablet'] );
            $columns_mobile = $this->normalize_grid_columns_value( $args['columns_mobile'] );
            $manual_order  = (bool) $args['manual_order'];
            $empty_message = (string) $args['empty_message'];

            if ( $columns > 0 && 0 === $columns_tablet ) {
                $columns_tablet = min( $columns, 3 );
            }

            if ( ( $columns > 0 || $columns_tablet > 0 ) && 0 === $columns_mobile ) {
                $columns_mobile = 1;
            }

            $sort_options = $this->get_loterie_sort_options();
            $sort_config  = isset( $sort_options[ $sort_key ] ) ? $sort_options[ $sort_key ] : $sort_options['date_desc'];

            $query_args = array(
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => -1,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            );

            $custom_query_args = $this->sanitize_lottery_query_args( $args['query_args'] );
            if ( ! empty( $custom_query_args ) ) {
                $query_args = wp_parse_args( $custom_query_args, $query_args );
            }

            $apply_sort = true;
            if ( $manual_order && isset( $query_args['post__in'] ) && 'date_desc' === $sort_key ) {
                $apply_sort = false;
            }

            if ( $apply_sort ) {
                $query_args['orderby'] = $sort_config['orderby'];

                if ( isset( $sort_config['order'] ) && 'rand' !== $sort_config['orderby'] ) {
                    $query_args['order'] = $sort_config['order'];
                } else {
                    unset( $query_args['order'] );
                }
            }

            if ( '' !== $search ) {
                $query_args['s'] = $search;
            }

            if ( $category > 0 ) {
                $query_args['cat'] = $category;
            }

            $query = new WP_Query( $query_args );

            $cards = array();
            $now   = current_time( 'timestamp' );

            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();

                    $context = $this->get_loterie_display_context( get_the_ID() );
                    if ( empty( $context ) ) {
                        continue;
                    }

                    $is_active    = $this->is_context_active( $context, $now );
                    $display_code = isset( $context['status_display_code'] ) ? $context['status_display_code'] : ( $is_active ? 'active' : 'ended' );

                    if ( '' !== $status && $status !== $display_code ) {
                        continue;
                    }

                    $card_html = $this->render_loterie_card( $context );
                    if ( '' === $card_html ) {
                        continue;
                    }

                    $cards[] = $card_html;
                }
            }

            wp_reset_postdata();

            if ( empty( $cards ) ) {
                if ( '' === $empty_message ) {
                    return '';
                }

                return '<p class="lm-lottery-list__empty">' . esc_html( $empty_message ) . '</p>';
            }

            $markup = $this->render_lottery_collection_markup( $cards, $layout, $columns, $columns_tablet, $columns_mobile );

            if ( '' === $markup ) {
                if ( '' === $empty_message ) {
                    return '';
                }

                return '<p class="lm-lottery-list__empty">' . esc_html( $empty_message ) . '</p>';
            }

            return $markup;
        }

        /**
         * Determines whether a loterie is currently active.
         *
         * @param int      $post_id   Loterie post ID.
         * @param int|null $timestamp Reference timestamp.
         *
         * @return bool
         */
        private function is_loterie_active( $post_id, $timestamp = null ) {
            $post_id = absint( $post_id );
            if ( ! $post_id ) {
                return false;
            }

            if ( null === $timestamp ) {
                $timestamp = current_time( 'timestamp' );
            }

            $stats = $this->get_lottery_stats( $post_id );
            if ( isset( $stats['status_display_code'] ) && '' !== $stats['status_display_code'] ) {
                return 'active' === $stats['status_display_code'];
            }

            if ( isset( $stats['status_code'] ) ) {
                return in_array( $stats['status_code'], array( 'active', 'complete' ), true );
            }

            $end_date = get_post_meta( $post_id, self::META_END_DATE, true );
            $end_time = $end_date ? strtotime( $end_date ) : false;

            if ( ! $end_time ) {
                return true;
            }

            return $end_time >= $timestamp;
        }

        /**
         * Determines whether the provided loterie context is active.
         *
         * @param array<string, mixed> $context Loterie context.
         * @param int|null             $timestamp Reference timestamp.
         *
         * @return bool
         */
        private function is_context_active( $context, $timestamp = null ) {
            if ( isset( $context['status_display_code'] ) && '' !== $context['status_display_code'] ) {
                if ( 'active' === $context['status_display_code'] ) {
                    return true;
                }

                if ( in_array( $context['status_display_code'], array( 'upcoming', 'cancelled', 'suspended', 'ended', 'draft' ), true ) ) {
                    return false;
                }
            }

            if ( isset( $context['status_class'] ) ) {
                $status_class = (string) $context['status_class'];

                if ( false !== strpos( $status_class, 'is-active' ) ) {
                    return true;
                }

                if ( false !== strpos( $status_class, 'is-ended' ) ) {
                    return false;
                }
            }

            $post_id = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;

            return $this->is_loterie_active( $post_id, $timestamp );
        }

        /**
         * Renders a grid of loteries via shortcode.
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_loterie_grid_shortcode( $atts ) {
            $atts = shortcode_atts(
                array(
                    'posts_per_page' => -1,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'category'       => '',
                    'ids'            => '',
                    'exclude'        => '',
                    'status'         => 'publish',
                    'empty_message'  => __( 'Aucune loterie disponible pour le moment.', 'loterie-manager' ),
                    'columns'        => '',
                    'columns_tablet' => '',
                    'columns_mobile' => '',
                ),
                $atts,
                'lm_loterie_grid'
            );

            $posts_per_page = intval( $atts['posts_per_page'] );
            if ( 0 === $posts_per_page ) {
                $posts_per_page = -1;
            }

            $allowed_orderby = array( 'date', 'title' );
            $orderby         = in_array( $atts['orderby'], $allowed_orderby, true ) ? $atts['orderby'] : 'date';

            $order = strtoupper( $atts['order'] );
            $order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

            $statuses = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) $atts['status'] ) ) ) );
            if ( empty( $statuses ) ) {
                $statuses = array( 'publish' );
            }

            $ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $atts['ids'] ) ) );
            $exclude = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $atts['exclude'] ) ) );
            $category_slugs = array_filter( array_map( 'sanitize_title', preg_split( '/[\s,]+/', (string) $atts['category'] ) ) );

            $columns        = $this->normalize_grid_columns_value( $atts['columns'] );
            $columns_tablet = $this->normalize_grid_columns_value( $atts['columns_tablet'] );
            $columns_mobile = $this->normalize_grid_columns_value( $atts['columns_mobile'] );

            if ( $columns > 0 && 0 === $columns_tablet ) {
                $columns_tablet = min( $columns, 3 );
            }

            if ( ( $columns > 0 || $columns_tablet > 0 ) && 0 === $columns_mobile ) {
                $columns_mobile = 1;
            }

            $sort_key = 'date_desc';
            if ( 'date' === $orderby ) {
                $sort_key = ( 'ASC' === $order ) ? 'date_asc' : 'date_desc';
            } elseif ( 'title' === $orderby ) {
                $sort_key = ( 'ASC' === $order ) ? 'title_asc' : 'title_desc';
            }

            $manual_order = false;

            $query_overrides = array(
                'post_status'    => $statuses,
                'posts_per_page' => $posts_per_page,
            );

            if ( ! empty( $ids ) ) {
                $query_overrides['post__in'] = $ids;
                $query_overrides['orderby']  = 'post__in';
                $manual_order = true;
            }

            if ( ! empty( $exclude ) ) {
                $query_overrides['post__not_in'] = $exclude;
            }

            if ( ! empty( $category_slugs ) ) {
                $query_overrides['category_name'] = implode( ',', $category_slugs );
            }

            wp_enqueue_script( 'loterie-manager-lottery-filters' );

            $sort_options = $this->get_loterie_sort_options();
            $categories   = get_categories(
                array(
                    'hide_empty' => true,
                )
            );

            $form_uid       = uniqid( 'lm-lottery-filters-' );
            $status_field   = $form_uid . '-status';
            $category_field = $form_uid . '-category';
            $search_field   = $form_uid . '-search';
            $sort_field     = $form_uid . '-sort';

            $initial_html = $this->get_filtered_lotteries_html(
                array(
                    'sort'           => $sort_key,
                    'layout'         => 'grid',
                    'columns'        => $columns,
                    'columns_tablet' => $columns_tablet,
                    'columns_mobile' => $columns_mobile,
                    'empty_message'  => $atts['empty_message'],
                    'manual_order'   => $manual_order,
                    'query_args'     => $query_overrides,
                )
            );

            $wrapper_attributes = array(
                'class'              => 'lm-lottery-list lm-lottery-list--grid',
                'data-default-sort'  => $sort_key,
                'data-layout'        => 'grid',
                'data-columns'       => $columns,
                'data-columns-tablet'=> $columns_tablet,
                'data-columns-mobile'=> $columns_mobile,
                'data-empty-message' => $atts['empty_message'],
                'data-manual-order'  => $manual_order ? '1' : '',
            );

            $query_args_json = wp_json_encode( $query_overrides );
            if ( $query_args_json ) {
                $wrapper_attributes['data-query-args'] = $query_args_json;
            }

            if ( $columns <= 0 ) {
                unset( $wrapper_attributes['data-columns'] );
            }

            if ( $columns_tablet <= 0 ) {
                unset( $wrapper_attributes['data-columns-tablet'] );
            }

            if ( $columns_mobile <= 0 ) {
                unset( $wrapper_attributes['data-columns-mobile'] );
            }

            if ( ! $manual_order ) {
                unset( $wrapper_attributes['data-manual-order'] );
            }

            ob_start();
            ?>
            <div<?php foreach ( $wrapper_attributes as $attribute => $value ) :
                if ( '' === $value && 'data-empty-message' !== $attribute ) {
                    continue;
                }
                printf( ' %s="%s"', esc_attr( $attribute ), esc_attr( (string) $value ) );
            endforeach; ?>>
                <form class="lm-lottery-filters" action="#" method="post" novalidate>
                    <div class="lm-lottery-filters__field lm-lottery-filters__field--status">
                        <label for="<?php echo esc_attr( $status_field ); ?>"><?php esc_html_e( 'Statut des loteries', 'loterie-manager' ); ?></label>
                        <select id="<?php echo esc_attr( $status_field ); ?>" name="status">
                            <option value="" selected="selected"><?php esc_html_e( 'Tous les statuts', 'loterie-manager' ); ?></option>
                            <option value="active"><?php esc_html_e( 'En cours', 'loterie-manager' ); ?></option>
                            <option value="upcoming"><?php esc_html_e( 'À venir', 'loterie-manager' ); ?></option>
                            <option value="cancelled"><?php esc_html_e( 'Annulées', 'loterie-manager' ); ?></option>
                            <option value="suspended"><?php esc_html_e( 'Suspendues', 'loterie-manager' ); ?></option>
                            <option value="ended"><?php esc_html_e( 'Terminées', 'loterie-manager' ); ?></option>
                        </select>
                    </div>

                    <div class="lm-lottery-filters__field lm-lottery-filters__field--category">
                        <label for="<?php echo esc_attr( $category_field ); ?>"><?php esc_html_e( 'Catégorie', 'loterie-manager' ); ?></label>
                        <select id="<?php echo esc_attr( $category_field ); ?>" name="category">
                            <option value="" selected="selected"><?php esc_html_e( 'Toutes les catégories', 'loterie-manager' ); ?></option>
                            <?php foreach ( $categories as $category ) : ?>
                                <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="lm-lottery-filters__field lm-lottery-filters__field--search">
                        <label for="<?php echo esc_attr( $search_field ); ?>"><?php esc_html_e( 'Recherche', 'loterie-manager' ); ?></label>
                        <input type="search" id="<?php echo esc_attr( $search_field ); ?>" name="search" placeholder="<?php esc_attr_e( 'Rechercher une loterie', 'loterie-manager' ); ?>" />
                    </div>

                    <div class="lm-lottery-filters__field lm-lottery-filters__field--sort">
                        <label for="<?php echo esc_attr( $sort_field ); ?>"><?php esc_html_e( 'Trier par', 'loterie-manager' ); ?></label>
                        <select id="<?php echo esc_attr( $sort_field ); ?>" name="sort">
                            <?php foreach ( $sort_options as $key => $option ) :
                                $label = isset( $option['label'] ) ? $option['label'] : $key;
                                ?>
                                <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $sort_key, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="lm-lottery-filters__actions">
                        <button type="submit" class="lm-lottery-filters__submit"><?php esc_html_e( 'Filtrer', 'loterie-manager' ); ?></button>
                        <button type="reset" class="lm-lottery-filters__reset"><?php esc_html_e( 'Réinitialiser', 'loterie-manager' ); ?></button>
                    </div>
                </form>

                <div class="lm-lottery-list__loading" role="status" aria-live="polite" aria-hidden="true"></div>
                <div class="lm-lottery-list__results" aria-live="polite" aria-busy="false">
                    <?php echo $initial_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        /**
         * Renders a textual loterie summary via shortcode.
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_loterie_summary_shortcode( $atts ) {
            $atts = shortcode_atts(
                array(
                    'id' => get_the_ID(),
                ),
                $atts
            );

            $context = $this->get_loterie_display_context( $atts['id'] );
            if ( empty( $context ) ) {
                return '';
            }

            $post_id         = $context['post_id'];
            $capacity        = $context['capacity'];
            $sold            = $context['sold'];
            $countdown_boxes = $context['countdown_boxes'];

            $has_elapsed_days   = array_key_exists( 'elapsed_days_count', $context );
            $raw_elapsed        = $has_elapsed_days ? $context['elapsed_days_count'] : null;
            $elapsed_days_count = null === $raw_elapsed ? null : absint( $raw_elapsed );

            $day_text = '';
            if ( null !== $elapsed_days_count ) {
                $day_text = sprintf( __( 'Jour %s', 'loterie-manager' ), number_format_i18n( $elapsed_days_count ) );
            }

            $goal_text = '';
            $remaining_number = '';
            $remaining_label  = '';
            $remaining_text   = '';
            if ( $capacity > 0 ) {
                $remaining_count = max( 0, $capacity - $sold );
                $remaining_label = _n( 'article restant', 'articles restants', $remaining_count, 'loterie-manager' );
                $remaining_number = number_format_i18n( $remaining_count );
                $goal_text = sprintf(
                    __( 'sur %s', 'loterie-manager' ),
                    number_format_i18n( $capacity )
                );
            } else {
                $remaining_text = $context['tickets_label'];
            }

            ob_start();
            ?>
            <section class="lm-loterie-banner" data-loterie-id="<?php echo esc_attr( $post_id ); ?>">
                <?php if ( '' !== $day_text ) : ?>
                    <span class="lm-loterie-banner__day"><?php echo esc_html( $day_text ); ?></span>
                <?php endif; ?>

                <?php if ( '' !== $day_text && ( $remaining_number || $remaining_text ) ) : ?>
                    <span class="lm-loterie-banner__separator" aria-hidden="true">:</span>
                <?php endif; ?>

                <?php if ( $remaining_number ) : ?>
                    <span class="lm-loterie-banner__remaining">
                        <span class="lm-loterie-banner__remaining-number"><?php echo esc_html( $remaining_number ); ?></span>
                        <?php if ( $remaining_label ) : ?>
                            <span class="lm-loterie-banner__remaining-label"><?php echo esc_html( $remaining_label ); ?></span>
                        <?php endif; ?>
                    </span>
                <?php elseif ( $remaining_text ) : ?>
                    <span class="lm-loterie-banner__remaining"><?php echo esc_html( $remaining_text ); ?></span>
                <?php endif; ?>

                <?php if ( $goal_text ) : ?>
                    <span class="lm-loterie-banner__goal"><?php echo esc_html( $goal_text ); ?></span>
                <?php endif; ?>
            </section>
            <?php

            return (string) ob_get_clean();
        }

        /**
         * Renders the sold ticket count for a loterie via shortcode.
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_loterie_sold_shortcode( $atts ) {
            $atts = shortcode_atts(
                array(
                    'id' => get_the_ID(),
                ),
                $atts
            );

            $context = $this->get_loterie_display_context( $atts['id'] );
            if ( empty( $context ) ) {
                return '';
            }

            $sold = isset( $context['sold'] ) ? absint( $context['sold'] ) : 0;

            return (string) number_format_i18n( $sold );
        }

        /**
         * Modifies the WooCommerce account menu.
         *
         * @param array $items Menu items.
         *
         * @return array
         */
        public function register_account_menu( $items ) {
            $new = array();
            foreach ( $items as $key => $label ) {
                $new[ $key ] = $label;
                if ( 'orders' === $key ) {
                    $new['lm-tickets'] = __( 'Mes tickets', 'loterie-manager' );
                }
            }

            if ( ! isset( $new['lm-tickets'] ) ) {
                $new['lm-tickets'] = __( 'Mes tickets', 'loterie-manager' );
            }

            return $new;
        }

        /**
         * Renders the "Mes tickets" account page.
         */
        public function render_account_tickets() {
            if ( ! is_user_logged_in() ) {
                echo '<p>' . esc_html__( 'Vous devez être connecté pour consulter vos tickets.', 'loterie-manager' ) . '</p>';
                return;
            }

            $user_id  = get_current_user_id();
            $tickets  = $this->get_customer_ticket_summary( $user_id );

            if ( empty( $tickets ) ) {
                echo '<p>' . esc_html__( 'Aucun ticket enregistré pour le moment.', 'loterie-manager' ) . '</p>';
                return;
            }

            $selected_loterie = isset( $_GET['lm_filter_loterie'] ) ? absint( $_GET['lm_filter_loterie'] ) : 0;
            $selected_status  = isset( $_GET['lm_filter_status'] ) ? sanitize_key( wp_unslash( $_GET['lm_filter_status'] ) ) : '';
            $search_term      = isset( $_GET['lm_ticket_search'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_ticket_search'] ) ) : '';

            $filtered        = array();
            $lottery_options = array();

            foreach ( $tickets as $reference => $ticket ) {
                if ( $ticket['loterie_id'] > 0 ) {
                    $lottery_options[ $ticket['loterie_id'] ] = $ticket['title'];
                }

                if ( $selected_loterie && intval( $ticket['loterie_id'] ) !== $selected_loterie ) {
                    continue;
                }

                if ( '' !== $selected_status && $selected_status !== $ticket['status'] ) {
                    continue;
                }

                if ( '' !== $search_term ) {
                    $haystack = strtolower( wp_strip_all_tags( implode( ' ', array(
                        $ticket['ticket_number'],
                        $ticket['title'],
                        $ticket['product_name'],
                        $ticket['order_number'],
                        $ticket['status_label'],
                        $ticket['status_note'],
                    ) ) ) );

                    if ( false === strpos( $haystack, strtolower( $search_term ) ) ) {
                        continue;
                    }
                }

                $filtered[ $reference ] = $ticket;
            }

            uasort( $filtered, static function ( $a, $b ) {
                $a_time = isset( $a['issued_at'] ) ? intval( $a['issued_at'] ) : 0;
                $b_time = isset( $b['issued_at'] ) ? intval( $b['issued_at'] ) : 0;

                if ( $a_time === $b_time ) {
                    return strcmp( $a['ticket_number'], $b['ticket_number'] );
                }

                return ( $a_time < $b_time ) ? 1 : -1;
            } );

            $filter_action = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'lm-tickets' ) : get_permalink();

            $status_options = array(
                ''          => __( 'Tous les statuts', 'loterie-manager' ),
                'valid'     => __( 'Valide', 'loterie-manager' ),
                'invalid'   => __( 'Invalidé', 'loterie-manager' ),
                'winner'    => __( 'Gagnant', 'loterie-manager' ),
                'alternate' => __( 'Suppléant', 'loterie-manager' ),
            );

            echo '<form method="get" class="lm-ticket-filters" action="' . esc_url( $filter_action ) . '">';
            echo '<div class="lm-ticket-filters__row">';
            echo '<label for="lm_filter_loterie" class="screen-reader-text">' . esc_html__( 'Filtrer par loterie', 'loterie-manager' ) . '</label>';
            echo '<select id="lm_filter_loterie" name="lm_filter_loterie">';
            echo '<option value="0">' . esc_html__( 'Toutes les loteries', 'loterie-manager' ) . '</option>';
            foreach ( $lottery_options as $loterie_id => $label ) {
                printf(
                    '<option value="%1$d" %2$s>%3$s</option>',
                    intval( $loterie_id ),
                    selected( $selected_loterie, intval( $loterie_id ), false ),
                    esc_html( $label )
                );
            }
            echo '</select>';

            echo '<label for="lm_filter_status" class="screen-reader-text">' . esc_html__( 'Filtrer par statut', 'loterie-manager' ) . '</label>';
            echo '<select id="lm_filter_status" name="lm_filter_status">';
            foreach ( $status_options as $value => $label ) {
                printf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr( $value ),
                    selected( $selected_status, $value, false ),
                    esc_html( $label )
                );
            }
            echo '</select>';

            echo '<label for="lm_ticket_search" class="screen-reader-text">' . esc_html__( 'Rechercher un ticket', 'loterie-manager' ) . '</label>';
            echo '<input type="search" id="lm_ticket_search" name="lm_ticket_search" value="' . esc_attr( $search_term ) . '" placeholder="' . esc_attr__( 'Rechercher un ticket…', 'loterie-manager' ) . '" />';
            echo '<button type="submit" class="button">' . esc_html__( 'Filtrer', 'loterie-manager' ) . '</button>';
            echo '</div>';
            echo '</form>';

            if ( ! $this->is_reassignment_enabled() ) {
                echo '<div class="woocommerce-info lm-ticket-alert">' . esc_html__( 'La réaffectation des tickets est actuellement désactivée par l\'administration.', 'loterie-manager' ) . '</div>';
            }

            echo '<form method="post" class="lm-ticket-reassignment">';
            wp_nonce_field( 'lm_reassign_ticket', 'lm_reassign_ticket_nonce' );

            echo '<table class="lm-ticket-table lm-ticket-table--extended">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Ticket', 'loterie-manager' ) . '</th>';
            echo '<th>' . esc_html__( 'Loterie', 'loterie-manager' ) . '</th>';
            echo '<th>' . esc_html__( 'Commande', 'loterie-manager' ) . '</th>';
            echo '<th>' . esc_html__( 'Statut', 'loterie-manager' ) . '</th>';
            echo '<th>' . esc_html__( 'Réaffecter', 'loterie-manager' ) . '</th>';
            echo '</tr></thead><tbody>';

            if ( empty( $filtered ) ) {
                echo '<tr><td colspan="5">' . esc_html__( 'Aucun ticket ne correspond à vos critères.', 'loterie-manager' ) . '</td></tr>';
            } else {
                $available_loteries = $this->get_loterie_choices();

                foreach ( $filtered as $reference => $ticket ) {
                    $issued_at = isset( $ticket['issued_at'] ) ? intval( $ticket['issued_at'] ) : 0;
                    echo '<tr class="lm-ticket-row lm-ticket-row--status-' . esc_attr( $ticket['status'] ) . '">';
                    echo '<td>';
                    echo '<strong>' . esc_html( $ticket['ticket_number'] ) . '</strong>';
                    if ( ! empty( $ticket['product_name'] ) ) {
                        echo '<br /><small>' . esc_html( $ticket['product_name'] ) . '</small>';
                    }
                    if ( $issued_at > 0 ) {
                        echo '<br /><small>' . esc_html( sprintf( __( 'Émis le %s', 'loterie-manager' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $issued_at ) ) ) . '</small>';
                    }
                    echo '</td>';

                    echo '<td>';
                    if ( $ticket['loterie_id'] > 0 ) {
                        echo '<strong>' . esc_html( $ticket['title'] ) . '</strong>';
                        if ( ! empty( $ticket['lottery_status_label'] ) ) {
                            echo '<br /><span class="lm-ticket-tag ' . esc_attr( $ticket['lottery_status_class'] ) . '">' . esc_html( $ticket['lottery_status_label'] ) . '</span>';
                        }
                        if ( ! empty( $ticket['end_date'] ) ) {
                            echo '<br /><small>' . esc_html( sprintf( __( 'Fin prévue le %s', 'loterie-manager' ), date_i18n( get_option( 'date_format' ), strtotime( $ticket['end_date'] ) ) ) ) . '</small>';
                        }
                    } else {
                        esc_html_e( 'Non attribué', 'loterie-manager' );
                    }
                    echo '</td>';

                    echo '<td>';
                    if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
                        $order_url = wc_get_account_endpoint_url( 'view-order', $ticket['order_id'] );
                        echo '<a class="lm-ticket-order-link" href="' . esc_url( $order_url ) . '">' . esc_html( sprintf( __( 'Commande #%s', 'loterie-manager' ), $ticket['order_number'] ) ) . '</a>';
                    } else {
                        echo esc_html( sprintf( __( 'Commande #%s', 'loterie-manager' ), $ticket['order_number'] ) );
                    }
                    echo '</td>';

                    echo '<td>';
                    echo '<span class="lm-ticket-status lm-ticket-status--' . esc_attr( $ticket['status'] ) . '">' . esc_html( $ticket['status_label'] ) . '</span>';
                    if ( ! empty( $ticket['status_note'] ) ) {
                        echo '<br /><small>' . esc_html( $ticket['status_note'] ) . '</small>';
                    }
                    echo '</td>';

                    echo '<td>';
                    if ( $this->is_reassignment_enabled() && ! empty( $ticket['can_reassign'] ) ) {
                        echo '<select name="lm_reassign[' . esc_attr( $reference ) . ']">';
                        echo '<option value="">' . esc_html__( 'Choisir une loterie', 'loterie-manager' ) . '</option>';
                        foreach ( $available_loteries as $choice_id => $label ) {
                            if ( ! $this->is_reassignment_enabled_for_loterie( $choice_id ) ) {
                                continue;
                            }
                            printf(
                                '<option value="%1$d">%2$s</option>',
                                intval( $choice_id ),
                                esc_html( $label )
                            );
                        }
                        echo '</select>';
                        echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Réaffecter', 'loterie-manager' ) . '</button>';
                        echo '<input type="hidden" name="lm_ticket_reference[]" value="' . esc_attr( $reference ) . '" />';
                    } elseif ( ! $this->is_reassignment_enabled() ) {
                        echo '<span class="lm-ticket-note">' . esc_html__( 'Réaffectation globale désactivée.', 'loterie-manager' ) . '</span>';
                    } else {
                        echo '<span class="lm-ticket-note">' . esc_html__( 'Réaffectation indisponible pour ce ticket.', 'loterie-manager' ) . '</span>';
                    }
                    echo '</td>';

                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
            echo '</form>';

            echo '<p class="lm-ticket-legend">' . esc_html__( 'Les tickets invalidés suite à une annulation ou un remboursement restent visibles ici afin de conserver une traçabilité complète.', 'loterie-manager' ) . '</p>';
        }

        /**
         * Handles ticket reassignment submissions.
         */
        public function handle_ticket_reassignment() {
            if ( ! is_user_logged_in() ) {
                return;
            }

            if ( ! function_exists( 'wc_get_order' ) ) {
                return;
            }

            if ( ! isset( $_POST['lm_reassign_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_reassign_ticket_nonce'] ) ), 'lm_reassign_ticket' ) ) {
                return;
            }

            if ( ! $this->is_reassignment_enabled() ) {
                if ( function_exists( 'wc_add_notice' ) ) {
                    wc_add_notice( __( 'La réaffectation des tickets est actuellement désactivée.', 'loterie-manager' ), 'error' );
                }
                return;
            }

            $references = isset( $_POST['lm_ticket_reference'] ) ? array_map( 'sanitize_text_field', (array) $_POST['lm_ticket_reference'] ) : array();
            if ( empty( $references ) ) {
                return;
            }

            $targets = array();
            if ( isset( $_POST['lm_reassign'] ) ) {
                foreach ( (array) $_POST['lm_reassign'] as $reference => $value ) {
                    $targets[ sanitize_text_field( $reference ) ] = intval( $value );
                }
            }

            if ( empty( $targets ) ) {
                return;
            }

            $user_id = get_current_user_id();
            $summary = $this->get_customer_ticket_summary( $user_id );
            $updated = array();
            $changes = array();

            foreach ( $references as $reference ) {
                if ( empty( $summary[ $reference ] ) ) {
                    continue;
                }

                $ticket = $summary[ $reference ];
                if ( empty( $ticket['can_reassign'] ) || ! $this->is_reassignment_enabled_for_loterie( $ticket['loterie_id'] ) ) {
                    continue;
                }

                $new_loterie_id = isset( $targets[ $reference ] ) ? intval( $targets[ $reference ] ) : 0;
                if ( $new_loterie_id <= 0 || $new_loterie_id === $ticket['loterie_id'] ) {
                    continue;
                }

                if ( ! $this->is_reassignment_enabled_for_loterie( $new_loterie_id ) ) {
                    if ( function_exists( 'wc_add_notice' ) ) {
                        wc_add_notice( sprintf( __( 'La loterie « %s » n\'accepte pas la réaffectation des tickets.', 'loterie-manager' ), get_the_title( $new_loterie_id ) ), 'error' );
                    }
                    continue;
                }

                $order = wc_get_order( $ticket['order_id'] );
                if ( ! $order ) {
                    continue;
                }

                $item = $order->get_item( $ticket['item_id'] );
                if ( ! $item ) {
                    continue;
                }

                $distribution = $this->get_item_ticket_distribution( $item );
                if ( ! isset( $distribution[ $ticket['ticket_index'] ] ) ) {
                    continue;
                }

                $original_id = intval( $distribution[ $ticket['ticket_index'] ] );
                if ( $original_id === $new_loterie_id ) {
                    continue;
                }

                $distribution[ $ticket['ticket_index'] ] = $new_loterie_id;
                $this->set_item_ticket_distribution( $item, $distribution );

                $unique_selection = array_values( array_unique( array_filter( $distribution ) ) );
                $item->update_meta_data( 'lm_lottery_selection', $unique_selection );
                $item->save();

                $updated[ $original_id ]    = true;
                $updated[ $new_loterie_id ] = true;

                $changes[] = array(
                    'ticket' => $ticket,
                    'from'   => $original_id,
                    'to'     => $new_loterie_id,
                );
            }

            if ( ! empty( $updated ) ) {
                $this->refresh_loterie_counters( array_keys( $updated ) );
            }

            if ( ! empty( $changes ) ) {
                $current_user = wp_get_current_user();
                foreach ( $changes as $change ) {
                    $from_title = $change['from'] > 0 ? get_the_title( $change['from'] ) : __( 'Aucune', 'loterie-manager' );
                    $to_title   = $change['to'] > 0 ? get_the_title( $change['to'] ) : __( 'Aucune', 'loterie-manager' );

                    if ( $change['from'] > 0 ) {
                        $this->add_lottery_log(
                            $change['from'],
                            'ticket_reassigned_out',
                            sprintf(
                                __( 'Ticket %1$s réaffecté vers « %2$s » par %3$s.', 'loterie-manager' ),
                                $change['ticket']['ticket_number'],
                                $to_title,
                                $current_user ? $current_user->display_name : __( 'un client', 'loterie-manager' )
                            ),
                            array(
                                'ticket_reference' => $change['ticket']['reference'],
                                'order_id'         => $change['ticket']['order_id'],
                                'user_id'          => $current_user ? $current_user->ID : 0,
                            )
                        );
                    }

                    if ( $change['to'] > 0 ) {
                        $this->add_lottery_log(
                            $change['to'],
                            'ticket_reassigned_in',
                            sprintf(
                                __( 'Ticket %1$s reçu depuis « %2$s ».', 'loterie-manager' ),
                                $change['ticket']['ticket_number'],
                                $from_title
                            ),
                            array(
                                'ticket_reference' => $change['ticket']['reference'],
                                'order_id'         => $change['ticket']['order_id'],
                                'user_id'          => $current_user ? $current_user->ID : 0,
                            )
                        );
                    }
                }

                if ( function_exists( 'wc_add_notice' ) ) {
                    wc_add_notice( _n( 'Le ticket a bien été réaffecté.', 'Les tickets sélectionnés ont bien été réaffectés.', count( $changes ), 'loterie-manager' ), 'success' );
                }
            }

            if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
                wp_safe_redirect( wc_get_account_endpoint_url( 'lm-tickets' ) );
            } else {
                wp_safe_redirect( home_url() );
            }
            exit;
        }

        /**
         * Retrieves plugin default settings.
         *
         * @return array<string, mixed>
         */
        private function get_default_settings() {
            return array(
                'reassignment_enabled' => true,
                'table_pagination'     => 25,
                'visible_columns'      => array( 'ticket', 'participant', 'email', 'status', 'order', 'date', 'city', 'country', 'phone' ),
                'eligibility_rules'    => array(
                    'exclude_statuses' => array( 'cancelled', 'refunded', 'failed', 'pending' ),
                ),
            );
        }

        /**
         * Retrieves merged settings.
         *
         * @return array<string, mixed>
         */
        private function get_settings() {
            $stored   = get_option( self::OPTION_SETTINGS, array() );
            $defaults = $this->get_default_settings();

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            $stored['eligibility_rules'] = isset( $stored['eligibility_rules'] ) && is_array( $stored['eligibility_rules'] )
                ? $stored['eligibility_rules']
                : array();

            $settings = wp_parse_args( $stored, $defaults );

            $settings['table_pagination'] = max( 5, intval( $settings['table_pagination'] ) );
            if ( ! is_array( $settings['visible_columns'] ) ) {
                $settings['visible_columns'] = $defaults['visible_columns'];
            }

            if ( empty( $settings['eligibility_rules']['exclude_statuses'] ) || ! is_array( $settings['eligibility_rules']['exclude_statuses'] ) ) {
                $settings['eligibility_rules']['exclude_statuses'] = $defaults['eligibility_rules']['exclude_statuses'];
            }

            return $settings;
        }

        /**
         * Persists plugin settings.
         *
         * @param array<string, mixed> $settings Settings to persist.
         */
        private function save_settings( $settings ) {
            update_option( self::OPTION_SETTINGS, $settings );
        }

        /**
         * Checks if global reassignment is enabled.
         *
         * @return bool
         */
        private function is_reassignment_enabled() {
            $settings = $this->get_settings();
            return ! empty( $settings['reassignment_enabled'] );
        }

        /**
         * Checks whether reassignment is enabled for a specific loterie.
         *
         * @param int $post_id Loterie ID.
         *
         * @return bool
         */
        private function is_reassignment_enabled_for_loterie( $post_id ) {
            $post_id = intval( $post_id );
            if ( $post_id <= 0 ) {
                return $this->is_reassignment_enabled();
            }

            $mode = get_post_meta( $post_id, self::META_REASSIGNMENT_MODE, true );
            $mode = in_array( $mode, array( 'enabled', 'disabled', 'inherit' ), true ) ? $mode : 'inherit';

            if ( 'enabled' === $mode ) {
                return true;
            }

            if ( 'disabled' === $mode ) {
                return false;
            }

            return $this->is_reassignment_enabled();
        }

        /**
         * Returns the list of excluded WooCommerce statuses.
         *
         * @return array<int, string>
         */
        private function get_order_excluded_statuses() {
            $settings  = $this->get_settings();
            $statuses  = isset( $settings['eligibility_rules']['exclude_statuses'] ) ? (array) $settings['eligibility_rules']['exclude_statuses'] : array();
            $sanitized = array();

            foreach ( $statuses as $status ) {
                $status = sanitize_key( str_replace( 'wc-', '', (string) $status ) );
                if ( '' !== $status ) {
                    $sanitized[] = $status;
                }
            }

            return array_values( array_unique( $sanitized ) );
        }

        /**
         * Formats a ticket number in a human readable way.
         *
         * @param WC_Order|int $order   Order object or ID.
         * @param int          $item_id Order item ID.
         * @param int          $index   Ticket index.
         *
         * @return string
         */
        private function format_ticket_number( $order, $item_id, $index ) {
            if ( $order instanceof WC_Order ) {
                $order_number = $order->get_order_number();
            } else {
                $order_number = $order;
            }

            $ticket_index = intval( $index ) + 1;

            return sprintf( 'T-%1$s-%2$03d', $order_number, $ticket_index );
        }

        /**
         * Records an event in the loterie audit log.
         *
         * @param int                   $post_id Loterie ID.
         * @param string                $type    Event type.
         * @param string                $message Human readable message.
         * @param array<string, mixed>  $context Additional context.
         */
        private function add_lottery_log( $post_id, $type, $message, $context = array() ) {
            $post_id = intval( $post_id );
            if ( $post_id <= 0 ) {
                return;
            }

            $log = get_post_meta( $post_id, self::META_AUDIT_LOG, true );
            if ( ! is_array( $log ) ) {
                $log = array();
            }

            $log[] = array(
                'timestamp' => current_time( 'timestamp' ),
                'type'      => sanitize_key( $type ),
                'message'   => wp_strip_all_tags( $message ),
                'context'   => $context,
                'user_id'   => get_current_user_id(),
            );

            if ( count( $log ) > 200 ) {
                $log = array_slice( $log, -200 );
            }

            update_post_meta( $post_id, self::META_AUDIT_LOG, $log );
        }

        /**
         * Retrieves stored logs for a loterie.
         *
         * @param int $post_id Loterie ID.
         *
         * @return array<int, array<string, mixed>>
         */
        private function get_lottery_logs( $post_id ) {
            $log = get_post_meta( $post_id, self::META_AUDIT_LOG, true );
            if ( ! is_array( $log ) ) {
                return array();
            }

            usort(
                $log,
                static function ( $a, $b ) {
                    $a_time = isset( $a['timestamp'] ) ? intval( $a['timestamp'] ) : 0;
                    $b_time = isset( $b['timestamp'] ) ? intval( $b['timestamp'] ) : 0;
                    return $b_time <=> $a_time;
                }
            );

            return $log;
        }

        /**
         * Retrieves manual draw reports for a loterie.
         *
         * @param int $post_id Loterie ID.
         *
         * @return array<int, array<string, mixed>>
         */
        private function get_manual_draw_reports( $post_id ) {
            $reports = get_post_meta( $post_id, self::META_MANUAL_DRAW_REPORTS, true );
            if ( ! is_array( $reports ) ) {
                return array();
            }

            usort(
                $reports,
                static function ( $a, $b ) {
                    $a_time = isset( $a['created_at'] ) ? intval( $a['created_at'] ) : 0;
                    $b_time = isset( $b['created_at'] ) ? intval( $b['created_at'] ) : 0;
                    return $b_time <=> $a_time;
                }
            );

            return $reports;
        }

        /**
         * Stores a new manual draw report and updates history.
         *
         * @param int                     $post_id Loterie ID.
         * @param array<string, mixed>    $report  Report data.
         */
        private function append_manual_draw_report( $post_id, $report ) {
            $post_id = intval( $post_id );
            if ( $post_id <= 0 ) {
                return;
            }

            $reports = $this->get_manual_draw_reports( $post_id );
            $reports[] = $report;

            if ( count( $reports ) > 20 ) {
                $reports = array_slice( $reports, 0, 20 );
            }

            update_post_meta( $post_id, self::META_MANUAL_DRAW_REPORTS, $reports );

            $history = get_post_meta( $post_id, self::META_DRAW_HISTORY, true );
            if ( ! is_array( $history ) ) {
                $history = array();
            }

            $history[] = array(
                'id'         => $report['id'],
                'created_at' => $report['created_at'],
                'type'       => $report['type'],
                'seed'       => $report['seed'],
                'winners'    => $report['winners'],
            );

            if ( count( $history ) > 20 ) {
                $history = array_slice( $history, -20 );
            }

            update_post_meta( $post_id, self::META_DRAW_HISTORY, $history );
        }

        /**
         * Formats a timestamp for admin display.
         *
         * @param int $timestamp Timestamp.
         *
         * @return string
         */
        private function format_admin_datetime( $timestamp ) {
            if ( ! $timestamp ) {
                return '';
            }

            return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
        }

        /**
         * Formats a currency amount using WooCommerce helpers when available.
         *
         * @param float $amount Amount.
         *
         * @return string
         */
        private function format_currency( $amount ) {
            $amount = floatval( $amount );

            if ( function_exists( 'wc_price' ) ) {
                $formatted = wc_price( $amount );
                $formatted = wp_strip_all_tags( $formatted );

                return trim( html_entity_decode( $formatted, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
            }

            $symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '€';

            return number_format_i18n( $amount, 2 ) . ' ' . $symbol;
        }

        /**
         * Retrieves draw roles indexed by ticket signature.
         *
         * @param int $post_id Loterie ID.
         *
         * @return array<string, array<string, mixed>>
         */
        private function map_draw_roles( $post_id ) {
            $history = get_post_meta( $post_id, self::META_DRAW_HISTORY, true );
            if ( ! is_array( $history ) || empty( $history ) ) {
                return array();
            }

            $latest = end( $history );
            if ( ! isset( $latest['winners'] ) || ! is_array( $latest['winners'] ) ) {
                return array();
            }

            $map       = array();
            $created   = isset( $latest['created_at'] ) ? intval( $latest['created_at'] ) : current_time( 'timestamp' );
            $draw_date = $this->format_admin_datetime( $created );

            foreach ( $latest['winners'] as $winner ) {
                if ( empty( $winner['signature'] ) ) {
                    continue;
                }

                $role     = isset( $winner['role'] ) ? $winner['role'] : 'winner';
                $position = isset( $winner['position'] ) ? intval( $winner['position'] ) : 0;

                if ( 'alternate' === $role ) {
                    $label = 0 === $position
                        ? __( 'Suppléant', 'loterie-manager' )
                        : sprintf( __( 'Suppléant #%d', 'loterie-manager' ), $position + 1 );
                } else {
                    $label = __( 'Ticket gagnant', 'loterie-manager' );
                }

                $map[ $winner['signature'] ] = array(
                    'status'            => 'alternate' === $role ? 'alternate' : 'winner',
                    'label'             => $label,
                    'note'              => $draw_date ? sprintf( __( 'Tirage manuel du %s', 'loterie-manager' ), $draw_date ) : __( 'Tirage manuel', 'loterie-manager' ),
                    'role'              => $role,
                    'position'          => $position,
                    'lock_reassignment' => true,
                );
            }

            return $map;
        }

        /**
         * Refreshes cached statistics for given loteries and syncs the stored counters.
         *
         * @param array<int, int> $loterie_ids List of IDs.
         */
        private function refresh_loterie_counters( $loterie_ids ) {
            $ids = array_unique( array_map( 'intval', (array) $loterie_ids ) );

            foreach ( $ids as $loterie_id ) {
                if ( $loterie_id <= 0 ) {
                    continue;
                }

                unset( $this->lottery_stats_cache[ $loterie_id ] );
                $stats = $this->get_lottery_stats( $loterie_id, array( 'force_refresh' => true ) );

                if ( isset( $stats['valid_tickets'] ) ) {
                    update_post_meta( $loterie_id, self::META_TICKETS_SOLD, intval( $stats['valid_tickets'] ) );
                }
            }
        }

        /**
         * Retrieves loterie statistics.
         *
         * @param int   $post_id Loterie ID.
         * @param array $args    Arguments.
         *
         * @return array<string, mixed>
         */
        private function get_lottery_stats( $post_id, $args = array() ) {
            $defaults = array(
                'force_refresh'   => false,
                'include_tickets' => false,
                'search'          => '',
                'status_filter'   => '',
                'paged'           => 1,
                'per_page'        => $this->get_settings()['table_pagination'],
            );

            $args = wp_parse_args( $args, $defaults );

            if ( $args['force_refresh'] ) {
                unset( $this->lottery_stats_cache[ $post_id ] );
            }

            if ( ! isset( $this->lottery_stats_cache[ $post_id ] ) ) {
                $this->lottery_stats_cache[ $post_id ] = $this->build_lottery_stats( $post_id );
            }

            $data = $this->lottery_stats_cache[ $post_id ];

            $tickets = isset( $data['tickets'] ) ? $data['tickets'] : array();
            $search  = strtolower( (string) $args['search'] );
            $status  = sanitize_key( $args['status_filter'] );

            $filtered = array_filter(
                $tickets,
                static function ( $ticket ) use ( $search, $status ) {
                    if ( '' !== $status && $status !== $ticket['status'] ) {
                        return false;
                    }

                    if ( '' === $search ) {
                        return true;
                    }

                    $haystack = strtolower( wp_strip_all_tags( implode( ' ', array(
                        $ticket['ticket_number'] ?? '',
                        $ticket['customer_name'] ?? '',
                        $ticket['customer_email'] ?? '',
                        $ticket['order_number'] ?? '',
                        $ticket['status_label'] ?? '',
                    ) ) ) );

                    return false !== strpos( $haystack, $search );
                }
            );

            $total     = count( $filtered );
            $per_page  = intval( $args['per_page'] );
            $per_page  = $per_page <= 0 ? $total : max( 1, $per_page );
            $paged     = max( 1, intval( $args['paged'] ) );
            $total_page= $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
            if ( $paged > $total_page ) {
                $paged = $total_page > 0 ? $total_page : 1;
            }

            $offset   = ( $paged - 1 ) * $per_page;
            $slice    = array_slice( $filtered, $offset, $per_page );

            if ( $args['include_tickets'] ) {
                $data['tickets_paginated'] = $slice;
            }

            $data['tickets_all']        = array_values( $filtered );
            $data['tickets']            = $args['include_tickets'] ? $slice : array();
            $data['total_tickets']      = $total;
            $data['current_page']       = $paged;
            $data['per_page']           = $per_page;
            $data['total_pages']        = $total_page;
            $data['filters']            = array(
                'search' => $args['search'],
                'status' => $status,
            );

            return $data;
        }

        /**
         * Builds base statistics for a loterie.
         *
         * @param int $post_id Loterie ID.
         *
         * @return array<string, mixed>
         */
        private function build_lottery_stats( $post_id ) {
            $post_id = intval( $post_id );
            $collection = $this->collect_loterie_tickets( $post_id );
            $tickets    = $collection['tickets'];
            $participants_map = $collection['participants'];

            $capacity   = intval( get_post_meta( $post_id, self::META_TICKET_CAPACITY, true ) );
            $start_date = get_post_meta( $post_id, self::META_START_DATE, true );
            $end_date   = get_post_meta( $post_id, self::META_END_DATE, true );
            $start_time = $start_date ? strtotime( $start_date ) : 0;
            $end_time   = $end_date ? strtotime( $end_date ) : 0;
            $now        = current_time( 'timestamp' );
            $post       = get_post( $post_id );
            if ( ! $start_time && $post ) {
                $start_time = strtotime( $post->post_date );
            }
            $status_raw = $post ? $post->post_status : 'draft';

            $valid_count     = 0;
            $invalid_count   = 0;
            $winner_count    = 0;
            $alternate_count = 0;
            $revenue         = 0.0;
            $orders_involved = array();

            foreach ( $tickets as $ticket ) {
                $orders_involved[ $ticket['order_id'] ] = true;

                if ( in_array( $ticket['status'], array( 'winner', 'alternate', 'valid' ), true ) ) {
                    $valid_count++;
                    $revenue += isset( $ticket['amount'] ) ? floatval( $ticket['amount'] ) : 0.0;

                    if ( 'winner' === $ticket['status'] ) {
                        $winner_count++;
                    } elseif ( 'alternate' === $ticket['status'] ) {
                        $alternate_count++;
                    }
                } else {
                    $invalid_count++;
                }
            }

            $unique_participants = count( $participants_map );
            $orders_count        = count( $orders_involved );
            $progress            = $capacity > 0 ? min( 100, round( ( $valid_count / max( 1, $capacity ) ) * 100, 2 ) ) : 0;

            if ( 'publish' !== $status_raw ) {
                $status_code         = 'draft';
                $status_label        = __( 'Brouillon', 'loterie-manager' );
                $status_class        = 'is-draft';
                $status_display_code = 'draft';
            } elseif ( $start_time && $start_time > $now ) {
                $status_code         = 'upcoming';
                $status_label        = __( 'À venir', 'loterie-manager' );
                $status_class        = 'is-upcoming';
                $status_display_code = 'upcoming';
            } elseif ( $end_time && $end_time < $now ) {
                $status_code         = 'closed';
                $status_label        = __( 'Terminée', 'loterie-manager' );
                $status_class        = 'is-ended';
                $status_display_code = 'ended';
            } elseif ( $capacity > 0 && $valid_count >= $capacity ) {
                $status_code         = 'complete';
                $status_label        = __( 'Objectif atteint', 'loterie-manager' );
                $status_class        = 'is-complete';
                $status_display_code = 'active';
            } else {
                $status_code         = 'active';
                $status_label        = __( 'En cours', 'loterie-manager' );
                $status_class        = 'is-active';
                $status_display_code = 'active';
            }

            $manual_status = sanitize_key( (string) get_post_meta( $post_id, self::META_STATUS_OVERRIDE, true ) );
            $status_options = $this->get_loterie_status_options();
            if ( '' !== $manual_status && isset( $status_options[ $manual_status ] ) ) {
                $status_label        = $status_options[ $manual_status ]['label'];
                $status_class        = $status_options[ $manual_status ]['class'];
                $status_display_code = $manual_status;
            }

            $alerts = array();

            if ( $end_time && $end_time >= $now && ( $end_time - $now ) < 3 * DAY_IN_SECONDS ) {
                $alerts[] = __( 'La date de fin approche.', 'loterie-manager' );
            }

            if ( $valid_count === 0 ) {
                $alerts[] = __( 'Aucun ticket valide enregistré pour le moment.', 'loterie-manager' );
            }

            if ( $invalid_count > 0 ) {
                $alerts[] = sprintf( __( '%d ticket(s) ont été invalidés.', 'loterie-manager' ), $invalid_count );
            }

            $ready_for_draw = $valid_count > 0 && ( in_array( $status_code, array( 'closed', 'complete' ), true ) );

            return array(
                'loterie_id'          => $post_id,
                'capacity'            => $capacity,
                'valid_tickets'       => $valid_count,
                'invalid_tickets'     => $invalid_count,
                'winner_tickets'      => $winner_count,
                'alternate_tickets'   => $alternate_count,
                'tickets'             => $tickets,
                'participants'        => $participants_map,
                'unique_participants' => $unique_participants,
                'revenue'             => $revenue,
                'orders_count'        => $orders_count,
                'average_order'       => $orders_count > 0 ? $revenue / $orders_count : 0,
                'conversion_rate'     => $orders_count > 0 ? round( ( $valid_count / $orders_count ) * 100, 2 ) : 0,
                'progress'            => $progress,
                'status_code'         => $status_code,
                'status_display_code' => $status_display_code,
                'status_manual_code'  => '' !== $manual_status && isset( $status_options[ $manual_status ] ) ? $manual_status : '',
                'status_label'        => $status_label,
                'status_class'        => $status_class,
                'start_date'          => $start_date,
                'end_date'            => $end_date,
                'alerts'              => $alerts,
                'ready_for_draw'      => $ready_for_draw,
                'reassignment_mode'   => get_post_meta( $post_id, self::META_REASSIGNMENT_MODE, true ),
                'reassignment_enabled'=> $this->is_reassignment_enabled_for_loterie( $post_id ),
                'global_reassignment' => $this->is_reassignment_enabled(),
                'draw_history'        => get_post_meta( $post_id, self::META_DRAW_HISTORY, true ),
                'manual_reports'      => $this->get_manual_draw_reports( $post_id ),
            );
        }

        /**
         * Builds ticket collection for a loterie.
         *
         * @param int $post_id Loterie ID.
         *
         * @return array<string, mixed>
         */
        private function collect_loterie_tickets( $post_id ) {
            if ( ! function_exists( 'wc_get_orders' ) ) {
                return array(
                    'tickets'     => array(),
                    'participants'=> array(),
                );
            }

            $orders = wc_get_orders(
                array(
                    'status'  => 'any',
                    'limit'   => -1,
                    'orderby' => 'date',
                    'order'   => 'ASC',
                )
            );

            $draw_map     = $this->map_draw_roles( $post_id );
            $tickets      = array();
            $participants = array();

            foreach ( $orders as $order ) {
                foreach ( $order->get_items() as $item_id => $item ) {
                    $distribution = $this->get_item_ticket_distribution( $item );
                    if ( empty( $distribution ) ) {
                        continue;
                    }

                    $counts = array_count_values( $distribution );
                    $share  = isset( $counts[ $post_id ] ) && $counts[ $post_id ] > 0
                        ? ( $item->get_total() + $item->get_total_tax() ) / max( 1, $counts[ $post_id ] )
                        : 0;

                    foreach ( $distribution as $index => $assigned_id ) {
                        if ( intval( $assigned_id ) !== intval( $post_id ) ) {
                            continue;
                        }

                        $reference   = sprintf( '%1$d:%2$d:%3$d', $order->get_id(), $item_id, $index );
                        $status_info = $this->get_ticket_status_from_order( $order, $post_id );
                        $draw_state  = isset( $draw_map[ $reference ] ) ? $draw_map[ $reference ] : array();

                        $status       = $status_info['status'];
                        $status_label = $status_info['label'];
                        $status_note  = $status_info['note'];
                        $valid        = 'valid' === $status;

                        if ( ! empty( $draw_state ) ) {
                            $status       = $draw_state['status'];
                            $status_label = $draw_state['label'];
                            $status_note  = $draw_state['note'];
                            $valid        = in_array( $status, array( 'winner', 'alternate' ), true );
                        }

                        $customer_name = trim( $order->get_formatted_billing_full_name() );
                        if ( '' === $customer_name ) {
                            $customer_name = trim( $order->get_formatted_shipping_full_name() );
                        }
                        if ( '' === $customer_name ) {
                            $user = $order->get_user();
                            if ( $user ) {
                                $customer_name = $user->display_name;
                            }
                        }
                        if ( '' === $customer_name ) {
                            $customer_name = __( 'Client', 'loterie-manager' );
                        }

                        $email          = $order->get_billing_email();
                        $participant_id = $email ? strtolower( $email ) : 'order-' . $order->get_id();
                        $participants[ $participant_id ] = array(
                            'name'  => $customer_name,
                            'email' => $email,
                        );

                        $tickets[] = array(
                            'reference'       => $reference,
                            'signature'       => $reference,
                            'ticket_number'   => $this->format_ticket_number( $order, $item_id, $index ),
                            'status'          => $status,
                            'status_label'    => $status_label,
                            'status_note'     => $status_note,
                            'valid'           => $valid,
                            'customer_first_name' => $order->get_billing_first_name(),
                            'customer_last_name'  => $order->get_billing_last_name(),
                            'customer_name'   => $customer_name,
                            'customer_email'  => $email,
                            'customer_phone'  => $order->get_billing_phone(),
                            'customer_country'=> $order->get_billing_country(),
                            'customer_city'   => $order->get_billing_city(),
                            'order_id'        => $order->get_id(),
                            'order_number'    => $order->get_order_number(),
                            'order_status'    => $order->get_status(),
                            'order_date'      => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
                            'amount'          => $share,
                            'item_id'         => $item_id,
                            'ticket_index'    => $index,
                            'draw_role'       => $draw_state['role'] ?? '',
                            'draw_position'   => $draw_state['position'] ?? 0,
                        );
                    }
                }
            }

            return array(
                'tickets'      => $tickets,
                'participants' => $participants,
            );
        }

        /**
         * Determines the ticket status based on the order state and reassignment settings.
         *
         * @param WC_Order $order     Order instance.
         * @param int      $loterie_id Loterie ID.
         *
         * @return array<string, mixed>
         */
        private function get_ticket_status_from_order( $order, $loterie_id ) {
            $status               = $order->get_status();
            $reassignment_allowed = $this->is_reassignment_enabled() && $this->is_reassignment_enabled_for_loterie( $loterie_id );
            $excluded             = $this->get_order_excluded_statuses();
            $normalized           = sanitize_key( str_replace( 'wc-', '', (string) $status ) );
            $is_excluded          = in_array( $normalized, $excluded, true );
            $reason_code          = '';
            $note                 = '';
            $valid                = true;

            if ( $is_excluded ) {
                $valid       = false;
                $reason_code = 'order-excluded';
                $status_name = function_exists( 'wc_get_order_status_name' )
                    ? wc_get_order_status_name( $status )
                    : ( '' !== $normalized ? ucfirst( $normalized ) : __( 'inconnu', 'loterie-manager' ) );
                $note        = sprintf( __( 'Commande %s : ticket invalidé automatiquement.', 'loterie-manager' ), $status_name );
            }

            return array(
                'status'       => $valid ? 'valid' : 'invalid',
                'label'        => $valid ? __( 'Valide pour tirage', 'loterie-manager' ) : __( 'Invalidé', 'loterie-manager' ),
                'note'         => $note,
                'reassignable' => $reassignment_allowed && $valid,
                'reason_code'  => $reason_code,
            );
        }

        /**
         * Registers the WinShirt admin menu.
         */
        public function register_admin_menu() {
            $capability = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';

            add_menu_page(
                __( 'WinShirt', 'loterie-manager' ),
                __( 'WinShirt', 'loterie-manager' ),
                $capability,
                'winshirt-lotteries',
                array( $this, 'render_admin_dashboard' ),
                'dashicons-tickets',
                57
            );

            add_submenu_page(
                'winshirt-lotteries',
                __( 'Loteries', 'loterie-manager' ),
                __( 'Loteries', 'loterie-manager' ),
                $capability,
                'winshirt-lotteries',
                array( $this, 'render_admin_dashboard' )
            );

            add_submenu_page(
                'winshirt-lotteries',
                __( 'Paramètres des loteries', 'loterie-manager' ),
                __( 'Paramètres', 'loterie-manager' ),
                $capability,
                'winshirt-lotteries-settings',
                array( $this, 'render_admin_settings' )
            );
        }

        /**
         * Enqueues admin assets.
         *
         * @param string $hook Current admin hook.
         */
        public function enqueue_admin_assets( $hook ) {
            if ( false === strpos( $hook, 'winshirt-lotteries' ) ) {
                return;
            }

            wp_enqueue_style(
                'loterie-manager-admin',
                plugins_url( 'assets/css/admin.css', __FILE__ ),
                array(),
                self::VERSION
            );
        }

        /**
         * Renders the dashboard or detail view depending on the requested loterie.
         */
        public function render_admin_dashboard() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.', 'loterie-manager' ) );
            }

            $loterie_id = isset( $_GET['loterie'] ) ? absint( $_GET['loterie'] ) : 0;
            if ( $loterie_id ) {
                $this->render_admin_lottery_detail( $loterie_id );
                return;
            }

            $settings             = $this->get_settings();
            $reassignment_enabled = $this->is_reassignment_enabled();
            $posts                = get_posts(
                array(
                    'post_type'      => 'post',
                    'posts_per_page' => -1,
                    'post_status'    => array( 'publish', 'draft', 'pending' ),
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                )
            );

            $search_global = isset( $_GET['lm_search_global'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_search_global'] ) ) : '';
            $status_filter = isset( $_GET['lm_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['lm_status_filter'] ) ) : '';
            $period_filter = isset( $_GET['lm_period_filter'] ) ? sanitize_key( wp_unslash( $_GET['lm_period_filter'] ) ) : '';
            $export_target = isset( $_GET['lm_export'] ) ? absint( $_GET['lm_export'] ) : 0;

            $current_user = wp_get_current_user();
            $now          = current_time( 'timestamp' );

            $lotteries = array();
            foreach ( $posts as $post ) {
                $stats = $this->get_lottery_stats(
                    $post->ID,
                    array(
                        'include_tickets' => true,
                        'per_page'        => -1,
                    )
                );

                $start_date = ! empty( $stats['start_date'] ) ? $stats['start_date'] : '';
                $start_time = $start_date ? strtotime( $start_date ) : ( $post->post_date ? strtotime( $post->post_date ) : 0 );
                $end_time   = ! empty( $stats['end_date'] ) ? strtotime( $stats['end_date'] ) : 0;
                $period     = $start_time ? date_i18n( get_option( 'date_format' ), $start_time ) : __( 'Non défini', 'loterie-manager' );
                if ( $end_time ) {
                    $period .= ' → ' . date_i18n( get_option( 'date_format' ), $end_time );
                }

                $lotteries[] = array(
                    'id'              => $post->ID,
                    'title'           => get_the_title( $post ),
                    'status_code'     => $stats['status_code'],
                    'status_label'    => $stats['status_label'],
                    'status_class'    => $stats['status_class'],
                    'period'          => $period,
                    'start_date'      => $start_date,
                    'start_time'      => $start_time,
                    'end_time'        => $end_time,
                    'progress'        => $stats['progress'],
                    'valid_tickets'   => $stats['valid_tickets'],
                    'capacity'        => $stats['capacity'],
                    'participants'    => $stats['unique_participants'],
                    'revenue'         => $stats['revenue'],
                    'conversion_rate' => $stats['conversion_rate'],
                    'alerts'          => $stats['alerts'],
                    'ready_for_draw'  => $stats['ready_for_draw'],
                    'tickets_all'     => isset( $stats['tickets_all'] ) ? $stats['tickets_all'] : array(),
                );
            }

            $has_active_sales = false;
            foreach ( $lotteries as $entry ) {
                if ( ! empty( $entry['valid_tickets'] ) ) {
                    $has_active_sales = true;
                    break;
                }
            }

            $filtered_lotteries = array_filter(
                $lotteries,
                static function ( $entry ) use ( $search_global, $status_filter, $period_filter, $now ) {
                    if ( '' !== $search_global && false === stripos( $entry['title'], $search_global ) ) {
                        return false;
                    }

                    if ( '' !== $status_filter && $status_filter !== $entry['status_code'] ) {
                        return false;
                    }

                    if ( '' !== $period_filter ) {
                        if ( 'closing' === $period_filter ) {
                            if ( empty( $entry['end_time'] ) || $entry['end_time'] < $now || ( $entry['end_time'] - $now ) > 7 * DAY_IN_SECONDS ) {
                                return false;
                            }
                        } elseif ( 'upcoming' === $period_filter ) {
                            if ( empty( $entry['start_time'] ) || $entry['start_time'] <= $now ) {
                                return false;
                            }
                        } elseif ( 'past' === $period_filter ) {
                            if ( empty( $entry['end_time'] ) || $entry['end_time'] >= $now ) {
                                return false;
                            }
                        }
                    }

                    return true;
                }
            );

            $filtered_lotteries = array_values( $filtered_lotteries );

            $reset_status = isset( $_GET['lm_reset_status'] ) ? sanitize_key( wp_unslash( $_GET['lm_reset_status'] ) ) : '';
            $reset_target = isset( $_GET['lm_reset_target'] ) ? absint( wp_unslash( $_GET['lm_reset_target'] ) ) : 0;
            $reset_target_post = $reset_target ? get_post( $reset_target ) : null;
            $reset_target_title = ( $reset_target_post && 'post' === $reset_target_post->post_type ) ? get_the_title( $reset_target_post ) : '';
            $reset_notice = '';
            $reset_notice_class = 'notice notice-success';

            if ( 'success' === $reset_status ) {
                if ( $reset_target && '' !== $reset_target_title ) {
                    /* translators: %s: lottery title. */
                    $reset_notice = sprintf( __( 'Les compteurs de « %s » ont été réinitialisés.', 'loterie-manager' ), $reset_target_title );
                } elseif ( $reset_target ) {
                    $reset_notice = __( 'Les compteurs de la loterie sélectionnée ont été réinitialisés.', 'loterie-manager' );
                } else {
                    $reset_notice = __( 'Les compteurs des loteries ont été réinitialisés.', 'loterie-manager' );
                }
                $reset_notice_class = 'notice notice-success';
            } elseif ( 'active' === $reset_status ) {
                if ( $reset_target && '' !== $reset_target_title ) {
                    /* translators: %s: lottery title. */
                    $reset_notice = sprintf( __( 'Réinitialisation impossible pour « %s » : des tickets valides sont encore enregistrés.', 'loterie-manager' ), $reset_target_title );
                } else {
                    $reset_notice = __( 'Réinitialisation impossible : des tickets valides sont encore enregistrés.', 'loterie-manager' );
                }
                $reset_notice_class = 'notice notice-error';
            } elseif ( 'forced' === $reset_status ) {
                if ( $reset_target && '' !== $reset_target_title ) {
                    /* translators: %s: loterie title. */
                    $reset_notice = sprintf( __( 'Réinitialisation forcée effectuée pour « %s ». Vérifiez les commandes associées.', 'loterie-manager' ), $reset_target_title );
                } elseif ( $reset_target ) {
                    $reset_notice = __( 'Réinitialisation forcée effectuée sur la loterie sélectionnée.', 'loterie-manager' );
                } else {
                    $reset_notice = __( 'Réinitialisation forcée effectuée sur l’ensemble des loteries.', 'loterie-manager' );
                }
                $reset_notice_class = 'notice notice-warning';
            } elseif ( 'nonce' === $reset_status ) {
                $reset_notice       = __( 'La vérification de sécurité a échoué. Merci de réessayer.', 'loterie-manager' );
                $reset_notice_class = 'notice notice-error';
            } elseif ( 'empty' === $reset_status ) {
                $reset_notice       = __( 'Aucune loterie n’a été trouvée pour la réinitialisation.', 'loterie-manager' );
                $reset_notice_class = 'notice notice-warning';
            }

            $totals = array(
                'active'        => 0,
                'valid'         => 0,
                'capacity'      => 0,
                'revenue'       => 0.0,
                'conversion'    => 0.0,
                'conversion_nb' => 0,
                'closing'       => 0,
                'alerts'        => 0,
            );

            $timeline = array();

            foreach ( $filtered_lotteries as $entry ) {
                if ( 'active' === $entry['status_code'] ) {
                    $totals['active']++;
                }

                $totals['valid']    += intval( $entry['valid_tickets'] );
                $totals['capacity'] += intval( $entry['capacity'] );
                $totals['revenue']  += floatval( $entry['revenue'] );

                if ( $entry['conversion_rate'] > 0 ) {
                    $totals['conversion']    += floatval( $entry['conversion_rate'] );
                    $totals['conversion_nb'] ++;
                }

                if ( ! empty( $entry['end_time'] ) && $entry['end_time'] >= $now && ( $entry['end_time'] - $now ) <= 7 * DAY_IN_SECONDS ) {
                    $totals['closing']++;
                }

                $totals['alerts'] += count( $entry['alerts'] );

                foreach ( $entry['tickets_all'] as $ticket ) {
                    if ( empty( $ticket['order_date'] ) ) {
                        continue;
                    }

                    $day = gmdate( 'Y-m-d', intval( $ticket['order_date'] ) );
                    if ( ! isset( $timeline[ $day ] ) ) {
                        $timeline[ $day ] = 0;
                    }

                    $timeline[ $day ]++;
                }
            }

            ksort( $timeline );

            $timeline_limit = 12;
            if ( count( $timeline ) > $timeline_limit ) {
                $timeline = array_slice( $timeline, -1 * $timeline_limit, null, true );
            }

            $avg_conversion = $totals['conversion_nb'] > 0 ? round( $totals['conversion'] / $totals['conversion_nb'], 1 ) : 0;

            $export_context = null;
            if ( $export_target ) {
                foreach ( $lotteries as $entry ) {
                    if ( intval( $entry['id'] ) === $export_target ) {
                        $export_context = $entry;
                        break;
                    }
                }
            }

            ob_start();
            ?>
            <div class="lm-app lm-app--dark">
                <aside class="lm-sidebar">
                    <div class="lm-sidebar__brand">WinShirt</div>
                    <nav class="lm-sidebar__nav">
                        <a class="lm-sidebar__item is-active" href="<?php echo esc_url( admin_url( 'admin.php?page=winshirt-lotteries' ) ); ?>">
                            <span class="dashicons dashicons-tickets"></span>
                            <span><?php esc_html_e( 'Loteries', 'loterie-manager' ); ?></span>
                        </a>
                        <a class="lm-sidebar__item" href="<?php echo esc_url( admin_url( 'admin.php?page=winshirt-lotteries-settings' ) ); ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <span><?php esc_html_e( 'Paramètres', 'loterie-manager' ); ?></span>
                        </a>
                    </nav>
                </aside>
                <main class="lm-main">
                    <header class="lm-topbar">
                        <div class="lm-topbar__titles">
                            <h1><?php esc_html_e( 'Loteries', 'loterie-manager' ); ?></h1>
                            <p><?php esc_html_e( 'Pilotez vos opérations en un clin d’œil.', 'loterie-manager' ); ?></p>
                        </div>
                        <div class="lm-topbar__tools">
                            <form class="lm-topbar__filters" method="get">
                                <input type="hidden" name="page" value="winshirt-lotteries" />
                                <label class="lm-field lm-field--search">
                                    <span class="dashicons dashicons-search"></span>
                                    <input type="search" name="lm_search_global" value="<?php echo esc_attr( $search_global ); ?>" placeholder="<?php esc_attr_e( 'Rechercher une loterie…', 'loterie-manager' ); ?>" />
                                </label>
                                <label class="lm-field">
                                    <span class="screen-reader-text"><?php esc_html_e( 'Filtrer par statut', 'loterie-manager' ); ?></span>
                                    <select name="lm_status_filter">
                                        <option value=""><?php esc_html_e( 'Tous les statuts', 'loterie-manager' ); ?></option>
                                        <option value="active" <?php selected( 'active', $status_filter ); ?>><?php esc_html_e( 'En cours', 'loterie-manager' ); ?></option>
                                        <option value="complete" <?php selected( 'complete', $status_filter ); ?>><?php esc_html_e( 'Objectif atteint', 'loterie-manager' ); ?></option>
                                        <option value="closed" <?php selected( 'closed', $status_filter ); ?>><?php esc_html_e( 'Clôturée', 'loterie-manager' ); ?></option>
                                        <option value="draft" <?php selected( 'draft', $status_filter ); ?>><?php esc_html_e( 'Brouillon', 'loterie-manager' ); ?></option>
                                    </select>
                                </label>
                                <label class="lm-field">
                                    <span class="screen-reader-text"><?php esc_html_e( 'Filtrer par période', 'loterie-manager' ); ?></span>
                                    <select name="lm_period_filter">
                                        <option value=""><?php esc_html_e( 'Toutes les périodes', 'loterie-manager' ); ?></option>
                                        <option value="closing" <?php selected( 'closing', $period_filter ); ?>><?php esc_html_e( 'Se termine bientôt', 'loterie-manager' ); ?></option>
                                        <option value="upcoming" <?php selected( 'upcoming', $period_filter ); ?>><?php esc_html_e( 'À venir', 'loterie-manager' ); ?></option>
                                        <option value="past" <?php selected( 'past', $period_filter ); ?>><?php esc_html_e( 'Clôturées', 'loterie-manager' ); ?></option>
                                    </select>
                                </label>
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrer', 'loterie-manager' ); ?></button>
                            </form>
                            <div class="lm-topbar__action">
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la réinitialisation des compteurs ?', 'loterie-manager' ) ); ?>');">
                                    <?php wp_nonce_field( 'lm_reset_lottery_counters', 'lm_reset_lottery_counters_nonce' ); ?>
                                    <input type="hidden" name="action" value="lm_reset_lottery_counters" />
                                    <button type="submit" class="button button-secondary" <?php disabled( $has_active_sales ); ?>><?php esc_html_e( 'Réinitialiser les compteurs', 'loterie-manager' ); ?></button>
                                </form>
                                <?php if ( $has_active_sales ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la réinitialisation forcée ? Tous les tickets valides seront ignorés.', 'loterie-manager' ) ); ?>');" class="lm-topbar__action-force">
                                        <?php wp_nonce_field( 'lm_reset_lottery_counters', 'lm_reset_lottery_counters_nonce' ); ?>
                                        <input type="hidden" name="action" value="lm_reset_lottery_counters" />
                                        <input type="hidden" name="lm_force_reset" value="1" />
                                        <button type="submit" class="button button-secondary button-secondary--danger"><?php esc_html_e( 'Forcer la réinitialisation', 'loterie-manager' ); ?></button>
                                        <p class="description"><?php esc_html_e( 'Utilisez cette option uniquement après avoir nettoyé les commandes concernées.', 'loterie-manager' ); ?></p>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="lm-topbar__avatar">
                                <?php echo get_avatar( $current_user ? $current_user->ID : 0, 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </div>
                    </header>

                    <?php if ( $reset_notice ) : ?>
                        <div class="lm-notice-wrapper">
                            <div class="<?php echo esc_attr( $reset_notice_class ); ?>">
                                <p><?php echo esc_html( $reset_notice ); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <section class="lm-card lm-card--glass lm-toggle-card">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'lm_toggle_reassignment', 'lm_toggle_reassignment_nonce' ); ?>
                            <input type="hidden" name="action" value="lm_toggle_reassignment" />
                            <div class="lm-toggle-card__header">
                                <span class="lm-toggle-card__label"><?php esc_html_e( 'Réaffectation automatique des tickets', 'loterie-manager' ); ?></span>
                                <label class="lm-switch">
                                    <input type="checkbox" name="lm_reassignment" value="1" <?php checked( $reassignment_enabled, true ); ?> />
                                    <span class="lm-switch__track"></span>
                                </label>
                            </div>
                            <p class="lm-toggle-card__help"><?php esc_html_e( 'Si activée, les tickets liés à des commandes annulées ou remboursées cessent automatiquement d’être valides.', 'loterie-manager' ); ?></p>
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Mettre à jour', 'loterie-manager' ); ?>
                            </button>
                        </form>
                    </section>

                    <section class="lm-kpi-grid">
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'Loteries actives', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( number_format_i18n( $totals['active'] ) ); ?></p>
                        </article>
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'Tickets valides totaux', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( number_format_i18n( $totals['valid'] ) ); ?></p>
                            <?php if ( $totals['capacity'] > 0 ) : ?>
                                <span class="lm-kpi__meta"><?php printf( esc_html__( '%1$s sur %2$s objectifs', 'loterie-manager' ), esc_html( number_format_i18n( $totals['valid'] ) ), esc_html( number_format_i18n( $totals['capacity'] ) ) ); ?></span>
                            <?php endif; ?>
                        </article>
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'CA cumulé', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( $this->format_currency( $totals['revenue'] ) ); ?></p>
                        </article>
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'Taux de conversion billets', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( $avg_conversion ); ?><span>%</span></p>
                        </article>
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'Loteries proches de la fin', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( number_format_i18n( $totals['closing'] ) ); ?></p>
                        </article>
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'Alertes', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( number_format_i18n( $totals['alerts'] ) ); ?></p>
                        </article>
                    </section>

                    <section class="lm-card lm-card--glass">
                        <header class="lm-card__header">
                            <div>
                                <h2><?php esc_html_e( 'Progression dans le temps', 'loterie-manager' ); ?></h2>
                                <p><?php esc_html_e( 'Tickets vendus vs. objectif global.', 'loterie-manager' ); ?></p>
                            </div>
                        </header>
                        <?php if ( empty( $timeline ) ) : ?>
                            <div class="lm-empty">
                                <span class="dashicons dashicons-chart-line"></span>
                                <p><?php esc_html_e( 'Pas encore de données disponibles.', 'loterie-manager' ); ?></p>
                            </div>
                        <?php else :
                            $max_value = max( $timeline );
                            $max_value = $max_value > 0 ? $max_value : 1;
                            ?>
                            <div class="lm-chart">
                                <?php foreach ( $timeline as $day => $count ) :
                                    $height = max( 6, ( $count / $max_value ) * 100 );
                                    ?>
                                    <div class="lm-chart__bar" style="height: <?php echo esc_attr( $height ); ?>%">
                                        <span class="lm-chart__tooltip">
                                            <strong><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $day ) ) ); ?></strong>
                                            <span><?php printf( esc_html__( '%d tickets', 'loterie-manager' ), $count ); ?></span>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="lm-lotteries">
                        <header class="lm-card__header">
                            <h2><?php esc_html_e( 'Liste des loteries', 'loterie-manager' ); ?></h2>
                        </header>
                        <?php if ( empty( $filtered_lotteries ) ) : ?>
                            <div class="lm-empty">
                                <span class="dashicons dashicons-megaphone"></span>
                                <p><?php esc_html_e( 'Aucune loterie ne correspond à votre recherche.', 'loterie-manager' ); ?></p>
                                <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Créer une loterie', 'loterie-manager' ); ?></a>
                            </div>
                        <?php else : ?>
                            <div class="lm-lotteries__grid">
                                <?php foreach ( $filtered_lotteries as $entry ) :
                                    $detail_url  = add_query_arg(
                                        array(
                                            'page'    => 'winshirt-lotteries',
                                            'loterie' => $entry['id'],
                                        ),
                                        admin_url( 'admin.php' )
                                    );
                                    $export_url  = add_query_arg(
                                        array(
                                            'page'      => 'winshirt-lotteries',
                                            'lm_export' => $entry['id'],
                                        ),
                                        admin_url( 'admin.php' )
                                    );
                                    $draw_url    = add_query_arg(
                                        array(
                                            'page'          => 'winshirt-lotteries',
                                            'loterie'       => $entry['id'],
                                            'lm_draw_step'  => 'prepare',
                                        ),
                                        admin_url( 'admin.php' )
                                    );
                                    ?>
                                    <article class="lm-lottery-card lm-card lm-card--glass">
                                        <header class="lm-lottery-card__header">
                                            <div>
                                                <span class="lm-status <?php echo esc_attr( $entry['status_class'] ); ?>"><?php echo esc_html( $entry['status_label'] ); ?></span>
                                                <h3><?php echo esc_html( $entry['title'] ); ?></h3>
                                                <p><?php echo esc_html( $entry['period'] ); ?></p>
                                            </div>
                                            <div class="lm-lottery-card__progress">
                                                <span><?php echo esc_html( $entry['progress'] ); ?>%</span>
                                                <div class="lm-progress"><span style="width: <?php echo esc_attr( $entry['progress'] ); ?>%"></span></div>
                                                <small><?php printf( esc_html__( '%1$d / %2$s tickets valides', 'loterie-manager' ), intval( $entry['valid_tickets'] ), $entry['capacity'] > 0 ? intval( $entry['capacity'] ) : 0 ); ?></small>
                                            </div>
                                        </header>
                                        <ul class="lm-lottery-card__stats">
                                            <li><span><?php esc_html_e( 'Participants uniques', 'loterie-manager' ); ?></span><strong><?php echo esc_html( number_format_i18n( $entry['participants'] ) ); ?></strong></li>
                                            <li><span><?php esc_html_e( 'CA lié', 'loterie-manager' ); ?></span><strong><?php echo esc_html( $this->format_currency( $entry['revenue'] ) ); ?></strong></li>
                                            <li><span><?php esc_html_e( 'Conversion billets', 'loterie-manager' ); ?></span><strong><?php echo esc_html( $entry['conversion_rate'] ); ?>%</strong></li>
                                        </ul>
                                        <div class="lm-lottery-card__actions">
                                            <a class="button button-primary" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Ouvrir', 'loterie-manager' ); ?></a>
                                            <a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Exporter participants (Huissier)', 'loterie-manager' ); ?></a>
                                            <?php if ( $entry['ready_for_draw'] ) : ?>
                                                <a class="button button-secondary" href="<?php echo esc_url( $draw_url ); ?>"><?php esc_html_e( 'Tirage manuel', 'loterie-manager' ); ?></a>
                                            <?php else : ?>
                                                <span class="lm-action-disabled" title="<?php esc_attr_e( 'Disponible lorsque la loterie est prête.', 'loterie-manager' ); ?>"><?php esc_html_e( 'Tirage manuel indisponible', 'loterie-manager' ); ?></span>
                                            <?php endif; ?>
                                            <details class="lm-more-actions">
                                                <summary><?php esc_html_e( 'Plus…', 'loterie-manager' ); ?></summary>
                                                <div>
                                                    <a href="<?php echo esc_url( get_edit_post_link( $entry['id'] ) ); ?>"><?php esc_html_e( 'Modifier', 'loterie-manager' ); ?></a>
                                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'post.php?action=trash&post=' . $entry['id'] ), 'trash-post_' . $entry['id'] ) ); ?>"><?php esc_html_e( 'Archiver', 'loterie-manager' ); ?></a>
                                                </div>
                                            </details>
                                            <form class="lm-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la réinitialisation des compteurs ?', 'loterie-manager' ) ); ?>');">
                                                <?php wp_nonce_field( 'lm_reset_lottery_counters', 'lm_reset_lottery_counters_nonce' ); ?>
                                                <input type="hidden" name="action" value="lm_reset_lottery_counters" />
                                                <input type="hidden" name="loterie_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
                                                <button type="submit" class="button button-secondary" <?php disabled( intval( $entry['valid_tickets'] ) > 0 ); ?>><?php esc_html_e( 'Réinitialiser les compteurs', 'loterie-manager' ); ?></button>
                                            </form>
                                            <?php if ( intval( $entry['valid_tickets'] ) > 0 ) : ?>
                                                <form class="lm-inline-form lm-inline-form--force" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la réinitialisation forcée ? Tous les tickets valides seront ignorés.', 'loterie-manager' ) ); ?>');">
                                                    <?php wp_nonce_field( 'lm_reset_lottery_counters', 'lm_reset_lottery_counters_nonce' ); ?>
                                                    <input type="hidden" name="action" value="lm_reset_lottery_counters" />
                                                    <input type="hidden" name="loterie_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
                                                    <input type="hidden" name="lm_force_reset" value="1" />
                                                    <button type="submit" class="button button-secondary button-secondary--danger"><?php esc_html_e( 'Forcer la réinitialisation', 'loterie-manager' ); ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ( ! empty( $entry['alerts'] ) ) : ?>
                                            <ul class="lm-lottery-card__alerts">
                                                <?php foreach ( $entry['alerts'] as $alert ) : ?>
                                                    <li><?php echo esc_html( $alert ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if ( $export_context ) :
                        $estimate = 0;
                        foreach ( $export_context['tickets_all'] as $ticket ) {
                            if ( in_array( $ticket['status'], array( 'valid', 'winner', 'alternate' ), true ) ) {
                                $estimate++;
                            }
                        }
                        ?>
                        <div class="lm-modal is-open" role="dialog" aria-modal="true">
                            <div class="lm-modal__dialog">
                                <header class="lm-modal__header">
                                    <h2><?php esc_html_e( 'Exporter participants (Huissier)', 'loterie-manager' ); ?></h2>
                                    <a class="lm-modal__close" href="<?php echo esc_url( remove_query_arg( 'lm_export' ) ); ?>" aria-label="<?php esc_attr_e( 'Fermer', 'loterie-manager' ); ?>">&times;</a>
                                </header>
                                <div class="lm-modal__content">
                                    <p><?php esc_html_e( 'Le fichier contient uniquement les tickets valides et leurs données nécessaires.', 'loterie-manager' ); ?></p>
                                    <p class="lm-modal__meta"><?php printf( esc_html__( 'Estimé : %d lignes.', 'loterie-manager' ), $estimate ); ?></p>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lm-modal__form">
                                        <?php wp_nonce_field( 'lm_export_participants_' . $export_context['id'], 'lm_export_nonce' ); ?>
                                        <input type="hidden" name="action" value="lm_export_participants" />
                                        <input type="hidden" name="loterie_id" value="<?php echo esc_attr( $export_context['id'] ); ?>" />
                                        <label>
                                            <span><?php esc_html_e( 'Période (optionnelle)', 'loterie-manager' ); ?></span>
                                            <div class="lm-modal__range">
                                                <input type="date" name="lm_export_start" />
                                                <span>→</span>
                                                <input type="date" name="lm_export_end" />
                                            </div>
                                        </label>
                                        <p class="lm-modal__hint"><?php esc_html_e( 'Chaque export est journalisé avec date, heure et opérateur.', 'loterie-manager' ); ?></p>
                                        <div class="lm-modal__actions">
                                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Exporter (CSV)', 'loterie-manager' ); ?></button>
                                            <a class="button" href="<?php echo esc_url( remove_query_arg( 'lm_export' ) ); ?>"><?php esc_html_e( 'Annuler', 'loterie-manager' ); ?></a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
            <?php
            echo ob_get_clean();
        }
        /**
         * Renders the detail screen for a specific loterie.
         *
         * @param int $loterie_id Loterie ID.
         */
        private function render_admin_lottery_detail( $loterie_id ) {
            $post = get_post( $loterie_id );

            if ( ! $post ) {
                echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Loterie introuvable.', 'loterie-manager' ) . '</p></div></div>';
                return;
            }

            $search        = isset( $_GET['lm_search'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_search'] ) ) : '';
            $status_filter = isset( $_GET['lm_status'] ) ? sanitize_key( wp_unslash( $_GET['lm_status'] ) ) : '';
            $paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
            $draw_step     = isset( $_GET['lm_draw_step'] ) ? sanitize_key( wp_unslash( $_GET['lm_draw_step'] ) ) : '';

            $flow_state = array(
                'exclude_cancelled' => true,
                'public_seed'       => '',
                'confirmed'         => false,
            );
            $flow_summary = null;
            $draw_errors  = array();

            if ( 'prepare' === $draw_step && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['lm_draw_step'] ) && 'validate' === sanitize_key( wp_unslash( $_POST['lm_draw_step'] ) ) ) {
                if ( empty( $_POST['lm_manual_draw_flow_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_manual_draw_flow_nonce'] ) ), 'lm_manual_draw_flow_' . $loterie_id ) ) {
                    $draw_errors[] = __( 'Jeton de sécurité invalide.', 'loterie-manager' );
                } else {
                    $flow_state['exclude_cancelled'] = ! empty( $_POST['lm_draw_exclude_cancelled'] );
                    $flow_state['public_seed']       = isset( $_POST['lm_public_seed'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_public_seed'] ) ) : '';
                    $flow_state['confirmed']         = ! empty( $_POST['lm_draw_confirm_participants'] );

                    if ( ! $flow_state['confirmed'] ) {
                        $draw_errors[] = __( 'Veuillez confirmer la vérification de la liste des participants.', 'loterie-manager' );
                    } else {
                        $draw_step    = 'validate';
                        $flow_summary = $this->prepare_manual_draw_pool(
                            $loterie_id,
                            array(
                                'exclude_cancelled' => $flow_state['exclude_cancelled'],
                            )
                        );
                    }
                }
            }

            $stats = $this->get_lottery_stats(
                $loterie_id,
                array(
                    'include_tickets' => true,
                    'search'          => $search,
                    'status_filter'   => $status_filter,
                    'paged'           => $paged,
                )
            );

            $tickets       = isset( $stats['tickets'] ) ? $stats['tickets'] : array();
            $logs          = $this->get_lottery_logs( $loterie_id );
            $reports       = $this->get_manual_draw_reports( $loterie_id );
            $mode          = get_post_meta( $loterie_id, self::META_REASSIGNMENT_MODE, true );
            $mode          = in_array( $mode, array( 'inherit', 'enabled', 'disabled' ), true ) ? $mode : 'inherit';
            $redirect      = admin_url( 'admin.php?page=winshirt-lotteries&loterie=' . $loterie_id );
            $draw_success  = isset( $_GET['lm_draw_success'] );
            $report_id     = isset( $_GET['lm_report'] ) ? sanitize_text_field( wp_unslash( $_GET['lm_report'] ) ) : '';
            $export_target = isset( $_GET['lm_export'] ) ? absint( $_GET['lm_export'] ) : 0;

            if ( 'validate' === $draw_step && null === $flow_summary ) {
                $flow_summary = $this->prepare_manual_draw_pool(
                    $loterie_id,
                    array(
                        'exclude_cancelled' => $flow_state['exclude_cancelled'],
                    )
                );
            }

            if ( empty( $flow_summary ) && 'validate' === $draw_step ) {
                $draw_step = 'prepare';
            }

            $report_entry = null;
            if ( $report_id ) {
                foreach ( $reports as $entry ) {
                    if ( isset( $entry['id'] ) && $entry['id'] === $report_id ) {
                        $report_entry = $entry;
                        break;
                    }
                }
            }

            $flow_current = 1;
            if ( 'validate' === $draw_step ) {
                $flow_current = 2;
            }
            if ( $draw_success && $report_entry ) {
                $flow_current = 3;
            }

            $thumbnail_url = get_the_post_thumbnail_url( $post, 'large' );
            $detail_start  = ! empty( $stats['start_date'] ) ? strtotime( $stats['start_date'] ) : strtotime( $post->post_date );
            $period_label  = $detail_start ? date_i18n( get_option( 'date_format' ), $detail_start ) : __( 'Non défini', 'loterie-manager' );
            if ( ! empty( $stats['end_date'] ) ) {
                $period_label .= ' → ' . date_i18n( get_option( 'date_format' ), strtotime( $stats['end_date'] ) );
            }
            $status_class  = $stats['status_class'];
            $status_label  = $stats['status_label'];
            $progress      = $stats['progress'];

            $mode_labels = array(
                'inherit'  => $stats['global_reassignment'] ? __( 'Hérite (ON)', 'loterie-manager' ) : __( 'Hérite (OFF)', 'loterie-manager' ),
                'enabled'  => __( 'Forcer ON', 'loterie-manager' ),
                'disabled' => __( 'Forcer OFF', 'loterie-manager' ),
            );

            $timeline_entries = array();
            foreach ( $logs as $entry ) {
                $timeline_entries[] = array(
                    'date'    => isset( $entry['timestamp'] ) ? intval( $entry['timestamp'] ) : 0,
                    'message' => $entry['message'],
                );
            }

            $report_download_url = '';
            if ( $report_entry ) {
                $report_download_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action'    => 'lm_download_draw_report',
                            'loterie'   => $loterie_id,
                            'report_id' => $report_entry['id'],
                        ),
                        admin_url( 'admin-post.php' )
                    ),
                    'lm_download_draw_report_' . $loterie_id
                );
            }

            $export_context = null;
            if ( $export_target === $loterie_id ) {
                $export_context = $stats;
            }

            $winner        = array();
            $alternates    = array();
            if ( $report_entry && ! empty( $report_entry['winners'] ) ) {
                $winner     = $report_entry['winners'][0];
                $alternates = array_slice( $report_entry['winners'], 1 );
            }

            $manual_reports = array_slice( $reports, 0, 5 );

            $draw_ready = $stats['ready_for_draw'];

            $reset_status = isset( $_GET['lm_reset_status'] ) ? sanitize_key( wp_unslash( $_GET['lm_reset_status'] ) ) : '';
            $reset_target = isset( $_GET['lm_reset_target'] ) ? absint( wp_unslash( $_GET['lm_reset_target'] ) ) : 0;
            $reset_target_post = $reset_target ? get_post( $reset_target ) : null;
            $reset_target_title = ( $reset_target_post && 'post' === $reset_target_post->post_type ) ? get_the_title( $reset_target_post ) : '';
            $reset_notice = '';
            $reset_notice_class = 'notice notice-success';

            if ( 'success' === $reset_status ) {
                if ( $reset_target && '' !== $reset_target_title ) {
                    /* translators: %s: lottery title. */
                    $reset_notice = sprintf( __( 'Les compteurs de « %s » ont été réinitialisés.', 'loterie-manager' ), $reset_target_title );
                } elseif ( $reset_target ) {
                    $reset_notice = __( 'Les compteurs de la loterie sélectionnée ont été réinitialisés.', 'loterie-manager' );
                } else {
                    $reset_notice = __( 'Les compteurs ont été réinitialisés.', 'loterie-manager' );
                }
                $reset_notice_class = 'notice notice-success';
            } elseif ( 'active' === $reset_status ) {
                if ( $reset_target && '' !== $reset_target_title ) {
                    /* translators: %s: lottery title. */
                    $reset_notice = sprintf( __( 'Réinitialisation impossible pour « %s » : des tickets valides sont encore enregistrés.', 'loterie-manager' ), $reset_target_title );
                } else {
                    $reset_notice = __( 'Réinitialisation impossible : des tickets valides sont encore enregistrés.', 'loterie-manager' );
                }
                $reset_notice_class = 'notice notice-error';
            } elseif ( 'forced' === $reset_status ) {
                if ( $reset_target && '' !== $reset_target_title ) {
                    /* translators: %s: loterie title. */
                    $reset_notice = sprintf( __( 'Réinitialisation forcée effectuée pour « %s ». Vérifiez les commandes associées.', 'loterie-manager' ), $reset_target_title );
                } elseif ( $reset_target ) {
                    $reset_notice = __( 'Réinitialisation forcée effectuée sur la loterie sélectionnée.', 'loterie-manager' );
                } else {
                    $reset_notice = __( 'Réinitialisation forcée effectuée.', 'loterie-manager' );
                }
                $reset_notice_class = 'notice notice-warning';
            } elseif ( 'nonce' === $reset_status ) {
                $reset_notice       = __( 'La vérification de sécurité a échoué. Merci de réessayer.', 'loterie-manager' );
                $reset_notice_class = 'notice notice-error';
            } elseif ( 'empty' === $reset_status ) {
                $reset_notice       = __( 'Aucune loterie n’a été trouvée pour la réinitialisation.', 'loterie-manager' );
                $reset_notice_class = 'notice notice-warning';
            }

            ob_start();
            ?>
            <div class="lm-app lm-app--dark">
                <aside class="lm-sidebar">
                    <div class="lm-sidebar__brand">WinShirt</div>
                    <nav class="lm-sidebar__nav">
                        <a class="lm-sidebar__item" href="<?php echo esc_url( admin_url( 'admin.php?page=winshirt-lotteries' ) ); ?>">
                            <span class="dashicons dashicons-tickets"></span>
                            <span><?php esc_html_e( 'Loteries', 'loterie-manager' ); ?></span>
                        </a>
                        <a class="lm-sidebar__item is-active" href="<?php echo esc_url( admin_url( 'admin.php?page=winshirt-lotteries&loterie=' . $loterie_id ) ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            <span><?php esc_html_e( 'Détail', 'loterie-manager' ); ?></span>
                        </a>
                    </nav>
                </aside>
                <main class="lm-main">
                    <a class="lm-breadcrumb" href="<?php echo esc_url( admin_url( 'admin.php?page=winshirt-lotteries' ) ); ?>">&larr; <?php esc_html_e( 'Retour au tableau de bord', 'loterie-manager' ); ?></a>

                    <?php if ( $reset_notice ) : ?>
                        <div class="lm-notice-wrapper">
                            <div class="<?php echo esc_attr( $reset_notice_class ); ?>">
                                <p><?php echo esc_html( $reset_notice ); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <section class="lm-card lm-card--hero">
                        <div class="lm-hero">
                            <div class="lm-hero__media"<?php if ( $thumbnail_url ) : ?> style="background-image:url('<?php echo esc_url( $thumbnail_url ); ?>')"<?php endif; ?>>
                                <?php if ( ! $thumbnail_url ) : ?>
                                    <span class="dashicons dashicons-format-gallery"></span>
                                <?php endif; ?>
                            </div>
                            <div class="lm-hero__content">
                                <span class="lm-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                                <h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
                                <p class="lm-hero__period"><?php echo esc_html( $period_label ); ?></p>
                                <div class="lm-hero__progress">
                                    <div class="lm-progress"><span style="width: <?php echo esc_attr( $progress ); ?>%"></span></div>
                                    <span><?php printf( esc_html__( '%1$s%% de l’objectif atteint (%2$d / %3$s tickets)', 'loterie-manager' ), number_format_i18n( $progress, 1 ), intval( $stats['valid_tickets'] ), $stats['capacity'] > 0 ? intval( $stats['capacity'] ) : 0 ); ?></span>
                                </div>
                            </div>
                            <div class="lm-hero__actions">
                                <div class="lm-hero__buttons">
                                    <?php if ( $draw_ready ) : ?>
                                        <a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'lm_draw_step' => 'prepare' ), $redirect ) ); ?>"><?php esc_html_e( 'Tirage manuel', 'loterie-manager' ); ?></a>
                                    <?php else : ?>
                                        <button class="button button-primary" type="button" disabled title="<?php esc_attr_e( 'Disponible lorsque la loterie est prête à être tirée.', 'loterie-manager' ); ?>"><?php esc_html_e( 'Tirage manuel', 'loterie-manager' ); ?></button>
                                    <?php endif; ?>
                                    <a class="button" href="<?php echo esc_url( add_query_arg( array( 'lm_export' => $loterie_id ), $redirect ) ); ?>"><?php esc_html_e( 'Exporter participants (Huissier)', 'loterie-manager' ); ?></a>
                                </div>
                                <form class="lm-hero__toggle" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <?php wp_nonce_field( 'lm_toggle_loterie_reassignment_' . $loterie_id, 'lm_toggle_loterie_nonce' ); ?>
                                    <input type="hidden" name="action" value="lm_toggle_loterie_reassignment" />
                                    <input type="hidden" name="loterie_id" value="<?php echo esc_attr( $loterie_id ); ?>" />
                                    <input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>" />
                                    <label for="lm_reassignment_mode"><span><?php esc_html_e( 'Réaffectation automatique', 'loterie-manager' ); ?></span></label>
                                    <select id="lm_reassignment_mode" name="lm_reassignment_mode">
                                        <option value="inherit" <?php selected( 'inherit', $mode ); ?>><?php echo esc_html( $mode_labels['inherit'] ); ?></option>
                                        <option value="enabled" <?php selected( 'enabled', $mode ); ?>><?php echo esc_html( $mode_labels['enabled'] ); ?></option>
                                        <option value="disabled" <?php selected( 'disabled', $mode ); ?>><?php echo esc_html( $mode_labels['disabled'] ); ?></option>
                                    </select>
                                    <p class="lm-hero__toggle-note"><?php printf( esc_html__( 'État effectif : %s', 'loterie-manager' ), $stats['reassignment_enabled'] ? esc_html__( 'Activée', 'loterie-manager' ) : esc_html__( 'Désactivée', 'loterie-manager' ) ); ?></p>
                                    <button type="submit" class="button button-secondary"><?php esc_html_e( 'Mettre à jour', 'loterie-manager' ); ?></button>
                                </form>
                                <div class="lm-hero__reset">
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la réinitialisation des compteurs ?', 'loterie-manager' ) ); ?>');">
                                        <?php wp_nonce_field( 'lm_reset_lottery_counters', 'lm_reset_lottery_counters_nonce' ); ?>
                                        <input type="hidden" name="action" value="lm_reset_lottery_counters" />
                                        <input type="hidden" name="loterie_id" value="<?php echo esc_attr( $loterie_id ); ?>" />
                                        <button type="submit" class="button button-secondary" <?php disabled( intval( $stats['valid_tickets'] ) > 0 ); ?>><?php esc_html_e( 'Réinitialiser les compteurs', 'loterie-manager' ); ?></button>
                                    </form>
                                    <?php if ( intval( $stats['valid_tickets'] ) > 0 ) : ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la réinitialisation forcée ? Tous les tickets valides seront ignorés.', 'loterie-manager' ) ); ?>');" class="lm-hero__reset-force">
                                            <?php wp_nonce_field( 'lm_reset_lottery_counters', 'lm_reset_lottery_counters_nonce' ); ?>
                                            <input type="hidden" name="action" value="lm_reset_lottery_counters" />
                                            <input type="hidden" name="loterie_id" value="<?php echo esc_attr( $loterie_id ); ?>" />
                                            <input type="hidden" name="lm_force_reset" value="1" />
                                            <button type="submit" class="button button-secondary button-secondary--danger"><?php esc_html_e( 'Réinitialiser malgré les tickets valides', 'loterie-manager' ); ?></button>
                                            <p class="lm-hero__reset-note"><?php esc_html_e( 'Utilisez cette option après avoir supprimé les commandes de test pour purger les compteurs restants.', 'loterie-manager' ); ?></p>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="lm-kpi-grid lm-kpi-grid--detail">
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'Tickets valides', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( number_format_i18n( $stats['valid_tickets'] ) ); ?></p>
                        </article>
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'Participants uniques', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( number_format_i18n( $stats['unique_participants'] ) ); ?></p>
                        </article>
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'CA lié', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( $this->format_currency( $stats['revenue'] ) ); ?></p>
                        </article>
                        <article class="lm-kpi">
                            <h3><?php esc_html_e( 'Conversion billets', 'loterie-manager' ); ?></h3>
                            <p><?php echo esc_html( number_format_i18n( $stats['conversion_rate'], 1 ) ); ?><span>%</span></p>
                        </article>
                    </section>

                    <?php if ( 'prepare' === $draw_step || 'validate' === $draw_step ) : ?>
                        <section class="lm-card lm-card--glass lm-manual-draw">
                            <header class="lm-card__header">
                                <div>
                                    <h2><?php esc_html_e( 'Tirage manuel', 'loterie-manager' ); ?></h2>
                                    <p><?php esc_html_e( 'Flux en 3 étapes pour garantir un tirage traçable.', 'loterie-manager' ); ?></p>
                                </div>
                                <ol class="lm-steps">
                                    <li class="<?php echo 1 === $flow_current ? 'is-active' : ( $flow_current > 1 ? 'is-complete' : '' ); ?>"><?php esc_html_e( 'Préparer', 'loterie-manager' ); ?></li>
                                    <li class="<?php echo 2 === $flow_current ? 'is-active' : ( $flow_current > 2 ? 'is-complete' : '' ); ?>"><?php esc_html_e( 'Valider', 'loterie-manager' ); ?></li>
                                    <li class="<?php echo 3 === $flow_current ? 'is-active' : ''; ?>"><?php esc_html_e( 'Résultat', 'loterie-manager' ); ?></li>
                                </ol>
                            </header>
                            <?php if ( ! empty( $draw_errors ) ) : ?>
                                <div class="lm-alert lm-alert--error">
                                    <?php foreach ( $draw_errors as $error ) : ?>
                                        <p><?php echo esc_html( $error ); ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ( 'validate' === $draw_step && $flow_summary ) : ?>
                                <div class="lm-manual-draw__body">
                                    <h3><?php esc_html_e( 'Étape 2 : valider les paramètres', 'loterie-manager' ); ?></h3>
                                    <ul class="lm-manual-draw__summary">
                                        <li><?php printf( esc_html__( '%d tickets analysés', 'loterie-manager' ), intval( $flow_summary['total_source'] ) ); ?></li>
                                        <li><?php printf( esc_html__( '%d tickets invalidés (statut)', 'loterie-manager' ), intval( $flow_summary['excluded_status'] ) ); ?></li>
                                        <?php if ( $flow_state['exclude_cancelled'] ) : ?>
                                            <li><?php printf( esc_html__( '%d exclusions commandes annulées / non payées', 'loterie-manager' ), intval( $flow_summary['excluded_orders'] ) ); ?></li>
                                        <?php endif; ?>
                                        <li><?php printf( esc_html__( '%d tickets éligibles au tirage', 'loterie-manager' ), count( $flow_summary['tickets'] ) ); ?></li>
                                        <li><?php printf( esc_html__( 'Aléa public : %s', 'loterie-manager' ), $flow_state['public_seed'] ? esc_html( $flow_state['public_seed'] ) : esc_html__( 'Non renseigné', 'loterie-manager' ) ); ?></li>
                                    </ul>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lm-manual-draw__form">
                                        <?php wp_nonce_field( 'lm_manual_draw_' . $loterie_id, 'lm_manual_draw_nonce' ); ?>
                                        <input type="hidden" name="action" value="lm_manual_draw" />
                                        <input type="hidden" name="loterie_id" value="<?php echo esc_attr( $loterie_id ); ?>" />
                                        <input type="hidden" name="lm_draw_exclude_cancelled" value="<?php echo $flow_state['exclude_cancelled'] ? '1' : '0'; ?>" />
                                        <input type="hidden" name="lm_public_seed" value="<?php echo esc_attr( $flow_state['public_seed'] ); ?>" />
                                        <input type="hidden" name="lm_draw_confirm" value="1" />
                                        <div class="lm-field-group">
                                            <label for="lm_alternate_count"><span><?php esc_html_e( 'Nombre de suppléants', 'loterie-manager' ); ?></span></label>
                                            <input type="number" id="lm_alternate_count" name="lm_alternate_count" min="0" max="3" value="<?php echo esc_attr( min( 3, max( 0, isset( $_POST['lm_alternate_count'] ) ? absint( $_POST['lm_alternate_count'] ) : 0 ) ) ); ?>" />
                                            <p class="description"><?php esc_html_e( 'Entre 0 et 3 suppléants seront tirés après le gagnant.', 'loterie-manager' ); ?></p>
                                        </div>
                                        <div class="lm-manual-draw__actions">
                                            <a class="button" href="<?php echo esc_url( add_query_arg( array( 'lm_draw_step' => 'prepare' ), $redirect ) ); ?>"><?php esc_html_e( 'Retour', 'loterie-manager' ); ?></a>
                                            <button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Confirmer le lancement du tirage ?', 'loterie-manager' ) ); ?>');"><?php esc_html_e( 'Lancer le tirage', 'loterie-manager' ); ?></button>
                                        </div>
                                    </form>
                                </div>
                            <?php else : ?>
                                <div class="lm-manual-draw__body">
                                    <h3><?php esc_html_e( 'Étape 1 : préparer le tirage', 'loterie-manager' ); ?></h3>
                                    <p class="lm-manual-draw__intro"><?php esc_html_e( 'Indiquez les options de filtrage et consignez un éventuel aléa public avant de passer à la validation.', 'loterie-manager' ); ?></p>
                                    <form method="post" action="<?php echo esc_url( add_query_arg( array( 'lm_draw_step' => 'prepare' ), $redirect ) ); ?>" class="lm-manual-draw__form">
                                        <?php wp_nonce_field( 'lm_manual_draw_flow_' . $loterie_id, 'lm_manual_draw_flow_nonce' ); ?>
                                        <input type="hidden" name="lm_draw_step" value="validate" />
                                        <label class="lm-checkbox">
                                            <input type="checkbox" name="lm_draw_exclude_cancelled" value="1" <?php checked( $flow_state['exclude_cancelled'], true ); ?> />
                                            <span><?php esc_html_e( 'Exclure automatiquement les commandes annulées/non payées.', 'loterie-manager' ); ?></span>
                                        </label>
                                        <label class="lm-checkbox">
                                            <input type="checkbox" name="lm_draw_confirm_participants" value="1" />
                                            <span><?php esc_html_e( 'J’ai vérifié que la liste des participants est correcte.', 'loterie-manager' ); ?></span>
                                        </label>
                                        <label class="lm-field">
                                            <span><?php esc_html_e( 'Aléa public (facultatif)', 'loterie-manager' ); ?></span>
                                            <input type="text" name="lm_public_seed" value="<?php echo esc_attr( $flow_state['public_seed'] ); ?>" placeholder="<?php esc_attr_e( 'Référence externe, code de tirage…', 'loterie-manager' ); ?>" />
                                            <p class="description"><?php esc_html_e( 'Indice public pour documenter la loyauté du tirage.', 'loterie-manager' ); ?></p>
                                        </label>
                                        <div class="lm-manual-draw__actions">
                                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Suivant', 'loterie-manager' ); ?></button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <?php if ( $draw_success && $report_entry ) : ?>
                        <section class="lm-card lm-card--glass lm-draw-result">
                            <header class="lm-card__header">
                                <div>
                                    <h2><?php esc_html_e( 'Résultat du tirage', 'loterie-manager' ); ?></h2>
                                    <p><?php esc_html_e( 'Rapport journalisé et disponible au téléchargement.', 'loterie-manager' ); ?></p>
                                </div>
                                <div class="lm-draw-result__actions">
                                    <?php if ( $report_download_url ) : ?>
                                        <a class="button button-primary" href="<?php echo esc_url( $report_download_url ); ?>"><?php esc_html_e( 'Télécharger le rapport', 'loterie-manager' ); ?></a>
                                    <?php endif; ?>
                                    <a class="button" href="<?php echo esc_url( add_query_arg( array(), remove_query_arg( array( 'lm_draw_success', 'lm_report' ) ) ) ); ?>"><?php esc_html_e( 'Fermer', 'loterie-manager' ); ?></a>
                                    <a class="button button-link" href="#lm-journal"><?php esc_html_e( 'Voir dans le Journal', 'loterie-manager' ); ?></a>
                                </div>
                            </header>
                            <div class="lm-draw-result__body">
                                <div class="lm-draw-result__winner">
                                    <h3><?php esc_html_e( 'Ticket gagnant', 'loterie-manager' ); ?></h3>
                                    <p class="lm-draw-result__ticket">#<?php echo esc_html( $winner['ticket_number'] ?? '' ); ?></p>
                                    <p><?php echo esc_html( $winner['participant'] ?? '' ); ?> &middot; <?php echo esc_html( $winner['email'] ?? '' ); ?></p>
                                    <p><?php printf( esc_html__( 'Commande %s', 'loterie-manager' ), esc_html( $winner['order_number'] ?? '' ) ); ?></p>
                                </div>
                                <?php if ( ! empty( $alternates ) ) : ?>
                                    <div class="lm-draw-result__alternates">
                                        <h3><?php esc_html_e( 'Suppléants', 'loterie-manager' ); ?></h3>
                                        <ol>
                                            <?php foreach ( $alternates as $alternate ) : ?>
                                                <li>
                                                    <strong>#<?php echo esc_html( $alternate['ticket_number'] ); ?></strong>
                                                    <span><?php echo esc_html( $alternate['participant'] ); ?> &middot; <?php echo esc_html( $alternate['email'] ); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="lm-card lm-card--glass">
                        <header class="lm-card__header">
                            <div>
                                <h2><?php esc_html_e( 'Tickets & participants', 'loterie-manager' ); ?></h2>
                                <p><?php esc_html_e( 'Recherche, filtres et pagination intégrés.', 'loterie-manager' ); ?></p>
                            </div>
                            <form class="lm-topbar__filters" method="get">
                                <input type="hidden" name="page" value="winshirt-lotteries" />
                                <input type="hidden" name="loterie" value="<?php echo esc_attr( $loterie_id ); ?>" />
                                <label class="lm-field lm-field--search">
                                    <span class="dashicons dashicons-search"></span>
                                    <input type="search" name="lm_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Recherche plein texte…', 'loterie-manager' ); ?>" />
                                </label>
                                <label class="lm-field">
                                    <span class="screen-reader-text"><?php esc_html_e( 'Filtrer par statut', 'loterie-manager' ); ?></span>
                                    <select name="lm_status">
                                        <?php
                                        $status_options = array(
                                            ''          => __( 'Tous les statuts', 'loterie-manager' ),
                                            'valid'     => __( 'Valide', 'loterie-manager' ),
                                            'invalid'   => __( 'Invalidé', 'loterie-manager' ),
                                            'winner'    => __( 'Gagnant', 'loterie-manager' ),
                                            'alternate' => __( 'Suppléant', 'loterie-manager' ),
                                        );
                                        foreach ( $status_options as $value => $label ) {
                                            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $status_filter, $value, false ), esc_html( $label ) );
                                        }
                                        ?>
                                    </select>
                                </label>
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrer', 'loterie-manager' ); ?></button>
                            </form>
                        </header>
                        <?php if ( empty( $tickets ) ) : ?>
                            <div class="lm-empty">
                                <span class="dashicons dashicons-groups"></span>
                                <p><?php esc_html_e( 'Aucun ticket ne correspond à votre recherche.', 'loterie-manager' ); ?></p>
                            </div>
                        <?php else : ?>
                            <div class="lm-table-wrapper">
                                <table class="lm-data-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'N° ticket', 'loterie-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Client', 'loterie-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Email', 'loterie-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Statut', 'loterie-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Commande', 'loterie-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Date', 'loterie-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Ville', 'loterie-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Pays', 'loterie-manager' ); ?></th>
                                            <th><?php esc_html_e( 'Notes', 'loterie-manager' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $tickets as $ticket ) : ?>
                                            <tr class="lm-ticket-row lm-ticket-row--status-<?php echo esc_attr( $ticket['status'] ); ?>">
                                                <td>
                                                    <strong><?php echo esc_html( $ticket['ticket_number'] ); ?></strong>
                                                    <span class="lm-ticket-note"><?php echo esc_html( $this->format_currency( $ticket['amount'] ) ); ?></span>
                                                </td>
                                                <td><?php echo esc_html( $ticket['customer_name'] ); ?></td>
                                                <td><a href="mailto:<?php echo esc_attr( $ticket['customer_email'] ); ?>"><?php echo esc_html( $ticket['customer_email'] ); ?></a></td>
                                                <td>
                                                    <span class="lm-status <?php echo esc_attr( $ticket['status'] ); ?>"><?php echo esc_html( $ticket['status_label'] ); ?></span>
                                                    <?php if ( ! empty( $ticket['status_note'] ) ) : ?>
                                                        <span class="lm-ticket-note"><?php echo esc_html( $ticket['status_note'] ); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>#<?php echo esc_html( $ticket['order_number'] ); ?></td>
                                                <td><?php echo esc_html( $this->format_admin_datetime( $ticket['order_date'] ) ); ?></td>
                                                <td><?php echo esc_html( $ticket['customer_city'] ); ?></td>
                                                <td><?php echo esc_html( $ticket['customer_country'] ); ?></td>
                                                <td><?php echo esc_html( $ticket['status_note'] ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $stats['total_pages'] ) && $stats['total_pages'] > 1 ) : ?>
                            <div class="lm-pagination">
                                <?php
                                echo wp_kses_post(
                                    paginate_links(
                                        array(
                                            'base'      => add_query_arg( array( 'paged' => '%#%' ), remove_query_arg( 'paged' ) ),
                                            'format'    => '',
                                            'prev_text' => __( '« Précédent', 'loterie-manager' ),
                                            'next_text' => __( 'Suivant »', 'loterie-manager' ),
                                            'total'     => max( 1, intval( $stats['total_pages'] ) ),
                                            'current'   => max( 1, intval( $stats['current_page'] ) ),
                                        )
                                    )
                                );
                                ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="lm-card lm-card--glass" id="lm-journal">
                        <header class="lm-card__header">
                            <div>
                                <h2><?php esc_html_e( 'Journal des événements', 'loterie-manager' ); ?></h2>
                                <p><?php esc_html_e( 'Réaffectations, exports, tirages… tout est tracé.', 'loterie-manager' ); ?></p>
                            </div>
                        </header>
                        <?php if ( empty( $timeline_entries ) ) : ?>
                            <div class="lm-empty">
                                <span class="dashicons dashicons-welcome-write-blog"></span>
                                <p><?php esc_html_e( 'Aucune action enregistrée pour le moment.', 'loterie-manager' ); ?></p>
                            </div>
                        <?php else : ?>
                            <ul class="lm-timeline">
                                <?php foreach ( $timeline_entries as $entry ) : ?>
                                    <li>
                                        <span class="lm-timeline__date"><?php echo esc_html( $this->format_admin_datetime( $entry['date'] ) ); ?></span>
                                        <span class="lm-timeline__message"><?php echo esc_html( $entry['message'] ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ( ! empty( $manual_reports ) ) : ?>
                            <div class="lm-draw-reports">
                                <h3><?php esc_html_e( 'Rapports de tirage récents', 'loterie-manager' ); ?></h3>
                                <ul>
                                    <?php foreach ( $manual_reports as $entry ) :
                                        $download = wp_nonce_url(
                                            add_query_arg(
                                                array(
                                                    'action'    => 'lm_download_draw_report',
                                                    'loterie'   => $loterie_id,
                                                    'report_id' => $entry['id'],
                                                ),
                                                admin_url( 'admin-post.php' )
                                            ),
                                            'lm_download_draw_report_' . $loterie_id
                                        );
                                        ?>
                                        <li>
                                            <strong><?php echo esc_html( $this->format_admin_datetime( intval( $entry['created_at'] ) ) ); ?></strong>
                                            <a href="<?php echo esc_url( $download ); ?>"><?php esc_html_e( 'Télécharger', 'loterie-manager' ); ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if ( $export_context ) :
                        $tickets_all = isset( $export_context['tickets_all'] ) ? $export_context['tickets_all'] : array();
                        $estimate    = 0;
                        foreach ( $tickets_all as $ticket ) {
                            if ( in_array( $ticket['status'], array( 'valid', 'winner', 'alternate' ), true ) ) {
                                $estimate++;
                            }
                        }
                        ?>
                        <div class="lm-modal is-open" role="dialog" aria-modal="true">
                            <div class="lm-modal__dialog">
                                <header class="lm-modal__header">
                                    <h2><?php esc_html_e( 'Exporter participants (Huissier)', 'loterie-manager' ); ?></h2>
                                    <a class="lm-modal__close" href="<?php echo esc_url( remove_query_arg( 'lm_export' ) ); ?>" aria-label="<?php esc_attr_e( 'Fermer', 'loterie-manager' ); ?>">&times;</a>
                                </header>
                                <div class="lm-modal__content">
                                    <p><?php esc_html_e( 'Le fichier contient uniquement les tickets valides et leurs données nécessaires.', 'loterie-manager' ); ?></p>
                                    <p class="lm-modal__meta"><?php printf( esc_html__( 'Estimé : %d lignes.', 'loterie-manager' ), $estimate ); ?></p>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lm-modal__form">
                                        <?php wp_nonce_field( 'lm_export_participants_' . $loterie_id, 'lm_export_nonce' ); ?>
                                        <input type="hidden" name="action" value="lm_export_participants" />
                                        <input type="hidden" name="loterie_id" value="<?php echo esc_attr( $loterie_id ); ?>" />
                                        <label>
                                            <span><?php esc_html_e( 'Période (optionnelle)', 'loterie-manager' ); ?></span>
                                            <div class="lm-modal__range">
                                                <input type="date" name="lm_export_start" />
                                                <span>→</span>
                                                <input type="date" name="lm_export_end" />
                                            </div>
                                        </label>
                                        <p class="lm-modal__hint"><?php esc_html_e( 'Chaque export est journalisé avec date, heure et opérateur.', 'loterie-manager' ); ?></p>
                                        <div class="lm-modal__actions">
                                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Exporter (CSV)', 'loterie-manager' ); ?></button>
                                            <a class="button" href="<?php echo esc_url( remove_query_arg( 'lm_export' ) ); ?>"><?php esc_html_e( 'Annuler', 'loterie-manager' ); ?></a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
            <?php
            echo ob_get_clean();
        }
        /**
         * Renders the global settings screen.
         */
        public function render_admin_settings() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.', 'loterie-manager' ) );
            }

            $settings          = $this->get_settings();
            $statuses          = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
            $selected_statuses = $this->get_order_excluded_statuses();

            echo '<div class="wrap lm-admin-wrap">';
            echo '<h1>' . esc_html__( 'Paramètres des loteries', 'loterie-manager' ) . '</h1>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="lm-settings-form">';
            wp_nonce_field( 'lm_save_settings', 'lm_save_settings_nonce' );
            echo '<input type="hidden" name="action" value="lm_save_settings" />';

            echo '<table class="form-table">';
            echo '<tr><th scope="row"><label for="lm_table_pagination">' . esc_html__( 'Tickets par page', 'loterie-manager' ) . '</label></th>';
            echo '<td><input type="number" id="lm_table_pagination" name="lm_table_pagination" min="5" max="200" value="' . esc_attr( $settings['table_pagination'] ) . '" /></td></tr>';

            echo '<tr><th scope="row">' . esc_html__( 'Réaffectation automatique', 'loterie-manager' ) . '</th><td>';
            echo '<label><input type="checkbox" name="lm_reassignment_enabled" value="1"' . checked( ! empty( $settings['reassignment_enabled'] ), true, false ) . ' /> ' . esc_html__( 'Autoriser la désactivation automatique des tickets lorsque la commande est annulée ou remboursée.', 'loterie-manager' ) . '</label>';
            echo '</td></tr>';

            echo '<tr><th scope="row">' . esc_html__( 'Statuts exclus du tirage', 'loterie-manager' ) . '</th><td>';
            if ( empty( $statuses ) ) {
                echo '<p>' . esc_html__( 'WooCommerce doit être actif pour configurer cette option.', 'loterie-manager' ) . '</p>';
            } else {
                foreach ( $statuses as $key => $label ) {
                    $key_clean = sanitize_key( str_replace( 'wc-', '', $key ) );
                    echo '<label class="lm-status-checkbox"><input type="checkbox" name="lm_excluded_statuses[]" value="' . esc_attr( $key_clean ) . '"' . checked( in_array( $key_clean, $selected_statuses, true ), true, false ) . ' /> ' . esc_html( $label ) . '</label><br />';
                }
            }
            echo '</td></tr>';

            echo '</table>';

            echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Enregistrer les modifications', 'loterie-manager' ) . '</button></p>';
            echo '</form>';
            echo '</div>';
        }

        /**
         * Handles the global reassignment toggle.
         */
        public function handle_toggle_reassignment() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Action non autorisée.', 'loterie-manager' ) );
            }

            if ( empty( $_POST['lm_toggle_reassignment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_toggle_reassignment_nonce'] ) ), 'lm_toggle_reassignment' ) ) {
                wp_die( esc_html__( 'Jeton de sécurité invalide.', 'loterie-manager' ) );
            }

            $settings                         = $this->get_settings();
            $settings['reassignment_enabled'] = isset( $_POST['lm_reassignment'] ) ? 1 : 0;

            $this->save_settings( $settings );

            $redirect = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : admin_url( 'admin.php?page=winshirt-lotteries' );
            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Saves the settings form.
         */
        public function handle_save_settings() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Action non autorisée.', 'loterie-manager' ) );
            }

            if ( empty( $_POST['lm_save_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_save_settings_nonce'] ) ), 'lm_save_settings' ) ) {
                wp_die( esc_html__( 'Jeton de sécurité invalide.', 'loterie-manager' ) );
            }

            $settings = $this->get_settings();

            $settings['table_pagination']     = isset( $_POST['lm_table_pagination'] ) ? max( 5, intval( $_POST['lm_table_pagination'] ) ) : $settings['table_pagination'];
            $settings['reassignment_enabled'] = isset( $_POST['lm_reassignment_enabled'] ) ? 1 : 0;

            $excluded = array();
            if ( isset( $_POST['lm_excluded_statuses'] ) && is_array( $_POST['lm_excluded_statuses'] ) ) {
                foreach ( $_POST['lm_excluded_statuses'] as $status ) {
                    $status = sanitize_key( str_replace( 'wc-', '', (string) $status ) );
                    if ( '' !== $status ) {
                        $excluded[] = $status;
                    }
                }
            }

            $settings['eligibility_rules']['exclude_statuses'] = array_values( array_unique( $excluded ) );

            $this->save_settings( $settings );

            wp_safe_redirect( admin_url( 'admin.php?page=winshirt-lotteries-settings&updated=1' ) );
            exit;
        }

        /**
         * Handles per-loterie reassignment overrides.
         */
        public function handle_loterie_reassignment_toggle() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Action non autorisée.', 'loterie-manager' ) );
            }

            $loterie_id = isset( $_POST['loterie_id'] ) ? absint( $_POST['loterie_id'] ) : 0;
            if ( $loterie_id <= 0 ) {
                wp_die( esc_html__( 'Loterie introuvable.', 'loterie-manager' ) );
            }

            if ( empty( $_POST['lm_toggle_loterie_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_toggle_loterie_nonce'] ) ), 'lm_toggle_loterie_reassignment_' . $loterie_id ) ) {
                wp_die( esc_html__( 'Jeton de sécurité invalide.', 'loterie-manager' ) );
            }

            $mode = isset( $_POST['lm_reassignment_mode'] ) ? sanitize_key( wp_unslash( $_POST['lm_reassignment_mode'] ) ) : 'inherit';
            if ( ! in_array( $mode, array( 'inherit', 'enabled', 'disabled' ), true ) ) {
                $mode = 'inherit';
            }

            if ( 'inherit' === $mode ) {
                delete_post_meta( $loterie_id, self::META_REASSIGNMENT_MODE );
            } else {
                update_post_meta( $loterie_id, self::META_REASSIGNMENT_MODE, $mode );
            }

            $this->add_lottery_log(
                $loterie_id,
                'reassignment_updated',
                sprintf( __( 'Réaffectation définie sur « %s ».', 'loterie-manager' ), $mode )
            );

            $redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url( 'admin.php?page=winshirt-lotteries&loterie=' . $loterie_id );
            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Handles participant export to CSV.
         */
        public function handle_export_participants() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Action non autorisée.', 'loterie-manager' ) );
            }

            $loterie_id = isset( $_POST['loterie_id'] ) ? absint( $_POST['loterie_id'] ) : 0;
            if ( $loterie_id <= 0 ) {
                wp_die( esc_html__( 'Loterie introuvable.', 'loterie-manager' ) );
            }

            if ( empty( $_POST['lm_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_export_nonce'] ) ), 'lm_export_participants_' . $loterie_id ) ) {
                wp_die( esc_html__( 'Jeton de sécurité invalide.', 'loterie-manager' ) );
            }

            $start_raw = isset( $_POST['lm_export_start'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_export_start'] ) ) : '';
            $end_raw   = isset( $_POST['lm_export_end'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_export_end'] ) ) : '';

            $start = $start_raw ? strtotime( $start_raw . ' 00:00:00' ) : 0;
            $end   = $end_raw ? strtotime( $end_raw . ' 23:59:59' ) : 0;

            $stats   = $this->get_lottery_stats( $loterie_id, array( 'include_tickets' => true, 'force_refresh' => true, 'per_page' => -1 ) );
            $tickets = isset( $stats['tickets_all'] ) ? $stats['tickets_all'] : array();
            $tickets = array_filter(
                $tickets,
                static function ( $ticket ) {
                    return in_array( $ticket['status'], array( 'valid', 'winner', 'alternate' ), true );
                }
            );

            if ( $start || $end ) {
                $tickets = array_filter(
                    $tickets,
                    static function ( $ticket ) use ( $start, $end ) {
                        $date = isset( $ticket['order_date'] ) ? intval( $ticket['order_date'] ) : 0;
                        if ( $start && $date < $start ) {
                            return false;
                        }
                        if ( $end && $date > $end ) {
                            return false;
                        }

                        return true;
                    }
                );
            }

            if ( headers_sent() ) {
                wp_die( esc_html__( 'Impossible de générer le fichier : les en-têtes HTTP ont déjà été envoyés.', 'loterie-manager' ) );
            }

            $filename = 'participants-loterie-' . $loterie_id . '-' . gmdate( 'Ymd-His' ) . '.csv';
            nocache_headers();
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=' . $filename );

            $output = fopen( 'php://output', 'w' );

            $header = array(
                'loterie_id',
                'loterie_nom',
                'ticket_numero',
                'ticket_date',
                'ticket_statut',
                'participant_prenom',
                'participant_nom',
                'participant_email',
                'participant_telephone',
                'participant_pays',
                'participant_ville',
                'commande_reference',
                'commande_date',
            );

            fputcsv( $output, $header, ';' );

            foreach ( $tickets as $ticket ) {
                $row = array(
                    $loterie_id,
                    get_the_title( $loterie_id ),
                    $ticket['ticket_number'],
                    $this->format_admin_datetime( $ticket['order_date'] ),
                    $ticket['status_label'],
                    $ticket['customer_first_name'],
                    $ticket['customer_last_name'],
                    $ticket['customer_email'],
                    $ticket['customer_phone'],
                    $ticket['customer_country'],
                    $ticket['customer_city'],
                    $ticket['order_number'],
                    $this->format_admin_datetime( $ticket['order_date'] ),
                );

                fputcsv( $output, $row, ';' );
            }

            fclose( $output );

            $this->add_lottery_log(
                $loterie_id,
                'export',
                sprintf( __( 'Export officiel généré (%1$d tickets) – Période : %2$s → %3$s.', 'loterie-manager' ), count( $tickets ), $start ? wp_date( get_option( 'date_format' ), $start ) : __( 'Début', 'loterie-manager' ), $end ? wp_date( get_option( 'date_format' ), $end ) : __( 'Aujourd’hui', 'loterie-manager' ) )
            );

            exit;
        }

        /**
         * Builds the manual draw pool with optional exclusions.
         *
         * @param int   $loterie_id Loterie ID.
         * @param array $args       Arguments.
         *
         * @return array<string, mixed>
         */
        private function prepare_manual_draw_pool( $loterie_id, $args = array() ) {
            $defaults = array(
                'exclude_cancelled' => true,
            );

            $args = wp_parse_args( $args, $defaults );

            $stats = $this->get_lottery_stats(
                $loterie_id,
                array(
                    'include_tickets' => true,
                    'force_refresh'   => true,
                    'per_page'        => -1,
                )
            );

            $tickets = isset( $stats['tickets_all'] ) ? $stats['tickets_all'] : array();

            $invalid_status = 0;
            $excluded_orders = 0;

            $eligible = array();
            foreach ( $tickets as $ticket ) {
                if ( 'valid' !== $ticket['status'] ) {
                    $invalid_status++;
                    continue;
                }

                if ( $args['exclude_cancelled'] ) {
                    $order_status = isset( $ticket['order_status'] ) ? sanitize_key( $ticket['order_status'] ) : '';
                    if ( in_array( $order_status, array( 'cancelled', 'refunded', 'failed', 'pending', 'on-hold' ), true ) ) {
                        $excluded_orders++;
                        continue;
                    }
                }

                $eligible[] = $ticket;
            }

            return array(
                'tickets'          => array_values( $eligible ),
                'total_source'     => count( $tickets ),
                'excluded_status'  => $invalid_status,
                'excluded_orders'  => $excluded_orders,
            );
        }

        /**
         * Processes manual draw requests.
         */
        public function handle_manual_draw() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Action non autorisée.', 'loterie-manager' ) );
            }

            $loterie_id = isset( $_POST['loterie_id'] ) ? absint( $_POST['loterie_id'] ) : 0;
            if ( $loterie_id <= 0 ) {
                wp_die( esc_html__( 'Loterie introuvable.', 'loterie-manager' ) );
            }

            if ( empty( $_POST['lm_manual_draw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_manual_draw_nonce'] ) ), 'lm_manual_draw_' . $loterie_id ) ) {
                wp_die( esc_html__( 'Jeton de sécurité invalide.', 'loterie-manager' ) );
            }

            if ( empty( $_POST['lm_draw_confirm'] ) ) {
                wp_die( esc_html__( 'Vous devez confirmer la vérification des participants.', 'loterie-manager' ) );
            }

            $public_seed      = isset( $_POST['lm_public_seed'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_public_seed'] ) ) : '';
            $alternate_count  = isset( $_POST['lm_alternate_count'] ) ? max( 0, min( 3, absint( $_POST['lm_alternate_count'] ) ) ) : 0;
            $exclude_cancelled = ! empty( $_POST['lm_draw_exclude_cancelled'] );

            $pool_data = $this->prepare_manual_draw_pool(
                $loterie_id,
                array(
                    'exclude_cancelled' => $exclude_cancelled,
                )
            );

            $pool = $pool_data['tickets'];

            if ( empty( $pool ) ) {
                wp_die( esc_html__( 'Aucun ticket valide disponible pour le tirage.', 'loterie-manager' ) );
            }

            $draw_count = min( count( $pool ), $alternate_count + 1 );
            $ticket_source = implode( '|', wp_list_pluck( $pool, 'reference' ) );
            $selected_indices = array();

            for ( $i = 0; $i < $draw_count; $i++ ) {
                $hash    = hash( 'sha256', $public_seed . '|' . $ticket_source . '|' . $i );
                $number  = hexdec( substr( $hash, 0, 12 ) );
                $index   = $number % count( $pool );
                $attempt = 0;

                while ( in_array( $index, $selected_indices, true ) && $attempt < 25 ) {
                    $hash   = hash( 'sha256', $hash . '|' . $attempt );
                    $number = hexdec( substr( $hash, 0, 12 ) );
                    $index  = $number % count( $pool );
                    $attempt++;
                }

                $selected_indices[] = $index;
            }

            $winners = array();
            foreach ( $selected_indices as $position => $pool_index ) {
                $ticket = $pool[ $pool_index ];
                $role   = 0 === $position ? 'winner' : 'alternate';
                $winners[] = array(
                    'signature'      => $ticket['reference'],
                    'ticket_number'  => $ticket['ticket_number'],
                    'order_id'       => $ticket['order_id'],
                    'order_number'   => $ticket['order_number'],
                    'participant'    => $ticket['customer_name'],
                    'email'          => $ticket['customer_email'],
                    'role'           => $role,
                    'position'       => $position,
                );
            }

            $current_user = wp_get_current_user();
            $report_id    = uniqid( 'lm_draw_', true );
            $report       = array(
                'id'           => $report_id,
                'loterie_id'   => $loterie_id,
                'type'         => 'manual',
                'created_at'   => current_time( 'timestamp' ),
                'seed'         => $public_seed,
                'ticket_count' => count( $pool ),
                'operator_id'  => $current_user ? $current_user->ID : 0,
                'operator'     => $current_user ? $current_user->display_name : '',
                'ip'           => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'winners'      => $winners,
                'options'      => array(
                    'exclude_cancelled' => (bool) $exclude_cancelled,
                ),
            );

            $checksum_source = $report;
            $report['checksum'] = hash( 'sha256', wp_json_encode( $checksum_source ) );

            $this->append_manual_draw_report( $loterie_id, $report );

            $winner_label = $winners[0];
            $this->add_lottery_log(
                $loterie_id,
                'manual_draw',
                sprintf( __( 'Tirage manuel : ticket gagnant %1$s (commande %2$s).', 'loterie-manager' ), $winner_label['ticket_number'], $winner_label['order_number'] )
            );

            $redirect = add_query_arg(
                array(
                    'loterie'         => $loterie_id,
                    'lm_draw_success' => 1,
                    'lm_report'       => rawurlencode( $report_id ),
                ),
                admin_url( 'admin.php?page=winshirt-lotteries' )
            );

            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Outputs a manual draw report for download.
         */
        public function handle_download_draw_report() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Action non autorisée.', 'loterie-manager' ) );
            }

            $loterie_id = isset( $_GET['loterie'] ) ? absint( $_GET['loterie'] ) : 0;
            $report_id  = isset( $_GET['report_id'] ) ? sanitize_text_field( wp_unslash( $_GET['report_id'] ) ) : '';

            if ( $loterie_id <= 0 || '' === $report_id ) {
                wp_die( esc_html__( 'Rapport introuvable.', 'loterie-manager' ) );
            }

            if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'lm_download_draw_report_' . $loterie_id ) ) {
                wp_die( esc_html__( 'Jeton de sécurité invalide.', 'loterie-manager' ) );
            }

            $reports = $this->get_manual_draw_reports( $loterie_id );
            $report  = null;
            foreach ( $reports as $entry ) {
                if ( isset( $entry['id'] ) && $entry['id'] === $report_id ) {
                    $report = $entry;
                    break;
                }
            }

            if ( ! $report ) {
                wp_die( esc_html__( 'Rapport introuvable.', 'loterie-manager' ) );
            }

            $filename = 'rapport-tirage-' . $loterie_id . '-' . gmdate( 'Ymd-His', intval( $report['created_at'] ) ) . '.json';
            nocache_headers();
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=' . $filename );

            echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
            exit;
        }

        /**
         * Allows administrators to manually reset cached lottery counters.
         */
        public function handle_reset_lottery_counters() {
            if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Action non autorisée.', 'loterie-manager' ) );
            }

            $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
            if ( 'POST' !== $request_method ) {
                wp_die( esc_html__( 'Méthode de requête invalide.', 'loterie-manager' ) );
            }

            $nonce = isset( $_POST['lm_reset_lottery_counters_nonce'] )
                ? sanitize_text_field( wp_unslash( $_POST['lm_reset_lottery_counters_nonce'] ) )
                : '';

            $redirect = isset( $_POST['_wp_http_referer'] ) ? wp_unslash( $_POST['_wp_http_referer'] ) : '';
            $redirect = $redirect ? wp_validate_redirect( $redirect, admin_url( 'admin.php?page=winshirt-lotteries' ) ) : admin_url( 'admin.php?page=winshirt-lotteries' );

            if ( ! wp_verify_nonce( $nonce, 'lm_reset_lottery_counters' ) ) {
                $redirect_url = add_query_arg( 'lm_reset_status', 'nonce', $redirect );
                wp_safe_redirect( $redirect_url );
                exit;
            }

            $loterie_id  = isset( $_POST['loterie_id'] ) ? absint( $_POST['loterie_id'] ) : 0;
            $force_reset = isset( $_POST['lm_force_reset'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['lm_force_reset'] ) );
            $targets     = array();

            if ( $loterie_id > 0 ) {
                $post = get_post( $loterie_id );
                if ( $post && 'post' === $post->post_type ) {
                    $targets = array( $post->ID );
                }
            } else {
                $posts   = get_posts(
                    array(
                        'post_type'      => 'post',
                        'post_status'    => array( 'publish', 'draft', 'pending' ),
                        'fields'         => 'ids',
                        'posts_per_page' => -1,
                    )
                );
                $targets = array_map( 'intval', $posts );
            }

            if ( empty( $targets ) ) {
                $redirect_args = array( 'lm_reset_status' => 'empty' );
                if ( $loterie_id > 0 ) {
                    $redirect_args['lm_reset_target'] = $loterie_id;
                }
                $redirect_url = add_query_arg( $redirect_args, $redirect );
                wp_safe_redirect( $redirect_url );
                exit;
            }

            $blocked_id = 0;
            if ( ! $force_reset ) {
                foreach ( $targets as $target_id ) {
                    $stats = $this->get_lottery_stats(
                        $target_id,
                        array(
                            'force_refresh' => true,
                        )
                    );

                    if ( isset( $stats['valid_tickets'] ) && $stats['valid_tickets'] > 0 ) {
                        $blocked_id = $target_id;
                        break;
                    }
                }

                if ( $blocked_id > 0 ) {
                    $redirect_url = add_query_arg(
                        array(
                            'lm_reset_status' => 'active',
                            'lm_reset_target' => $blocked_id,
                        ),
                        $redirect
                    );
                    wp_safe_redirect( $redirect_url );
                    exit;
                }
            }

            foreach ( $targets as $target_id ) {
                update_post_meta( $target_id, self::META_TICKETS_SOLD, 0 );
                unset( $this->lottery_stats_cache[ $target_id ] );
                $this->add_lottery_log(
                    $target_id,
                    $force_reset ? 'manual_reset_force' : 'manual_reset',
                    $force_reset
                        ? __( 'Les compteurs ont été réinitialisés manuellement (forcé).', 'loterie-manager' )
                        : __( 'Les compteurs ont été réinitialisés manuellement.', 'loterie-manager' )
                );
            }

            $this->most_advanced_loterie_id = null;

            $redirect_args = array( 'lm_reset_status' => $force_reset ? 'forced' : 'success' );
            if ( 1 === count( $targets ) ) {
                $redirect_args['lm_reset_target'] = $targets[0];
            }

            $redirect_url = add_query_arg( $redirect_args, $redirect );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        /**
         * Reacts to WooCommerce order status changes.
         *
         * @param int      $order_id    Order ID.
         * @param string   $old_status  Previous status.
         * @param string   $new_status  New status.
         * @param WC_Order $order       Order object.
         */
        public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
            if ( ! $order instanceof WC_Order ) {
                $order = wc_get_order( $order_id );
            }

            if ( ! $order ) {
                return;
            }

            $loterie_counts = array();

            foreach ( $order->get_items() as $item ) {
                $distribution = $this->get_item_ticket_distribution( $item );

                foreach ( $distribution as $loterie_id ) {
                    $loterie_id = intval( $loterie_id );
                    if ( $loterie_id <= 0 ) {
                        continue;
                    }

                    if ( ! isset( $loterie_counts[ $loterie_id ] ) ) {
                        $loterie_counts[ $loterie_id ] = 0;
                    }

                    $loterie_counts[ $loterie_id ]++;
                }
            }

            if ( empty( $loterie_counts ) ) {
                return;
            }

            $this->refresh_loterie_counters( array_keys( $loterie_counts ) );

            $excluded = $this->get_order_excluded_statuses();
            $normalized_new = sanitize_key( str_replace( 'wc-', '', $new_status ) );

            if ( in_array( $normalized_new, $excluded, true ) ) {
                foreach ( $loterie_counts as $loterie_id => $count ) {
                    if ( $this->is_reassignment_enabled_for_loterie( $loterie_id ) ) {
                        $this->add_lottery_log(
                            $loterie_id,
                            'tickets_invalidated',
                            sprintf( __( '%1$d ticket(s) invalidé(s) après le passage de la commande #%2$s au statut %3$s.', 'loterie-manager' ), $count, $order->get_order_number(), wc_get_order_status_name( $new_status ) )
                        );
                    }
                }
            }
        }

        /**
         * Retrieves available loteries as choices.
         *
         * @return array<int, string>
         */
        private function get_loterie_choices() {
            $posts = get_posts(
                array(
                    'post_type'      => 'post',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                )
            );

            $choices = array();
            foreach ( $posts as $post ) {
                $choices[ $post->ID ] = $post->post_title;
            }

            return $choices;
        }

        /**
         * Builds a summary of tickets for a given customer.
         *
         * @param int $user_id User ID.
         *
         * @return array
         */
        private function get_customer_ticket_summary( $user_id ) {
            if ( ! function_exists( 'wc_get_orders' ) ) {
                return array();
            }

            $orders = wc_get_orders(
                array(
                    'customer_id' => $user_id,
                    'status'      => 'any',
                    'limit'       => -1,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                )
            );

            if ( empty( $orders ) ) {
                return array();
            }

            $summary               = array();
            $lottery_context_cache  = array();
            $draw_context_cache     = array();

            foreach ( $orders as $order ) {
                $order_id    = $order->get_id();
                $order_date  = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
                $order_state = $order->get_status();

                foreach ( $order->get_items() as $item_id => $item ) {
                    $distribution = $this->get_item_ticket_distribution( $item );
                    if ( empty( $distribution ) ) {
                        continue;
                    }

                    $counts_for_item = array_count_values( $distribution );

                    foreach ( $distribution as $index => $loterie_id ) {
                        $loterie_id = intval( $loterie_id );
                        $reference  = sprintf( '%1$d:%2$d:%3$d', $order_id, $item_id, $index );

                        if ( $loterie_id > 0 && ! isset( $lottery_context_cache[ $loterie_id ] ) ) {
                            $lottery_context_cache[ $loterie_id ] = $this->get_lottery_stats( $loterie_id );
                            $draw_context_cache[ $loterie_id ]    = $this->map_draw_roles( $loterie_id );
                        }

                        $lottery_stats = $loterie_id > 0 ? ( $lottery_context_cache[ $loterie_id ] ?? array() ) : array();
                        $draw_map      = $loterie_id > 0 ? ( $draw_context_cache[ $loterie_id ] ?? array() ) : array();

                        $status_info = $this->get_ticket_status_from_order( $order, $loterie_id );
                        $draw_state  = isset( $draw_map[ $reference ] ) ? $draw_map[ $reference ] : array();

                        $status       = $status_info['status'];
                        $status_label = $status_info['label'];
                        $status_note  = $status_info['note'];
                        $can_reassign = $status_info['reassignable'];

                        if ( ! empty( $draw_state ) ) {
                            $status       = $draw_state['status'];
                            $status_label = $draw_state['label'];
                            $status_note  = $draw_state['note'];
                            if ( ! empty( $draw_state['lock_reassignment'] ) ) {
                                $can_reassign = false;
                            }
                        }

                        $summary[ $reference ] = array(
                            'reference'            => $reference,
                            'ticket_number'        => $this->format_ticket_number( $order, $item_id, $index ),
                            'product_name'         => $item->get_name(),
                            'loterie_id'           => $loterie_id,
                            'title'                => $loterie_id > 0 ? get_the_title( $loterie_id ) : '',
                            'end_date'             => $loterie_id > 0 ? get_post_meta( $loterie_id, self::META_END_DATE, true ) : '',
                            'order_id'             => $order_id,
                            'order_number'         => $order->get_order_number(),
                            'order_status'         => $order_state,
                            'issued_at'            => $order_date,
                            'status'               => $status,
                            'status_label'         => $status_label,
                            'status_note'          => $status_note,
                            'can_reassign'         => $can_reassign,
                            'ticket_index'         => $index,
                            'item_id'              => $item_id,
                            'lottery_status_label' => $lottery_stats['status_label'] ?? '',
                            'lottery_status_class' => $lottery_stats['status_class'] ?? '',
                            'reassignment_enabled' => $this->is_reassignment_enabled_for_loterie( $loterie_id ),
                            'draw_role'            => $draw_state['role'] ?? '',
                            'draw_position'        => $draw_state['position'] ?? 0,
                            'reason_code'          => $status_info['reason_code'],
                            'amount_share'         => ( $loterie_id > 0 && ! empty( $counts_for_item[ $loterie_id ] ) ) ? ( $item->get_total() + $item->get_total_tax() ) / max( 1, $counts_for_item[ $loterie_id ] ) : 0,
                        );
                    }
                }
            }

            return $summary;
        }

        /**
         * Retrieves the per-ticket distribution for an order item.
         *
         * @param WC_Order_Item_Product $item Order item instance.
         *
         * @return array<int, int>
         */
        private function get_item_ticket_distribution( $item ) {
            if ( ! $item ) {
                return array();
            }

            $ticket_allocation = intval( $item->get_meta( 'lm_ticket_allocation', true ) );
            if ( $ticket_allocation <= 0 ) {
                $ticket_allocation = 1;
            }

            $quantity      = max( 1, $item->get_quantity() );
            $tickets_total = max( 1, $ticket_allocation * $quantity );

            $distribution = (array) $item->get_meta( 'lm_ticket_distribution', true );
            $distribution = array_map( 'intval', $distribution );

            if ( count( $distribution ) !== $tickets_total ) {
                $selection = (array) $item->get_meta( 'lm_lottery_selection', true );
                $selection = array_map( 'intval', $selection );
                $distribution = $this->normalize_ticket_distribution( $selection, $tickets_total );
                $this->set_item_ticket_distribution( $item, $distribution );
            }

            return $distribution;
        }

        /**
         * Persists the ticket distribution for an order item.
         *
         * @param WC_Order_Item_Product $item         Order item instance.
         * @param array<int, int>       $distribution Distribution data.
         */
        private function set_item_ticket_distribution( $item, $distribution ) {
            if ( ! $item ) {
                return;
            }

            $distribution = array_map( 'intval', (array) $distribution );
            $item->update_meta_data( 'lm_ticket_distribution', array_values( $distribution ) );
            $item->save();
        }

        /**
         * Normalizes selection data to a per-ticket distribution.
         *
         * @param array<int, int> $selection     Selected loterie IDs.
         * @param int             $tickets_total Number of tickets to distribute.
         *
         * @return array<int, int>
         */
        private function normalize_ticket_distribution( $selection, $tickets_total ) {
            $tickets_total = intval( $tickets_total );

            if ( $tickets_total <= 0 ) {
                return array();
            }

            $selection = array_values( array_filter( array_map( 'intval', (array) $selection ), static function ( $value ) {
                return $value >= 0;
            } ) );

            if ( empty( $selection ) ) {
                return array_fill( 0, $tickets_total, 0 );
            }

            if ( 1 === count( $selection ) ) {
                return array_fill( 0, $tickets_total, $selection[0] );
            }

            $distribution = array();
            $index        = 0;
            $count        = count( $selection );

            while ( count( $distribution ) < $tickets_total ) {
                $distribution[] = $selection[ $index % $count ];
                $index++;
            }

            return array_slice( $distribution, 0, $tickets_total );
        }
    }

    Loterie_Manager::instance();
}

register_activation_hook( __FILE__, static function () {
    Loterie_Manager::instance()->register_account_endpoint();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
    flush_rewrite_rules();
} );
