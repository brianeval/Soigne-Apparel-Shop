<?php
// Save a restock
if ($post_action == 'create') {
    $supplier_id = (int)$posted_supplier_id;
    if ($supplier_id < 1) {
        $errors[] = 'Choose a supplier for this restock.';
    }
    if (($_POST['confirmed'] ?? '') != '1') {
        $errors[] = 'Confirm that you reviewed the restock details before saving.';
    }

    $restock_lines = [];
    foreach ($posted_variant_ids as $index => $posted_variant_id) {
        if ($posted_variant_id == '') {
            continue;
        }
        $variant_id = (int)$posted_variant_id;
        $quantity = (int)($posted_quantities[$index] ?? 0);
        $unit_cost = $posted_unit_costs[$index] ?? '';

        if ($variant_id < 1 || $quantity < 1 || !is_numeric($unit_cost) || $unit_cost < 0) {
            $errors[] = 'Choose a valid product variant and enter a positive quantity and valid unit cost for each restock line.';
            continue;
        }

        // The variant must belong to the chosen supplier
        $variant_result = mysqli_query($conn,
            "SELECT v.variant_id
             FROM product_variants v
             JOIN product_colors pc ON pc.product_color_id = v.product_color_id
             JOIN products p ON p.product_id = pc.product_id
             WHERE v.variant_id = $variant_id AND p.supplier_id = $supplier_id"
        );
        if (mysqli_num_rows($variant_result) == 0) {
            $errors[] = 'Every selected variant must belong to the chosen supplier.';
            continue;
        }

        $restock_lines[] = ['variant_id' => $variant_id, 'quantity' => $quantity, 'unit_cost' => $unit_cost];
    }
    if (!$restock_lines) {
        $errors[] = 'Select at least one product variant before saving the restock.';
    }

    if (!$errors) {
        try {
            mysqli_begin_transaction($conn);

            $restock_date = date('Y-m-d');
            $restock_result = mysqli_execute_query($conn, "INSERT INTO restocks (batch_number, supplier_id, restock_date) VALUES (NULL, ?, ?)", [$supplier_id, $restock_date]);
            $restock_id = mysqli_insert_id($conn);

            // Batch number looks like RS-2026-00012
            $batch_number = sprintf('RS-%s-%05d', date('Y'), $restock_id);
            mysqli_query($conn, "UPDATE restocks SET batch_number = '$batch_number' WHERE restock_id = $restock_id");

            foreach ($restock_lines as $line) {
                mysqli_execute_query($conn, "INSERT INTO restock_items (restock_id, variant_id, quantity, unit_cost) VALUES (?, ?, ?, ?)", [$restock_id, $line['variant_id'], $line['quantity'], $line['unit_cost']]);

                // Add the received quantity to the stock
                mysqli_query($conn, "UPDATE product_variants SET stock = stock + " . $line['quantity'] . " WHERE variant_id = " . $line['variant_id']);
            }

            mysqli_commit($conn);
            $_SESSION['success'] = 'Restock ' . $batch_number . ' saved and stock updated.';
            header('Location: ' . BASE_URL . 'admin/manage-inventory/');
            exit;
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $errors[] = 'Could not save the restock. Please try again.';
        }
    }
}
