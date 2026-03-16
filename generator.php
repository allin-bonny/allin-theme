<?php
/**
 * Allin WordPress Starter Kit Generator
 * Core ZIP builder engine
 *
 * @package AllinStarterKit
 */

class StarterKitGenerator
{
    private array $theme = [];
    private string $boilerplate_dir;
    private string $plugins_dir;
    private array $replacements = [];

    /**
     * Free plugins from WordPress.org
     * NOT bundled in ZIP — installed via admin notice
     */
    private array $wp_org_plugins = [
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
        ],
        'cptui' => [
            'name' => 'Custom Post Type UI',
            'slug' => 'cptui',
            'file' => 'custom-post-type-ui/custom-post-type-ui.php',
        ],
        'query-monitor' => [
            'name' => 'Query Monitor',
            'slug' => 'query-monitor',
            'file' => 'query-monitor/query-monitor.php',
        ],
    ];

    public function __construct(string $boilerplate_dir, string $plugins_dir)
    {
        $this->boilerplate_dir = rtrim($boilerplate_dir, '/');
        $this->plugins_dir = rtrim($plugins_dir, '/');
    }

    /**
     * Set theme data from POST input
     */
    public function set_theme(array $data): void
    {
        $slug = $this->sanitize_slug($data['slug'] ?? 'my-theme');
        $name = sanitize_text($data['name'] ?? 'My Theme');

        $this->theme = [
            'name' => $name,
            'slug' => $slug,
            'prefix' => $this->slug_to_prefix($slug),
            'const' => strtoupper($this->slug_to_prefix($slug)),
            'author' => sanitize_text($data['author'] ?? ''),
            'author_uri' => sanitize_text($data['author_uri'] ?? ''),
            'uri' => sanitize_text($data['theme_uri'] ?? ''),
            'description' => sanitize_text($data['description'] ?? ''),
            'version' => sanitize_text($data['version'] ?? '1.0.0'),
        ];

        $this->replacements = [
            // style.css header
            'Theme Name: _sk' => 'Theme Name: ' . $this->theme['name'],
            'Author: _sk Author' => 'Author: ' . $this->theme['author'],
            'Author URI: https://example.com' => 'Author URI: ' . $this->theme['author_uri'],
            'Theme URI: https://example.com' => 'Theme URI: ' . $this->theme['uri'],
            'Description: _sk Description' => 'Description: ' . $this->theme['description'],
            'Version: 1.0.0' => 'Version: ' . $this->theme['version'],
            'Text Domain: _sk' => 'Text Domain: ' . $this->theme['slug'],

            // PHP prefixes
            '_sk_' => $this->theme['prefix'] . '_',
            "'_sk'" => "'" . $this->theme['slug'] . "'",
            '_SK_' => $this->theme['const'] . '_',
            'class _Sk' => 'class ' . $this->slug_to_class($slug),
            '_sk-' => $this->theme['slug'] . '-',
            '"_sk"' => '"' . $this->theme['slug'] . '"',

            // Version constant
            '_S_VERSION' => strtoupper($this->theme['prefix']) . '_VERSION',

            // ACF/SCF options keys
            '"_sk_options"' => '"' . $this->theme['prefix'] . '_options"',
            "'_sk_options'" => "'" . $this->theme['prefix'] . "_options'",
            '"_sk_header_options"' => '"' . $this->theme['prefix'] . '_header_options"',
            "'_sk_header_options'" => "'" . $this->theme['prefix'] . "_header_options'",
            '"_sk_footer_options"' => '"' . $this->theme['prefix'] . '_footer_options"',
            "'_sk_footer_options'" => "'" . $this->theme['prefix'] . "_footer_options'",
            '"_sk_social_options"' => '"' . $this->theme['prefix'] . '_social_options"',
            "'_sk_social_options'" => "'" . $this->theme['prefix'] . "_social_options'",
        ];
    }

    /**
     * Build the ZIP and stream it to the browser
     */
    public function generate(): void
    {
        if (empty($this->theme)) {
            throw new \RuntimeException('Theme data not set. Call set_theme() first.');
        }

        $zip_path = sys_get_temp_dir() . '/' . $this->theme['slug'] . '-' . md5(uniqid()) . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive.');
        }

        // 1. Add processed theme files
        $this->add_directory($zip, $this->boilerplate_dir, $this->theme['slug']);

        // 2. Generate and inject required-plugins.php
        $this->add_required_plugins_notice($zip);

        // 3. Bundle premium plugins only (wp.org plugins are NOT bundled)
        $this->add_premium_plugins($zip);

        $zip->close();

        $this->stream_zip($zip_path, $this->theme['slug'] . '-starter-kit.zip');
    }

    /**
     * Recursively add a directory to the ZIP, processing file contents
     */
    private function add_directory(ZipArchive $zip, string $dir, string $zip_base): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $real_path = $file->getRealPath();
            $local_path = $zip_base . '/' . ltrim(str_replace($dir, '', $real_path), '/\\');

            if ($file->isDir()) {
                $zip->addEmptyDir($local_path);
            } else {
                $contents = file_get_contents($real_path);
                $contents = $this->process_contents($contents, $file->getFilename());
                $zip->addFromString($local_path, $contents);
            }
        }
    }

    /**
     * Run all replacements on file contents
     */
    private function process_contents(string $contents, string $filename): string
    {
        $text_extensions = ['php', 'css', 'js', 'json', 'txt', 'md', 'html', 'xml', 'po', 'pot'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $text_extensions, true)) {
            return $contents;
        }

        foreach ($this->replacements as $find => $replace) {
            $contents = str_replace($find, $replace, $contents);
        }

        if ($ext === 'json') {
            $contents = $this->process_acf_json($contents);
        }

        return $contents;
    }

    /**
     * Update ACF/SCF JSON field group data with new theme prefix
     */
    private function process_acf_json(string $json_string): string
    {
        $data = json_decode($json_string, true);
        if (!is_array($data)) {
            return $json_string;
        }

        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = str_replace('_sk_', $this->theme['prefix'] . '_', $value);
                $value = str_replace('_sk-', $this->theme['slug'] . '-', $value);
                $value = str_replace('_sk', $this->theme['slug'], $value);
            }
        });

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate required-plugins.php dynamically and inject into theme's inc/ folder
     */
    private function add_required_plugins_notice(ZipArchive $zip): void
    {
        $prefix = $this->theme['prefix'];
        $slug = $this->theme['slug'];
        $name = $this->theme['name'];

        // Build wp_org plugins array string
        $wp_org_array = "[\n";
        foreach ($this->wp_org_plugins as $key => $plugin) {
            $wp_org_array .= "        '{$key}' => [\n";
            $wp_org_array .= "            'name' => '{$plugin['name']}',\n";
            $wp_org_array .= "            'slug' => '{$plugin['slug']}',\n";
            $wp_org_array .= "            'file' => '{$plugin['file']}',\n";
            $wp_org_array .= "        ],\n";
        }
        $wp_org_array .= "    ]";

        // Build premium plugins array string
        $premium_array = "[\n";
        if (is_dir($this->plugins_dir)) {
            foreach (glob($this->plugins_dir . '/*.zip') as $p) {
                $basename = basename($p, '.zip');
                $premium_name = ucwords(str_replace('-', ' ', $basename));
                $premium_array .= "        '{$basename}' => '{$premium_name}',\n";
            }
        }
        $premium_array .= "    ]";

        $contents = <<<PHP
<?php
/**
 * Required Plugins Notice & Setup Page
 *
 * @package {$slug}
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────

function {$prefix}_required_plugins(): array {
    return {$wp_org_array};
}

function {$prefix}_premium_plugins(): array {
    return {$premium_array};
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function {$prefix}_get_missing_plugins(): array {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    \$missing = [];
    foreach ( {$prefix}_required_plugins() as \$key => \$plugin ) {
        if ( ! is_plugin_active( \$plugin['file'] ) ) {
            \$missing[ \$key ] = \$plugin;
        }
    }
    return \$missing;
}

function {$prefix}_all_required_active(): bool {
    return empty( {$prefix}_get_missing_plugins() );
}

function {$prefix}_setup_page_url(): string {
    return admin_url( 'themes.php?page={$slug}-setup' );
}

// ── On Theme Activation: set flag only, NO wp_die ────────────────────────────

// ── On Theme Activation ───────────────────────────────────────────────────────

function {$prefix}_check_plugins_on_activation(): void {

    // Auto-install premium bundled plugins (do NOT activate them)
    {$prefix}_install_premium_plugins();

    // Then check if free required plugins are active
    if ( {$prefix}_all_required_active() ) {
        return;
    }

    // Set redirect flag — admin_init will handle the redirect
    update_option( '{$prefix}_redirect_to_setup', true );
}
add_action( 'after_switch_theme', '{$prefix}_check_plugins_on_activation' );

/**
 * Install premium plugins from /_plugins/ folder silently.
 * Does NOT activate them — user activates manually.
 */
function {$prefix}_install_premium_plugins(): void {
    \$plugins_dir = get_template_directory() . '/_plugins/';

    if ( ! is_dir( \$plugins_dir ) ) {
        return;
    }

    \$zip_files = glob( \$plugins_dir . '*.zip' );

    if ( empty( \$zip_files ) ) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';

    foreach ( \$zip_files as \$zip_file ) {
        \$plugin_folder = basename( \$zip_file, '.zip' );

        // Skip if already installed
        if ( is_dir( WP_PLUGIN_DIR . '/' . \$plugin_folder ) ) {
            continue;
        }

        // Install silently — no activation
        \$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
        \$upgrader->install( \$zip_file );
    }
}

add_action( 'after_switch_theme', '{$prefix}_check_plugins_on_activation' );

function {$prefix}_maybe_redirect_to_setup(): void {
    if ( ! get_option( '{$prefix}_redirect_to_setup' ) ) {
        return;
    }
    delete_option( '{$prefix}_redirect_to_setup' );
    if ( ! {$prefix}_all_required_active() ) {
        wp_safe_redirect( {$prefix}_setup_page_url() );
        exit;
    }
}
add_action( 'admin_init', '{$prefix}_maybe_redirect_to_setup' );

// ── Admin Notice (banner only) ────────────────────────────────────────────────

function {$prefix}_required_plugins_notice(): void {
    if ( ! current_user_can( 'install_plugins' ) ) {
        return;
    }

    \$screen = get_current_screen();
    if ( \$screen && \$screen->id === 'appearance_page_{$slug}-setup' ) {
        return;
    }

    if ( {$prefix}_all_required_active() ) {
        return;
    }

    \$missing       = {$prefix}_get_missing_plugins();
    \$missing_count = count( \$missing );
    \$setup_url     = {$prefix}_setup_page_url();
    ?>
    <div class="notice notice-warning is-dismissible" style="border-left-color:#f59e0b;">
        <p>
            <strong>⚠ {$name} Theme:</strong>
            <?php echo esc_html( \$missing_count ); ?> required
            <?php echo \$missing_count === 1 ? 'plugin is' : 'plugins are'; ?>
            not active yet.
            <a href="<?php echo esc_url( \$setup_url ); ?>" style="font-weight:600;">
                → Go to Theme Setup
            </a>
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', '{$prefix}_required_plugins_notice' );

// ── Register Setup Page ───────────────────────────────────────────────────────

function {$prefix}_register_setup_page(): void {
    add_theme_page(
        '{$name} — Theme Setup',
        'Theme Setup',
        'install_plugins',
        '{$slug}-setup',
        '{$prefix}_render_setup_page'
    );
}
add_action( 'admin_menu', '{$prefix}_register_setup_page' );

// ── Render Setup Page ─────────────────────────────────────────────────────────

function {$prefix}_render_setup_page(): void {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    \$required = {$prefix}_required_plugins();
    \$premium  = {$prefix}_premium_plugins();
    \$missing  = {$prefix}_get_missing_plugins();
    \$all_done = empty( \$missing );
    ?>
    <div class="wrap" id="{$slug}-setup-wrap" style="max-width:860px;">

        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:2rem;">⚡</span> {$name} — Theme Setup
        </h1>

        <?php if ( \$all_done ) : ?>
            <div class="notice notice-success inline" style="padding:12px 16px;">
                <p><strong>✅ All required plugins are installed and active!</strong>
                Your theme is ready to use.
                <a href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>" class="button button-primary" style="margin-left:12px;">
                    → Open Customizer
                </a>
                </p>
            </div>
        <?php else : ?>
            <div class="notice notice-warning inline" style="padding:12px 16px;">
                <p><strong>⚠ Action Required:</strong>
                Please install and activate all required plugins below before using this theme.</p>
            </div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-top:24px;overflow:hidden;">
            <div style="background:#1e1b4b;padding:16px 24px;color:#fff;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.05em;">
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
                    <?php foreach ( \$required as \$key => \$plugin ) :
                        \$is_active    = is_plugin_active( \$plugin['file'] );
                        \$is_installed = file_exists( WP_PLUGIN_DIR . '/' . dirname( \$plugin['file'] ) );

                        if ( \$is_active ) {
                            \$status_html = '<span style="color:#16a34a;font-weight:600;">✅ Active</span>';
                            \$action_html = '—';
                        } elseif ( \$is_installed ) {
                            \$activate_url = wp_nonce_url(
                                admin_url( 'plugins.php?action=activate&plugin=' . \$plugin['file'] ),
                                'activate-plugin_' . \$plugin['file']
                            );
                            \$status_html = '<span style="color:#d97706;font-weight:600;">⚠ Installed, not active</span>';
                            \$action_html = '<a href="' . esc_url( \$activate_url ) . '" class="button button-primary button-small">Activate</a>';
                        } else {
                            \$install_url = wp_nonce_url(
                                admin_url( 'update.php?action=install-plugin&plugin=' . \$plugin['slug'] ),
                                'install-plugin_' . \$plugin['slug']
                            );
                            \$status_html = '<span style="color:#dc2626;font-weight:600;">✗ Not installed</span>';
                            \$action_html = '<a href="' . esc_url( \$install_url ) . '" class="button button-primary button-small">Install &amp; Activate</a>';
                        }
                    ?>
                    <tr>
                        <td style="text-align:center;font-size:18px;">
                            <?php echo \$is_active ? '🟢' : ( \$is_installed ? '🟡' : '🔴' ); ?>
                        </td>
                        <td><strong><?php echo esc_html( \$plugin['name'] ); ?></strong></td>
                        <td><?php echo \$status_html; ?></td>
                        <td><?php echo \$action_html; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ( ! empty( \$premium ) ) : ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-top:24px;overflow:hidden;">
            <div style="background:#7c3aed;padding:16px 24px;color:#fff;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.05em;">
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
                    <?php foreach ( \$premium as \$basename => \$pname ) :
    \$is_installed = is_dir( WP_PLUGIN_DIR . '/' . \$basename );
    \$plugin_files = \$is_installed ? get_plugins( '/' . \$basename ) : [];
    \$plugin_file  = \$is_installed && ! empty( \$plugin_files )
        ? \$basename . '/' . array_key_first( \$plugin_files )
        : '';
    \$is_active    = \$plugin_file && is_plugin_active( \$plugin_file );
?>
<tr>
    <td style="text-align:center;font-size:18px;">
        <?php echo \$is_active ? '🟢' : ( \$is_installed ? '🟡' : '🔴' ); ?>
    </td>
    <td>
        <strong><?php echo esc_html( \$pname ); ?></strong><br>
        <small style="color:#6b7280;">
            <code>/_plugins/<?php echo esc_html( \$basename ); ?>.zip</code>
        </small>
    </td>
    <td>
        <?php if ( \$is_active ) : ?>
            <span style="color:#16a34a;font-weight:600;">✅ Active</span>
        <?php elseif ( \$is_installed && \$plugin_file ) :
            \$activate_url = wp_nonce_url(
                admin_url( 'plugins.php?action=activate&plugin=' . \$plugin_file ),
                'activate-plugin_' . \$plugin_file
            ); ?>
            <span style="color:#d97706;font-weight:600;">⚠ Installed</span>
            <a href="<?php echo esc_url( \$activate_url ); ?>" class="button button-primary button-small" style="margin-left:8px;">
                Activate
            </a>
        <?php else : ?>
            <span style="color:#dc2626;font-weight:600;">✗ Not installed</span>
            <small style="display:block;color:#6b7280;margin-top:4px;">
                Will auto-install on next theme activation
            </small>
        <?php endif; ?>
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
                Once all show <strong style="color:#16a34a;">✅ Active</strong>, your theme is fully ready.
            </p>
        </div>

    </div>
    <?php
}
PHP;

        $zip->addFromString(
            $slug . '/inc/required-plugins.php',
            $contents
        );
    }


    /**
     * Add ONLY premium plugins from local /plugins folder
     */
    private function add_premium_plugins(ZipArchive $zip): void
    {
        if (!is_dir($this->plugins_dir)) {
            return;
        }

        foreach (glob($this->plugins_dir . '/*.zip') as $plugin_zip) {
            $zip->addFile(
                $plugin_zip,
                $this->theme['slug'] . '/_plugins/' . basename($plugin_zip)
            );
        }
    }

    /**
     * Stream ZIP to browser and clean up temp file
     */
    private function stream_zip(string $zip_path, string $download_name): void
    {
        if (headers_sent()) {
            throw new \RuntimeException('Headers already sent — cannot stream ZIP.');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . filesize($zip_path));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zip_path);
        @unlink($zip_path);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function sanitize_slug(string $slug): string
    {
        return strtolower(preg_replace('/[^a-z0-9-]/', '-', $slug));
    }

    private function slug_to_prefix(string $slug): string
    {
        return str_replace('-', '_', $slug);
    }

    private function slug_to_class(string $slug): string
    {
        return str_replace(' ', '_', ucwords(str_replace('-', ' ', $slug)));
    }
}

function sanitize_text(string $str): string
{
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}
