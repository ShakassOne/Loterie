<?php
/*
Plugin Name: WinShirt Loterie Manager
Plugin URI: https://github.com/ShakassOne/loterie-winshirt
Description: Gestion des loteries pour WooCommerce.
Version: 1.2.0
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
         * Meta key storing number of tickets sold.
         */
        const META_TICKETS_SOLD = '_lm_tickets_sold';

        /**
         * Meta key storing ticket allocation per product purchase.
         */
        const META_PRODUCT_TICKET_ALLOCATION = '_lm_product_ticket_allocation';

        /**
         * Meta key storing loterie targets for a product.
         */
        const META_PRODUCT_TARGET_LOTERIES = '_lm_product_target_lotteries';

        /**
         * Singleton instance.
         *
         * @var Loterie_Manager
         */
        private static $instance = null;

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
            add_action( 'woocommerce_order_status_completed', array( $this, 'sync_order_ticket_counts' ) );
            add_action( 'woocommerce_order_status_processing', array( $this, 'sync_order_ticket_counts' ) );

            // Loterie meta boxes on posts.
            add_action( 'add_meta_boxes', array( $this, 'register_loterie_meta_box' ) );
            add_action( 'save_post_post', array( $this, 'save_loterie_meta' ), 10, 2 );

            // Frontend overlays & shortcodes.
            add_filter( 'post_thumbnail_html', array( $this, 'inject_loterie_overlay' ), 10, 5 );
            add_shortcode( 'lm_loterie', array( $this, 'render_loterie_shortcode' ) );

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
                '1.0.0'
            );

            wp_enqueue_script(
                'loterie-manager-frontend',
                plugins_url( 'assets/js/frontend.js', __FILE__ ),
                array( 'jquery' ),
                '1.0.0',
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
                    ),
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

            $selection_raw = isset( $_POST['lm_lottery_selection'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_lottery_selection'] ) ) : '';
            $selection     = array_filter( array_map( 'absint', explode( ',', $selection_raw ) ) );

            if ( empty( $selection ) ) {
                if ( function_exists( 'wc_add_notice' ) ) {
                    wc_add_notice( __( 'Veuillez sélectionner au moins une loterie.', 'loterie-manager' ), 'error' );
                }
                return false;
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
            $selection_raw = isset( $_POST['lm_lottery_selection'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_lottery_selection'] ) ) : '';
            $selection     = array_filter( array_map( 'absint', explode( ',', $selection_raw ) ) );

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

            if ( 'yes' === $order->get_meta( '_lm_ticket_counts_synced', true ) ) {
                return;
            }

            $counts_updated = false;

            foreach ( $order->get_items() as $item ) {
                $distribution = $this->get_item_ticket_distribution( $item );

                if ( empty( $distribution ) ) {
                    continue;
                }

                $counts = array();
                foreach ( $distribution as $loterie_id ) {
                    $loterie_id = intval( $loterie_id );
                    if ( $loterie_id <= 0 ) {
                        continue;
                    }

                    if ( ! isset( $counts[ $loterie_id ] ) ) {
                        $counts[ $loterie_id ] = 0;
                    }

                    $counts[ $loterie_id ]++;
                }

                foreach ( $counts as $loterie_id => $count ) {
                    $current = intval( get_post_meta( $loterie_id, self::META_TICKETS_SOLD, true ) );
                    update_post_meta( $loterie_id, self::META_TICKETS_SOLD, $current + $count );
                    $counts_updated = true;
                }
            }

            if ( $counts_updated ) {
                $order->update_meta_data( '_lm_ticket_counts_synced', 'yes' );
                $order->save();
            }
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
         * Renders the loterie meta box.
         *
         * @param WP_Post $post Current post.
         */
        public function render_loterie_meta_box( $post ) {
            wp_nonce_field( 'lm_save_loterie_meta', 'lm_loterie_nonce' );

            $capacity = intval( get_post_meta( $post->ID, self::META_TICKET_CAPACITY, true ) );
            $lot      = get_post_meta( $post->ID, self::META_LOT_DESCRIPTION, true );
            $end_date = get_post_meta( $post->ID, self::META_END_DATE, true );

            ?>
            <p>
                <label for="lm_ticket_capacity"><strong><?php esc_html_e( 'Capacité totale de tickets', 'loterie-manager' ); ?></strong></label><br />
                <input type="number" id="lm_ticket_capacity" name="lm_ticket_capacity" min="0" step="1" value="<?php echo esc_attr( $capacity ); ?>" />
            </p>
            <p>
                <label for="lm_lot_description"><strong><?php esc_html_e( 'Description du lot', 'loterie-manager' ); ?></strong></label><br />
                <textarea id="lm_lot_description" name="lm_lot_description" rows="3" style="width:100%;"><?php echo esc_textarea( $lot ); ?></textarea>
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

            $lot = isset( $_POST['lm_lot_description'] ) ? wp_kses_post( wp_unslash( $_POST['lm_lot_description'] ) ) : '';
            update_post_meta( $post_id, self::META_LOT_DESCRIPTION, $lot );

            $end_date = isset( $_POST['lm_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_end_date'] ) ) : '';
            update_post_meta( $post_id, self::META_END_DATE, $end_date );
        }

        /**
         * Merges the loterie overlay into the post thumbnail HTML when needed.
         *
         * @param string $html               Thumbnail HTML.
         * @param int    $post_id            Post ID.
         * @param int    $post_thumbnail_id  Attachment ID.
         * @param string $size               Image size.
         * @param mixed  $attr               Attributes.
         *
         * @return string
         */
        public function inject_loterie_overlay( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
            if ( is_admin() || is_singular() || ! in_the_loop() || ! is_main_query() ) {
                return $html;
            }

            if ( empty( $html ) ) {
                return $html;
            }

            $post_id = $post_id ? $post_id : get_the_ID();
            if ( ! $post_id ) {
                return $html;
            }

            $overlay = $this->get_loterie_overlay_markup( $post_id );
            if ( '' === $overlay ) {
                return $html;
            }

            return sprintf(
                '<div class="lm-loterie-thumbnail">%1$s%2$s</div>',
                $html,
                $overlay
            );
        }

        /**
         * Generates the HTML markup for the loterie overlay.
         *
         * @param int $post_id Post ID.
         *
         * @return string
         */
        private function get_loterie_overlay_markup( $post_id ) {
            $capacity = intval( get_post_meta( $post_id, self::META_TICKET_CAPACITY, true ) );
            if ( $capacity <= 0 ) {
                return '';
            }

            $sold     = intval( get_post_meta( $post_id, self::META_TICKETS_SOLD, true ) );
            $progress = $capacity > 0 ? min( 100, round( ( $sold / max( $capacity, 1 ) ) * 100, 2 ) ) : 0;
            $lot      = get_post_meta( $post_id, self::META_LOT_DESCRIPTION, true );
            $end_date = get_post_meta( $post_id, self::META_END_DATE, true );

            ob_start();
            ?>
            <div class="lm-loterie-overlay" aria-live="polite">
                <div class="lm-overlay-header">
                    <span class="lm-overlay-title"><?php esc_html_e( 'Progression de la loterie', 'loterie-manager' ); ?></span>
                    <?php if ( ! empty( $end_date ) ) : ?>
                        <span class="lm-overlay-date"><?php echo esc_html( sprintf( __( 'Fin le %s', 'loterie-manager' ), date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) ) ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="lm-overlay-progress">
                    <div class="lm-overlay-bar" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
                </div>
                <div class="lm-overlay-stats">
                    <span class="lm-overlay-count"><?php echo esc_html( sprintf( __( '%1$d tickets vendus sur %2$d', 'loterie-manager' ), $sold, $capacity ) ); ?></span>
                    <?php if ( ! empty( $lot ) ) : ?>
                        <span class="lm-overlay-prize"><?php echo esc_html( $lot ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php

            return (string) ob_get_clean();
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
                    'id' => get_the_ID(),
                ),
                $atts
            );

            $post_id = absint( $atts['id'] );
            if ( ! $post_id ) {
                return '';
            }

            $capacity = intval( get_post_meta( $post_id, self::META_TICKET_CAPACITY, true ) );
            $sold     = intval( get_post_meta( $post_id, self::META_TICKETS_SOLD, true ) );
            $lot      = get_post_meta( $post_id, self::META_LOT_DESCRIPTION, true );
            $end_date = get_post_meta( $post_id, self::META_END_DATE, true );

            ob_start();
            ?>
            <div class="lm-loterie-summary" data-loterie-id="<?php echo esc_attr( $post_id ); ?>">
                <h3 class="lm-loterie-summary__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
                <?php if ( ! empty( $lot ) ) : ?>
                    <p class="lm-loterie-summary__prize"><?php echo esc_html( $lot ); ?></p>
                <?php endif; ?>
                <?php if ( ! empty( $end_date ) ) : ?>
                    <p class="lm-loterie-summary__end">
                        <?php echo esc_html( sprintf( __( 'Clôture le %s', 'loterie-manager' ), date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) ) ); ?>
                    </p>
                <?php endif; ?>
                <div class="lm-loterie-summary__progress">
                    <div class="lm-loterie-summary__bar" style="width: <?php echo esc_attr( $capacity > 0 ? min( 100, round( ( $sold / max( $capacity, 1 ) ) * 100, 2 ) ) : 0 ); ?>%;"></div>
                </div>
                <p class="lm-loterie-summary__stats"><?php echo esc_html( sprintf( __( '%1$d tickets vendus sur %2$d', 'loterie-manager' ), $sold, $capacity ) ); ?></p>
            </div>
            <?php

            return (string) ob_get_clean();
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

            $tickets = $this->get_customer_ticket_summary( get_current_user_id() );

            if ( empty( $tickets ) ) {
                echo '<p>' . esc_html__( 'Aucun ticket enregistré pour le moment.', 'loterie-manager' ) . '</p>';
                return;
            }

            echo '<form method="post" class="lm-ticket-reassignment">';
            wp_nonce_field( 'lm_reassign_ticket', 'lm_reassign_ticket_nonce' );
            echo '<table class="lm-ticket-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Ticket', 'loterie-manager' ) . '</th>';
            echo '<th>' . esc_html__( 'Loterie actuelle', 'loterie-manager' ) . '</th>';
            echo '<th>' . esc_html__( 'Réaffecter vers', 'loterie-manager' ) . '</th>';
            echo '<th>' . esc_html__( 'Action', 'loterie-manager' ) . '</th>';
            echo '</tr></thead><tbody>';

            $available_loteries = $this->get_loterie_choices();
            $counter            = 1;

            foreach ( $tickets as $reference => $ticket ) {
                echo '<tr>';
                echo '<td>';
                echo '<strong>' . esc_html( sprintf( __( 'Ticket #%d', 'loterie-manager' ), $counter ) ) . '</strong>';
                if ( ! empty( $ticket['product_name'] ) ) {
                    echo '<br /><small>' . esc_html( $ticket['product_name'] ) . '</small>';
                }
                echo '</td>';

                echo '<td>';
                if ( $ticket['loterie_id'] > 0 ) {
                    echo esc_html( $ticket['title'] );
                    if ( ! empty( $ticket['end_date'] ) ) {
                        echo '<br /><small>' . esc_html( sprintf( __( 'Fin le %s', 'loterie-manager' ), date_i18n( get_option( 'date_format' ), strtotime( $ticket['end_date'] ) ) ) ) . '</small>';
                    }
                } else {
                    esc_html_e( 'Non attribué', 'loterie-manager' );
                }
                echo '</td>';

                echo '<td>';
                echo '<select name="lm_reassign[' . esc_attr( $reference ) . ']">';
                echo '<option value="">' . esc_html__( 'Sélectionnez une loterie', 'loterie-manager' ) . '</option>';
                foreach ( $available_loteries as $choice_id => $label ) {
                    printf( '<option value="%1$d">%2$s</option>', intval( $choice_id ), esc_html( $label ) );
                }
                echo '</select>';
                echo '</td>';

                echo '<td>';
                echo '<button type="submit" class="button">' . esc_html__( 'Réaffecter', 'loterie-manager' ) . '</button>';
                echo '<input type="hidden" name="lm_ticket_reference[]" value="' . esc_attr( $reference ) . '" />';
                echo '</td>';

                echo '</tr>';
                $counter++;
            }

            echo '</tbody></table>';
            echo '</form>';
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

            if ( ! isset( $_POST['lm_reassign_ticket_nonce'], $_POST['lm_ticket_reference'], $_POST['lm_reassign'] ) ) {
                return;
            }

            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lm_reassign_ticket_nonce'] ) ), 'lm_reassign_ticket' ) ) {
                return;
            }

            $user_id = get_current_user_id();
            $summary    = $this->get_customer_ticket_summary( $user_id );
            $references = array_map( 'sanitize_text_field', (array) $_POST['lm_ticket_reference'] );

            $targets = array();
            foreach ( (array) $_POST['lm_reassign'] as $reference => $value ) {
                $targets[ sanitize_text_field( $reference ) ] = intval( $value );
            }

            foreach ( $references as $reference ) {
                if ( empty( $reference ) || empty( $summary[ $reference ] ) ) {
                    continue;
                }

                $ticket         = $summary[ $reference ];
                $new_loterie_id = isset( $targets[ $reference ] ) ? intval( $targets[ $reference ] ) : 0;
                if ( $new_loterie_id <= 0 || $new_loterie_id === $ticket['loterie_id'] ) {
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

                if ( $original_id > 0 ) {
                    $current_original = intval( get_post_meta( $original_id, self::META_TICKETS_SOLD, true ) );
                    update_post_meta( $original_id, self::META_TICKETS_SOLD, max( 0, $current_original - 1 ) );
                }

                if ( $new_loterie_id > 0 ) {
                    $current_new = intval( get_post_meta( $new_loterie_id, self::META_TICKETS_SOLD, true ) );
                    update_post_meta( $new_loterie_id, self::META_TICKETS_SOLD, $current_new + 1 );
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
                    'status'      => array( 'completed', 'processing' ),
                    'limit'       => -1,
                )
            );

            $summary = array();

            foreach ( $orders as $order ) {
                foreach ( $order->get_items() as $item_id => $item ) {
                    $distribution = $this->get_item_ticket_distribution( $item );
                    if ( empty( $distribution ) ) {
                        continue;
                    }

                    foreach ( $distribution as $index => $loterie_id ) {
                        $loterie_id = intval( $loterie_id );
                        $reference  = sprintf( '%1$d:%2$d:%3$d', $order->get_id(), $item_id, $index );

                        $summary[ $reference ] = array(
                            'loterie_id'   => $loterie_id,
                            'title'        => $loterie_id > 0 ? get_the_title( $loterie_id ) : '',
                            'end_date'     => $loterie_id > 0 ? get_post_meta( $loterie_id, self::META_END_DATE, true ) : '',
                            'order_id'     => $order->get_id(),
                            'item_id'      => $item_id,
                            'ticket_index' => $index,
                            'product_name' => $item->get_name(),
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
