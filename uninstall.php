<?php
/**
 * Removes the plugin's data when the plugin is deleted.
 *
 * @package TempusFugit
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/uninstall-functions.php';

tempus_fugit_uninstall();
