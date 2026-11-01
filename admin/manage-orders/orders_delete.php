// Delete an order (only unpaid pending, cancelled or failed-delivery orders)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') == 'delete') {
    $order_id = (int)($_POST['order_id'] ?? 0);

    try {
        mysqli_begin_transaction($conn);

        $order_result = mysqli_query($conn, "SELECT fulfillment_status, payment_status FROM orders WHERE order_id = $order_id");
        $order = mysqli_fetch_assoc($order_result);

        if (!$order) {
            mysqli_rollback($conn);
            $errors[] = 'Order not found.';
        } elseif ($order['payment_status'] != 'unpaid'
                  || !in_array($order['fulfillment_status'], ['pending', 'cancelled', 'failed_delivery'])) {
            mysqli_rollback($conn);
            $errors[] = 'Only unpaid pending, cancelled, or failed-delivery orders can be deleted.';
        } else {
            // A pending order still holds stock, so give it back
            if ($order['fulfillment_status'] == 'pending') {
                $items_result = mysqli_query($conn, "SELECT variant_id, quantity FROM order_items WHERE order_id = $order_id");
                foreach (mysqli_fetch_all($items_result, MYSQLI_ASSOC) as $item) {
                    mysqli_query($conn, "UPDATE product_variants SET stock = stock + " . (int)$item['quantity'] . " WHERE variant_id = " . (int)$item['variant_id']);
                }
            }
            mysqli_query($conn, "DELETE FROM order_items WHERE order_id = $order_id");
            mysqli_query($conn, "DELETE FROM orders WHERE order_id = $order_id");
            mysqli_commit($conn);

            $_SESSION['success'] = 'Order deleted.';
            header('Location: ' . BASE_URL . 'admin/manage-orders/?order_view=' . $order_view);
            exit;
        }
    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($conn);
        $errors[] = 'Could not delete this order because it is still referenced by store data.';
    }
}
