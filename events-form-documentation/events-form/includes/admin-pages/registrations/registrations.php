<?php
if (!defined('ABSPATH')) exit;

echo '<div class="wrap"><h1>Manage Registrations</h1></div>';

echo '<div style="margin-bottom: 40px; padding: 20px; background: #fff; border: 1px solid #ddd;">';
include __DIR__ . '/add-registration.php';
echo '</div>';

echo '<hr style="margin:40px 0;">';

echo '<div style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #ddd;">';
include __DIR__ . '/list-registrations.php';
echo '</div>';
