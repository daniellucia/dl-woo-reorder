<?php

namespace DL\WooReorder;

defined('ABSPATH') || exit;

class Plugin
{

    public function init(): void
    {
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'add_reorder_button'], 10, 2);
        add_action('init', [$this, 'process_reorder']);
    }

    /**
     * Añadimos botón para poder a volver el pedido
     * @param mixed $actions
     * @param mixed $order
     * @author Daniel Lucia
     */
    public function add_reorder_button($actions, $order)
    {
        if (!$this->can_reorder($order)) {
            return $actions;
        }

        $actions['reorder'] = [
            'url'  => wp_nonce_url(
                add_query_arg([
                    'dl_reorder' => $order->get_id(),
                ]),
                'dl_reorder_action'
            ),
            'name' => __('Reorder', 'dl-woo-reorder'),
        ];

        return $actions;
    }

    /**
     * Validamos si el pedido puede ser vuelto a comprar
     * @param mixed $order
     * @return bool
     * @author Daniel Lucia
     */
    private function can_reorder($order): bool
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }

        if (!$order->get_items()) {
            return false;
        }

        $allowed_statuses = apply_filters('dl_woo_reorder_allowed_statuses', [
            'completed',
            'processing',
            'on-hold'
        ]);

        if (!in_array($order->get_status(), $allowed_statuses, true)) {
            return false;
        }

        return $this->validate_order_access($order);
    }

    /**
     * Validamos que el usuario pueda volver a comprar el pedido
     * @param mixed $order
     * @return bool
     * @author Daniel Lucia
     */
    private function validate_order_access($order): bool
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }

        // Si el usuario no está logueado, no puede volver a comprar
        //TODO: Que sea configurable
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user_id = get_current_user_id();
        $order_user_id = $order->get_user_id();

        // Verificar que el pedido pertenezca al mismo usuario
        return ($order_user_id === $current_user_id);
    }

    /**
     * Procesamos la recompra
     * @return void
     * @author Daniel Lucia
     */
    public function process_reorder()
    {
        if (isset($_GET['dl_reorder']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'dl_reorder_action')) {
            $order_id = absint($_GET['dl_reorder']);
            $order    = wc_get_order($order_id);

            if ($order && $order->get_items()) {
                WC()->cart->empty_cart();

                foreach ($order->get_items() as $item) {
                    $product_id = 0;
                    $variation_id = 0;
                    $quantity = 0;
                    $variation = [];

                    if ($product_id <= 0 || $quantity <= 0) {
                        continue;
                    }
                    
                    $product = wc_get_product($variation_id > 0 ? $variation_id : $product_id);
        
                    if (!$product || !$product->is_purchasable()) {
                        continue;
                    }

                    if ($product->managing_stock() && !$product->has_enough_stock($quantity)) {
                        continue;
                    }

                    WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
                }

                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
        }
    }
}
