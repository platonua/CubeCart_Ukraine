<?php

class Gateway {

    private $_config;
    private $_module;
    private $_basket;

    public function __construct($module = false, $basket = false) {
        $this->_module = $module;
        $this->_basket = & $GLOBALS['cart']->basket;
    }

    ##################################################

    public function transfer() {
        $transfer = array(
            'action' => 'https://secure.platononline.com/payment/auth',
            'method' => 'post',
            'target' => '_self',
            'submit' => 'auto',
        );
        return $transfer;
    }

    public function repeatVariables() {
        return false;
    }

    public function fixedVariables() {
        $data = array();
        $data['key'] = $this->_module['clientKey'];
        $data['order'] = $this->_basket['cart_order_id'];

        /* Prepare product data for coding */
        $data['data'] = base64_encode(
                json_encode(
                        array(
                            'amount' => sprintf("%01.2f", $this->_basket['total']),
                            'name' => 'Order from ' . $GLOBALS['config']->get('config', 'store_name'),
                            'currency' => $GLOBALS['config']->get('config', 'default_currency')
                        )
                )
        );

        $data['url'] = $GLOBALS['storeURL'] . '/index.php?_a=complete';

        /* Calculation of signature */
        $sign = md5(
                strtoupper(
                        strrev($this->_module['clientKey']) .
                        strrev($data['data']) .
                        strrev($data['url']) .
                        strrev($this->_module['clientPassword'])
                )
        );

        $data['sign'] = $sign;

        return $data;
    }

    ##################################################

    public function call() {
        return false;
    }

    public function process() {
        $order = Order::getInstance();
        $cart_order_id = $_POST['order'];
        $order_summary = $order->getSummary($cart_order_id);

        // generate signature from callback params
        $sign = md5(
                strtoupper(
                        strrev($_POST['email']) .
                        $this->_module['clientPassword'] .
                        $_POST['order'] .
                        strrev(substr($_POST['card'], 0, 6) . substr($_POST['card'], -4))
                )
        );

        // verify signature
        if ($_POST['sign'] !== $sign) {
            die("ERROR: Bad signature");
        }


        switch ($_POST['status']) {
            case 'SALE':
                $notes = 'Card was successfully processed.';
                $status = 'Processed';
                $order->orderStatus(Order::ORDER_PROCESS, $cart_order_id);
                $order->paymentStatus(Order::PAYMENT_SUCCESS, $cart_order_id);
                break;
            case 'REFUND':
                $notes = 'Payment was refunded by customer.';
                $status = 'Canceled';
                $order->orderStatus(Order::ORDER_CANCELLED, $cart_order_id);
                $order->paymentStatus(Order::PAYMENT_CANCEL, $cart_order_id);
                break;
            case 'CHARGEBACK':
                break;
            default:
                die("ERROR: Invalid callback data");
        }

        $transData['notes'] = $notes;
        $transData['gateway'] = 'platon';
        $transData['order_id'] = $cart_order_id;
        $transData['trans_id'] = $_POST['id'];
        $transData['amount'] = isset($_POST['amount']) ? $_POST['amount'] : '';
        $transData['status'] = $status;
        $transData['customer_id'] = $order_summary['customer_id'];
        $transData['extra'] = '';
        $order->logTransaction($transData);

        exit("OK");
                
    }

    public function form() {
        return false;
    }

}