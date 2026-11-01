if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $send_email = false;   // becomes true when the customer should be told about the change

    if ($action == 'update_address') {
        // Change the shipping address (pending orders only)
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        if ($shipping_address == '') {
            $errors[] = 'Enter a shipping address.';
        } elseif ($order['fulfillment_status'] != 'pending') {
            $errors[] = 'Only pending orders can have their shipping address changed.';
        } else {
            $address_result = mysqli_execute_query($conn, "UPDATE orders SET shipping_address = ? WHERE order_id = ?", [$shipping_address, $order_id]);
            $order['shipping_address'] = $shipping_address;
            $send_email = true;
            $success_text = 'Shipping address updated.';
        }
    } elseif (in_array($action, ['mark_shipped', 'mark_delivered', 'mark_failed_delivery', 'mark_paid', 'cancel'])) {
        // action => [status the order must have now, status it gets]
        $steps = [
            'mark_shipped' => ['pending', 'shipped'],
            'mark_delivered' => ['shipped', 'delivered'],
            'mark_failed_delivery' => ['shipped', 'failed_delivery'],
            'cancel' => ['pending', 'cancelled'],
        ];
        $success_messages = [
            'mark_shipped' => 'Order marked as shipped.',
            'mark_delivered' => 'Order marked as delivered.',
            'mark_failed_delivery' => 'Delivery marked as failed. Returned stock has been restored.',
            'mark_paid' => 'Payment marked as paid.',
            'cancel' => 'Order cancelled. Reserved stock has been restored.',
        ];

        try {
            mysqli_begin_transaction($conn);

            // Read the current status again
            $status_result = mysqli_query($conn, "SELECT fulfillment_status, payment_status FROM orders WHERE order_id = $order_id");
            $current = mysqli_fetch_assoc($status_result);

            if ($action == 'mark_paid' && $current['fulfillment_status'] == 'delivered' && $current['payment_status'] == 'unpaid') {
                mysqli_query($conn, "UPDATE orders SET payment_status = 'paid' WHERE order_id = $order_id");
                $order['payment_status'] = 'paid';
                $allowed = true;
            } elseif ($action != 'mark_paid' && $current['payment_status'] == 'unpaid' && $current['fulfillment_status'] == $steps[$action][0]) {
                $new_status = $steps[$action][1];

                // Failed delivery and cancelled orders give their stock back
                if ($new_status == 'failed_delivery' || $new_status == 'cancelled') {
                    $items_result = mysqli_query($conn, "SELECT variant_id, quantity FROM order_items WHERE order_id = $order_id");
                    foreach (mysqli_fetch_all($items_result, MYSQLI_ASSOC) as $item) {
                        mysqli_query($conn, "UPDATE product_variants SET stock = stock + " . (int)$item['quantity'] . " WHERE variant_id = " . (int)$item['variant_id']);
                    }
                }
                mysqli_query($conn, "UPDATE orders SET fulfillment_status = '$new_status' WHERE order_id = $order_id");
                $order['fulfillment_status'] = $new_status;
                $allowed = true;
            } else {
                $allowed = false;
            }

            if ($allowed) {
                mysqli_commit($conn);
                $send_email = true;
                $success_text = $success_messages[$action];
            } else {
                mysqli_rollback($conn);
                $errors[] = 'That action is not available for this order status.';
            }
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $errors[] = 'Could not update this order.';
        }
    } else {
        $errors[] = 'Choose a valid order action.';
    }

    // Tell the customer about the change by email
    if ($send_email) {
        $email_type = $action;
        $email_order = $order;
        if ($action == 'mark_paid') {
            // The paid email carries a PDF receipt, so it needs the order items
            $receipt_items_result = mysqli_query($conn,
                "SELECT product_name, color, size, quantity, unit_price AS price
                 FROM order_transaction_details WHERE order_id = $order_id ORDER BY order_item_id"
            );
            $email_order['items'] = mysqli_fetch_all($receipt_items_result, MYSQLI_ASSOC);
        }

        $mail_error = '';
        try {
            include("../includes/email_order_update.php");
        } catch (Exception $e) {
            $mail_error = $e->getMessage();
        }
        if ($mail_error != '') {
            error_log('Order update email failed for order #' . $order_id . ': ' . $mail_error);
            $success_text .= ' The customer email could not be sent.';
        } else {
            $success_text .= ' A confirmation email was sent to the customer.';
        }

        $_SESSION['success'] = $success_text;
        header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
        exit;
    }
}
