<?php
    $conn = mysqli_connect("localhost", "root", "", "soigne_apparel");
    mysqli_set_charset($conn, "utf8mb4");

    function peso($amount) {
        return "₱" . number_format($amount, 0);
    }
?>