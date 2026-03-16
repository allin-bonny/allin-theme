<?php
/**
 * Required Plugins Notice & Setup Page
 *
 * @package _sk
 */

if (!defined('ABSPATH')) {
    exit;
}


// ── Data ─────────────────────────────────────────────────────────────────────

function _sk_required_plugins(): array
{
    return [
        'secure-custom-fields' => [
            'name' => 'Secure Custom Fields',
            'slug' => 'secure-custom-fields',
            'file' => 'secure-custom-fields/secure-custom-fields.php',
        ],
        'elementor' => [
            'name' => 'Elementor',
            'slug' => 'elementor',
            'file' => 'elementor/elementor.php',
        ],
        'wordpress-seo' => [
            'name' => 'Yoast SEO',
            'slug' => 'wordpress-seo',
            'file' => 'wordpress-seo/wp-seo.php',
        ],
        'better-search-replace' => [
            'name' => 'Better Search Replace',
            'slug' => 'better-search-replace',
            'file' => 'better-search-replace/better-search-replace.php',
        ]
    ];
}

function _sk_premium_plugins(): array
{
    $premium = [];
    $dir = get_template_directory() . '/_plugins/';
    if (is_dir($dir)) {
        foreach (glob($dir . '*.zip') as $zip) {
            $basename = basename($zip, '.zip');
            $premium[$basename] = ucwords(str_replace('-', ' ', $basename));
        }
    }
    return $premium;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function _sk_get_missing_plugins(): array
{
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $missing = [];
    foreach (_sk_required_plugins() as $key => $plugin) {
        if (!is_plugin_active($plugin['file'])) {
            $missing[$key] = $plugin;
        }
    }
    return $missing;
}

function _sk_all_required_active(): bool
{
    return empty(_sk_get_missing_plugins());
}

function _sk_setup_page_url(): string
{
    return admin_url('themes.php?page=_sk-setup');
}

// ── On Theme Activation: redirect instead of wp_die ──────────────────────────

function _sk_check_plugins_on_activation(): void
{
    if (_sk_all_required_active()) {
        return;
    }
    // Store a flag so we redirect on next admin load
    update_option('_sk_redirect_to_setup', true);
}
add_action('after_switch_theme', '_sk_check_plugins_on_activation');

function _sk_maybe_redirect_to_setup(): void
{
    if (!get_option('_sk_redirect_to_setup')) {
        return;
    }
    // Only redirect once
    delete_option('_sk_redirect_to_setup');

    if (!_sk_all_required_active()) {
        wp_safe_redirect(_sk_setup_page_url());
        exit;
    }
}
add_action('admin_init', '_sk_maybe_redirect_to_setup');

// ── Admin Notice (banner only — does NOT block plugin pages) ─────────────────

function _sk_required_plugins_notice(): void
{
    if (!current_user_can('install_plugins')) {
        return;
    }

    // Don't show notice on our own setup page
    $screen = get_current_screen();
    if ($screen && $screen->id === 'appearance_page__sk-setup') {
        return;
    }

    if (_sk_all_required_active()) {
        return;
    }

    $missing = _sk_get_missing_plugins();
    $missing_count = count($missing);
    $setup_url = _sk_setup_page_url();
    ?>
    <div class="notice notice-warning is-dismissible" style="border-left-color:#f59e0b;">
        <p>
            <strong>⚠ _sk Theme:</strong>
            <?php echo esc_html($missing_count); ?> required
            <?php echo $missing_count === 1 ? 'plugin is' : 'plugins are'; ?>
            not active yet.
            <a href="<?php echo esc_url($setup_url); ?>" style="font-weight:600;">
                → Go to Theme Setup
            </a>
            to install them.
        </p>
    </div>
    <?php
}
add_action('admin_notices', '_sk_required_plugins_notice');

// ── Register Setup Page ───────────────────────────────────────────────────────

function _sk_register_setup_page(): void
{
    add_theme_page(
        '_sk — Theme Setup',
        'Theme Setup',
        'install_plugins',
        '_sk-setup',
        '_sk_render_setup_page'
    );
}
add_action('admin_menu', '_sk_register_setup_page');

// ── Render Setup Page ─────────────────────────────────────────────────────────

function _sk_render_setup_page(): void
{
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $required = _sk_required_plugins();
    $premium = _sk_premium_plugins();
    $missing = _sk_get_missing_plugins();
    $all_done = empty($missing);
    ?>
    <div class="wrap" id="_sk-setup-wrap" style="max-width:860px;">

        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:2rem;">⚡</span> _sk — Theme Setup
        </h1>

        <?php if ($all_done): ?>
            <div class="notice notice-success inline" style="padding:12px 16px;">
                <p><strong>✅ All required plugins are installed and active!</strong>
                    Your theme is ready to use.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning inline" style="padding:12px 16px;">
                <p><strong>⚠ Action Required:</strong>
                    Please install and activate all required plugins below before using this theme.</p>
            </div>
        <?php endif; ?>

        <div style="
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:8px;
            margin-top:24px;
            overflow:hidden;
        ">
            <div style="
                background:#1e1b4b;
                padding:16px 24px;
                color:#fff;
                font-weight:700;
                font-size:13px;
                text-transform:uppercase;
                letter-spacing:.05em;
            ">
                📦 Required Plugins (WordPress.org)
            </div>

            <table class="widefat" style="border:none;">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Plugin</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($required as $key => $plugin):
                        $is_active = is_plugin_active($plugin['file']);
                        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . dirname($plugin['file']));

                        if ($is_active) {
                            $status_html = '<span style="color:#16a34a;font-weight:600;">✅ Active</span>';
                            $action_html = '—';
                        } elseif ($is_installed) {
                            $activate_url = wp_nonce_url(
                                admin_url('plugins.php?action=activate&plugin=' . $plugin['file']),
                                'activate-plugin_' . $plugin['file']
                            );
                            $status_html = '<span style="color:#d97706;font-weight:600;">⚠ Installed, not active</span>';
                            $action_html = '<a href="' . esc_url($activate_url) . '" class="button button-primary button-small">Activate</a>';
                        } else {
                            $install_url = wp_nonce_url(
                                admin_url('update.php?action=install-plugin&plugin=' . $plugin['slug']),
                                'install-plugin_' . $plugin['slug']
                            );
                            $status_html = '<span style="color:#dc2626;font-weight:600;">✗ Not installed</span>';
                            $action_html = '<a href="' . esc_url($install_url) . '" class="button button-primary button-small">Install &amp; Activate</a>';
                        }
                        ?>
                        <tr>
                            <td style="text-align:center;font-size:18px;">
                                <?php echo $is_active ? '🟢' : ($is_installed ? '🟡' : '🔴'); ?>
                            </td>
                            <td><strong><?php echo esc_html($plugin['name']); ?></strong></td>
                            <td><?php echo $status_html; ?></td>
                            <td><?php echo $action_html; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($premium)): ?>
            <div style="
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:8px;
            margin-top:24px;
            overflow:hidden;
        ">
                <div style="
                background:#7c3aed;
                padding:16px 24px;
                color:#fff;
                font-weight:700;
                font-size:13px;
                text-transform:uppercase;
                letter-spacing:.05em;
            ">
                    ⭐ Premium Plugins (Bundled in Theme)
                </div>
                <table class="widefat" style="border:none;">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Plugin</th>
                            <th>How to Install</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($premium as $basename => $pname):
                            $zip_path = get_template_directory() . '/_plugins/' . $basename . '.zip';
                            $upload_url = admin_url('plugin-install.php');
                            ?>
                            <tr>
                                <td style="text-align:center;font-size:18px;">⭐</td>
                                <td>
                                    <strong><?php echo esc_html($pname); ?></strong><br>
                                    <small style="color:#6b7280;">
                                        Found at: <code>/_plugins/<?php echo esc_html($basename); ?>.zip</code>
                                    </small>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($upload_url); ?>" class="button button-secondary button-small">
                                        Plugins → Add New → Upload Plugin
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div style="margin-top:24px;padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
            <p style="margin:0;color:#6b7280;font-size:13px;">
                💡 <strong>Tip:</strong> After installing all plugins, refresh this page.
                Once all plugins show <strong style="color:#16a34a;">✅ Active</strong>,
                you can safely start customising your theme.
                <a href="<?php echo esc_url(admin_url('customize.php')); ?>">→ Open Customizer</a>
            </p>
        </div>

    </div>
    <?php
}
