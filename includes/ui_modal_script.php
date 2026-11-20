<?php
/**
 * Loads ui-modal.js so window.alert uses the in-app modal (and UiModal is available).
 * Before include, set $UI_MODAL_PREFIX: '' for site root pages, '../' for Admin/ and Userprofile/.
 */
if (!isset($UI_MODAL_PREFIX)) {
  $UI_MODAL_PREFIX = '';
}
$__ui_modal_src = rtrim((string) $UI_MODAL_PREFIX, '/') . '/assets/ui-modal.js';
?>
<script src="<?php echo htmlspecialchars($__ui_modal_src, ENT_QUOTES, 'UTF-8'); ?>"></script>
