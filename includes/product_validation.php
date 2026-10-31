<?php
function product_positive_integer($value)
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT);
    return $integer !== false && $integer > 0 ? $integer : null;
}

function product_nonnegative_integer($value)
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT);
    return $integer !== false && $integer >= 0 ? $integer : null;
}

function product_price_value($value)
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return null;
    }

    $price = (string)$value;
    if (!preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $price)) {
        return null;
    }

    return (float)$price;
}
