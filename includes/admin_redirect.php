<?php
function safe_admin_return_path($destination): string
{
    $fallback = BASE_URL . 'admin/manage-products/';
    if (!is_string($destination) || $destination === '' || strlen($destination) > 2048) {
        return $fallback;
    }

    $parts = parse_url($destination);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user'])
        || isset($parts['pass']) || isset($parts['fragment'])) {
        return $fallback;
    }

    $path = $parts['path'] ?? '';
    $admin_prefix = rtrim(BASE_URL, '/') . '/admin/';
    $decoded_path = rawurldecode($path);
    if (strpos($decoded_path, $admin_prefix) !== 0
        || preg_match('#(?:^|/)\.\.?(/|$)|[\\\\\x00-\x1f]#', $decoded_path)
        || !preg_match('#^' . preg_quote($admin_prefix, '#') . '[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*(?:\.php)?/?$#D', $decoded_path)
        || $decoded_path === $admin_prefix . 'login.php') {
        return $fallback;
    }

    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $submitted_query);
        foreach ($submitted_query as $key => $value) {
            if (!preg_match('/\A(?:id|page|[A-Za-z0-9_-]+_page)\z/', (string)$key)
                || (!is_string($value) && !is_int($value))
                || !ctype_digit((string)$value)) {
                continue;
            }
            $query[$key] = (string)$value;
        }
    }

    return $decoded_path . ($query ? '?' . http_build_query($query) : '');
}
