<?php
/**
 * Plugin Name: Postmark Inbound Order Status Updater
 * Description: Updates WooCommerce orders using Postmark Inbound email processing
 * Version: 1.0
 * Author: Vikas
 */

add_action('admin_menu', function () {
    add_menu_page('Postmark Inbound', 'Postmark Inbound', 'manage_options', 'postmark-inbound', 'pmib_settings_page');
    add_submenu_page('postmark-inbound', 'Inbound Logs', 'Logs', 'manage_options', 'postmark-inbound-logs', 'pmib_logs_page');
});

function pmib_settings_page() {
    ?>
    <div class="wrap">
        <h1>Postmark Inbound Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('pmib_settings');
            do_settings_sections('pmib_settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Inbound Email Address</th>
                    <td><input type="email" name="pmib_inbound_email" value="<?php echo esc_attr(get_option('pmib_inbound_email')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Allowed Admin Email(s) (comma-separated)</th>
                    <td><input type="text" name="pmib_admin_emails" value="<?php echo esc_attr(get_option('pmib_admin_emails')); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <p><strong>Webhook URL:</strong> <code><?php echo esc_url(rest_url('pmib/v1/inbound')); ?></code></p>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


function pmib_logs_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'pmib_logs';

    // Handle clear logs action
    if (isset($_POST['pmib_clear_logs']) && check_admin_referer('pmib_clear_logs_action', 'pmib_clear_logs_nonce')) {
        $wpdb->query("TRUNCATE TABLE $table");
        echo '<div class="notice notice-success is-dismissible"><p>Logs cleared successfully.</p></div>';
    }

    // Check if the logs table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

    ?>
    <div class="wrap">
        <h1>Postmark Inbound Logs</h1>
        <?php if ($table_exists !== $table): ?>
            <p><strong>Logs table does not exist yet.</strong> Please deactivate and reactivate the plugin to create the table.</p>
        <?php else: ?>
            <form method="post" style="margin-bottom:20px;">
                <?php wp_nonce_field('pmib_clear_logs_action', 'pmib_clear_logs_nonce'); ?>
                <input type="submit" name="pmib_clear_logs" class="button button-secondary" value="Clear Logs" onclick="return confirm('Are you sure you want to clear all logs?');" />
            </form>

            <?php
            $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY log_time DESC LIMIT 100");
            if (!is_array($logs)) {
                $logs = [];
            }
            ?>
            <style>
                .log-details {
                    display: none;
                    white-space: pre-wrap;
                    background: #f9f9f9;
                    border: 1px solid #ddd;
                    padding: 8px;
                    margin-top: 5px;
                    font-family: monospace;
                    font-size: 13px;
                }
                .log-toggle {
                    cursor: pointer;
                    color: #0073aa;
                    text-decoration: underline;
                    font-size: 0.9em;
                    margin-left: 10px;
                }
                .log-summary {
                    font-weight: bold;
                }
            </style>
            <table class="widefat fixed">
                <thead>
                    <tr>
                        <th width="150">Time</th>
                        <th>Summary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                        <tr><td colspan="2">No logs found.</td></tr>
                    <?php else : ?>
                        <?php foreach ($logs as $log) : 
                            $entry = $log->log_entry;
                            $summary = 'General log entry';

                            $decoded = json_decode($entry, true);
                            if ($decoded && is_array($decoded)) {
                                if (isset($decoded['success']) && isset($decoded['order_id']) && isset($decoded['new_status'])) {
                                    $summary = sprintf(
                                        'Order #%d status changed to "%s"',
                                        intval($decoded['order_id']),
                                        esc_html($decoded['new_status'])
                                    );
                                } elseif (isset($decoded['error'])) {
                                    $summary = 'Error: ' . esc_html($decoded['error']);
                                } elseif (isset($decoded['method'])) {
                                    $summary = 'Inbound received via ' . esc_html($decoded['method']);
                                } else {
                                    $summary = substr(strip_tags(json_encode($decoded)), 0, 100) . '...';
                                }
                            } else {
                                $summary = substr($entry, 0, 100) . (strlen($entry) > 100 ? '...' : '');
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($log->log_time); ?></td>
                                <td>
                                    <span class="log-summary"><?php echo $summary; ?></span>
                                    <span class="log-toggle" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'block' ? 'none' : 'block'">[Details]</span>
                                    <div class="log-details"><?php echo esc_html($entry); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}




add_action('admin_init', function () {
    register_setting('pmib_settings', 'pmib_inbound_email');
    register_setting('pmib_settings', 'pmib_admin_emails');

    // Populate all admin emails if option is empty
    $current = get_option('pmib_admin_emails');
    if (empty($current)) {
        $admins = get_users(['role' => 'administrator']);
        $emails = array_map(function($user) {
            return $user->user_email;
        }, $admins);
        update_option('pmib_admin_emails', implode(',', $emails));
    }
});

add_filter('woocommerce_email_headers', function ($headers, $email_id, $object) {
    if ($email_id === 'new_order') {
        $reply_to = get_option('pmib_inbound_email');
        if ($reply_to) {
            // Split headers by new lines
            $header_lines = explode("\r\n", $headers);
            // Filter out any existing Reply-To headers
            $header_lines = array_filter($header_lines, function($line) {
                return stripos($line, 'Reply-To:') === false;
            });
            // Add only Postmark inbound reply-to
            $header_lines[] = 'Reply-To: ' . sanitize_email($reply_to);
            // Rebuild headers string
            $headers = implode("\r\n", $header_lines) . "\r\n";
        }
    }
    return $headers;
}, 10, 3);


add_action('rest_api_init', function () {
    register_rest_route('pmib/v1', '/inbound', [
        'methods'  => ['GET', 'POST'],
        'callback' => 'pmib_handle_inbound',
        'permission_callback' => '__return_true',
    ]);
});

function pmib_log($message) {
    global $wpdb;
    $table = $wpdb->prefix . 'pmib_logs';
    if (!is_string($message)) {
        $message = wp_json_encode($message);
    }
    $wpdb->insert($table, [
        'log_time' => current_time('mysql'),
        'log_entry' => $message
    ]);
}

function pmib_create_log_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'pmib_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        log_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        log_entry LONGTEXT
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'pmib_create_log_table');

function pmib_handle_inbound(WP_REST_Request $request) {
    $method = $request->get_method();
    $params = $request->get_json_params();
    $query = $request->get_query_params();

    pmib_log("Inbound received via $method");
    pmib_log($params);

    if ($method === 'GET') {
        return [
            'method' => $method,
            'params' => $params,
            'query'  => $query,
            'message' => 'GET route works.'
        ];
    }

    if (empty($params['FromFull']['Email']) || empty($params['StrippedTextReply'])) {
        return new WP_REST_Response(['error' => 'Missing data'], 400);
    }

    $admin_emails = array_map('trim', explode(',', get_option('pmib_admin_emails')));
    $from_email = sanitize_email($params['FromFull']['Email']);

    if (!in_array($from_email, $admin_emails)) {
        return new WP_REST_Response(['error' => 'Unauthorized email'], 403);
    }

    $text = strtolower($params['StrippedTextReply']);
    $status = null;
    if (strpos($text, 'cancel') !== false) {
        $status = 'cancelled';
    } elseif (strpos($text, 'refund') !== false) {
        $status = 'refunded';
    } elseif (strpos($text, 'complete') !== false || strpos($text, 'delivered') !== false) {
        $status = 'completed';
    }

    if (!$status) {
        return new WP_REST_Response(['error' => 'No valid status found'], 400);
    }

    // Extract order ID from both Subject and Body
    $subject_order_id = null;
    $body_order_id = null;

    if (!empty($params['Subject']) && preg_match('/#(\d+)/', $params['Subject'], $matches)) {
        $subject_order_id = absint($matches[1]);
    }

    $body_text = $params['TextBody'] ?? '';
    $body_text .= "\n" . ($params['HtmlBody'] ?? '');

    if (preg_match_all('/#(\d+)/', $body_text, $matches)) {
        $body_order_id = absint($matches[1][0]);
    }

    if ($subject_order_id && $body_order_id && $subject_order_id === $body_order_id) {
        $order = wc_get_order($subject_order_id);
        if (!$order) {
            return new WP_REST_Response(['error' => 'Order not found.'], 404);
        }
    } else {
        return new WP_REST_Response(['error' => 'Order number mismatch between subject and body'], 400);
    }

    $order->update_status($status, 'Updated via Postmark Inbound');

    wp_mail($from_email, 'Order ' . $order->get_id() . ' updated', 'Status changed to ' . $status);

    return ['success' => true, 'order_id' => $order->get_id(), 'new_status' => $status];
}

