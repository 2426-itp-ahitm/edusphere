<?php

    if (!defined('ABSPATH')) exit;

    global $wpdb;

    echo '<div class="wrap"><h1>Manage Interests</h1></div>';

    // Include modular files
    echo '<div style="margin-bottom: 40px; padding: 20px; background: #fff; border: 1px solid #ddd;">';
    require_once plugin_dir_path(__FILE__) . 'add-interest.php';
    echo '</div>';

    echo '<hr style="margin:40px 0;">';

    echo '<div style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #ddd;">';
    require_once plugin_dir_path(__FILE__) . 'list-interests.php';
    echo '</div>';
?>
