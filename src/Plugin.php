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

                    if (method_exists($item, 'get_product_id')) {
                        $product_id = $item->get_product_id();
                    }

                    if (method_exists($item, 'get_variation_id')) {
                        $variation_id = $item->get_variation_id();
                    }

                    if (method_exists($item, 'get_quantity')) {
                        $quantity = $item->get_quantity();
                    }

                    if (method_exists($item, 'get_variation_attributes')) {
                        $variation = $item->get_variation_attributes();
                    }

                    if ($product_id == 0 || $quantity == 0) {
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
