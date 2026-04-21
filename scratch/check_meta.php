<?php
require_once('../../../../wp-load.php');
$user_id = get_current_user_id();
$theme = get_user_meta($user_id, '_hizli_kasa_tema', true);
echo "Current User ID: " . $user_id . "\n";
echo "Theme Meta: " . $theme . "\n";
