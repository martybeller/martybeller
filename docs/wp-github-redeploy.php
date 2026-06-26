<?php
/**
 * Plugin Name: MB GitHub Redeploy Webhook
 * Description: Dispatches a GitHub repository event when relevant WordPress content changes.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ── Configuration ────────────────────────────────────────────────────────────
// Set these two values before uploading the file to WordPress.
// Do NOT commit this file to a public repository once the token is filled in.

if (!defined('MB_GITHUB_REPO')) {
    define('MB_GITHUB_REPO', 'martybeller/martybeller');
}

if (!defined('MB_GITHUB_TOKEN')) {
    define('MB_GITHUB_TOKEN', '');
}

// ─────────────────────────────────────────────────────────────────────────────

if (MB_GITHUB_REPO === '' || MB_GITHUB_TOKEN === '') {
    error_log('MB GitHub redeploy webhook is not configured: MB_GITHUB_REPO and MB_GITHUB_TOKEN must be set.');
    return;
}

if (!defined('MB_GITHUB_EVENT_TYPE')) {
    define('MB_GITHUB_EVENT_TYPE', 'wp-content-updated');
}

if (!defined('MB_GITHUB_POST_TYPES')) {
    define('MB_GITHUB_POST_TYPES', ['videos', 'work']);
}

if (!defined('MB_GITHUB_DISPATCH_DEBOUNCE_SECONDS')) {
    define('MB_GITHUB_DISPATCH_DEBOUNCE_SECONDS', 120);
}

if (!function_exists('mb_should_dispatch_for_post')) {
    function mb_should_dispatch_for_post($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return false;
        }

        return in_array($post->post_type, MB_GITHUB_POST_TYPES, true);
    }
}

if (!function_exists('mb_dispatch_github_redeploy')) {
    function mb_dispatch_github_redeploy($post_id, $action)
    {
        if (!mb_should_dispatch_for_post($post_id)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        // Debounce all dispatches so bursty editorial activity doesn't trigger
        // a separate build for every single save.
        $debounce_key = 'mb_github_dispatch_debounce';
        if (get_transient($debounce_key)) {
            return;
        }

        $cache_key = sprintf('mb_github_dispatch_%d_%s', (int) $post_id, sanitize_key($action));
        if (get_transient($cache_key)) {
            return;
        }

        set_transient($debounce_key, 1, (int) MB_GITHUB_DISPATCH_DEBOUNCE_SECONDS);
        set_transient($cache_key, 1, 30);

        $response = wp_remote_post(
            sprintf('https://api.github.com/repos/%s/dispatches', MB_GITHUB_REPO),
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => 'Bearer ' . MB_GITHUB_TOKEN,
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent' => 'mb-wordpress-redeploy-webhook',
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'event_type' => MB_GITHUB_EVENT_TYPE,
                    'client_payload' => [
                        'post_id' => (int) $post_id,
                        'post_type' => $post->post_type,
                        'post_status' => $post->post_status,
                        'post_action' => $action,
                        'post_modified_gmt' => $post->post_modified_gmt,
                    ],
                ]),
            ]
        );

        if (is_wp_error($response)) {
            error_log('MB GitHub redeploy dispatch failed: ' . $response->get_error_message());
            return;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            error_log('MB GitHub redeploy dispatch failed with HTTP status: ' . $status);
        }
    }
}

if (!function_exists('mb_handle_save_post_dispatch')) {
    function mb_handle_save_post_dispatch($post_id, $post, $update)
    {
        if ($post->post_status !== 'publish') {
            return;
        }

        mb_dispatch_github_redeploy($post_id, $update ? 'update' : 'create');
    }
}

if (!function_exists('mb_handle_trashed_post_dispatch')) {
    function mb_handle_trashed_post_dispatch($post_id)
    {
        mb_dispatch_github_redeploy($post_id, 'trash');
    }
}

if (!function_exists('mb_handle_untrashed_post_dispatch')) {
    function mb_handle_untrashed_post_dispatch($post_id)
    {
        mb_dispatch_github_redeploy($post_id, 'restore');
    }
}

if (!function_exists('mb_handle_deleted_post_dispatch')) {
    function mb_handle_deleted_post_dispatch($post_id)
    {
        mb_dispatch_github_redeploy($post_id, 'delete');
    }
}

add_action('save_post', 'mb_handle_save_post_dispatch', 20, 3);
add_action('trashed_post', 'mb_handle_trashed_post_dispatch');
add_action('untrashed_post', 'mb_handle_untrashed_post_dispatch');
add_action('deleted_post', 'mb_handle_deleted_post_dispatch');
