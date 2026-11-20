<?php
declare(strict_types=1);
/**
 * Live bell badge: polls Admin/api/notifications_poll.php (see js/admin-notify-live.js).
 * Include once before </body> on authenticated admin pages (not admin-login.php).
 */
?>
<script src="js/admin-notify-live.js?v=4" defer data-poll-ms="5000"></script>
