<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once(__DIR__ . "/../../includes/config.php");
require_once(__DIR__ . "/../../includes/admin_redirect.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $return_path = safe_admin_return_path($_SERVER['REQUEST_URI'] ?? '');
    $_SESSION['admin_login_notice'] = 'Please sign in with an active administrator account to access that page.';
    header('Location: ' . BASE_URL . 'admin/login.php?redirect=' . urlencode($return_path));
    exit;
}

$admin_status_stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE user_id = ? AND role = 'admin'");
$admin_user_id = (int)$_SESSION['user_id'];
mysqli_stmt_bind_param($admin_status_stmt, 'i', $admin_user_id);
mysqli_stmt_execute($admin_status_stmt);
$admin_account = mysqli_fetch_assoc(mysqli_stmt_get_result($admin_status_stmt));

if (!$admin_account || $admin_account['status'] !== 'active') {
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
    $return_path = safe_admin_return_path($_SERVER['REQUEST_URI'] ?? '');
    $_SESSION['admin_login_notice'] = 'Your administrator account is inactive. Contact another administrator for assistance.';
    header('Location: ' . BASE_URL . 'admin/login.php?redirect=' . urlencode($return_path));
    exit;
}

function admin_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function admin_verify_csrf($token): bool
{
    return is_string($token)
        && isset($_SESSION['admin_csrf'])
        && hash_equals($_SESSION['admin_csrf'], $token);
}

function admin_set_flash(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function admin_take_flash(): ?array
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return is_array($flash) ? $flash : null;
}

function admin_pagination_page(int $totalItems, int $pageSize = 10, string $pageKey = 'page'): array
{
    $totalPages = max(1, (int)ceil($totalItems / $pageSize));
    $requestedPage = filter_var($_GET[$pageKey] ?? 1, FILTER_VALIDATE_INT);
    $page = $requestedPage === false ? 1 : max(1, min($requestedPage, $totalPages));

    return [
        'page' => $page,
        'page_size' => $pageSize,
        'total_pages' => $totalPages,
        'offset' => ($page - 1) * $pageSize,
    ];
}

function admin_render_pagination(int $currentPage, int $totalPages, string $pageKey = 'page'): void
{
    if ($totalPages <= 1) {
        return;
    }

    $query = $_GET;
    unset($query[$pageKey]);
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $baseUrl = is_string($path) ? $path : '';
    $buildUrl = static function (int $page) use ($baseUrl, $query, $pageKey): string {
        return $baseUrl . '?' . http_build_query(array_merge($query, [$pageKey => $page]));
    };
    $firstPage = max(1, $currentPage - 2);
    $lastPage = min($totalPages, $currentPage + 2);
    ?>
    <nav class="manage-pagination" aria-label="Table pages">
      <?php if ($currentPage > 1) { ?>
        <a class="manage-pagination-link" href="<?php echo admin_escape($buildUrl($currentPage - 1)); ?>" rel="prev">Previous</a>
      <?php } ?>
      <?php for ($page = $firstPage; $page <= $lastPage; $page++) { ?>
        <?php if ($page === $currentPage) { ?>
          <span class="manage-pagination-link is-current" aria-current="page"><?php echo $page; ?></span>
        <?php } else { ?>
          <a class="manage-pagination-link" href="<?php echo admin_escape($buildUrl($page)); ?>"><?php echo $page; ?></a>
        <?php } ?>
      <?php } ?>
      <?php if ($currentPage < $totalPages) { ?>
        <a class="manage-pagination-link" href="<?php echo admin_escape($buildUrl($currentPage + 1)); ?>" rel="next">Next</a>
      <?php } ?>
    </nav>
    <?php
}
