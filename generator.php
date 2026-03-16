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
 * Required Plugins Notice
 *
 * Displays an admin notice prompting the user to install and activate
 * all required plugins before using the {$name} theme.
 *
 * @package {$slug}
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the list of required free plugins (WordPress.org)
 */
function {$prefix}_required_plugins(): array {
    return {$wp_org_array};
}

/**
 * Returns premium plugins bundled in the theme's /_plugins/ folder
 */
function {$prefix}_premium_plugins(): array {
    return {$premium_array};
}

/**
 * Returns list of required plugins that are not yet active
 */
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

/**
 * Admin notice: list missing plugins with Install / Activate links
 */
function {$prefix}_required_plugins_notice(): void {
    if ( ! current_user_can( 'install_plugins' ) ) {
        return;
    }

    \$missing = {$prefix}_get_missing_plugins();
    if ( empty( \$missing ) ) {
        return;
    }

    \$links = [];
    foreach ( \$missing as \$plugin ) {
        \$plugin_path = WP_PLUGIN_DIR . '/' . dirname( \$plugin['file'] );

        if ( file_exists( \$plugin_path ) ) {
            \$url     = wp_nonce_url(
                admin_url( 'plugins.php?action=activate&plugin=' . \$plugin['file'] ),
                'activate-plugin_' . \$plugin['file']
            );
            \$action  = 'Activate';
        } else {
            \$url     = wp_nonce_url(
                admin_url( 'update.php?action=install-plugin&plugin=' . \$plugin['slug'] ),
                'install-plugin_' . \$plugin['slug']
            );
            \$action  = 'Install';
        }

        \$links[] = sprintf(
            '<a href="%s"><strong>%s</strong> (%s)</a>',
            esc_url( \$url ),
            esc_html( \$plugin['name'] ),
            \$action
        );
    }

    // Premium plugin notice
    \$premium_notices = [];
    foreach ( {$prefix}_premium_plugins() as \$basename => \$pname ) {
        \$zip_path = get_template_directory() . '/_plugins/' . \$basename . '.zip';
        if ( file_exists( \$zip_path ) ) {
            \$premium_notices[] = '<strong>' . esc_html( \$pname ) . '</strong>';
        }
    }

    ?>
    <div class="notice notice-warning is-dismissible" style="border-left-color: #f59e0b; padding: 12px 16px;">
        <p style="font-size: 14px;">
            <strong>⚠ {$name} — Required Plugins:</strong>
            Please install and activate the following plugins before activating this theme:
        </p>
        <p><?php echo implode( ' &nbsp;&nbsp;|&nbsp;&nbsp; ', \$links ); ?></p>
        <?php if ( ! empty( \$premium_notices ) ) : ?>
            <p>
                <strong>Premium plugins</strong> are bundled in the theme's <code>/_plugins/</code> folder.
                Please upload them manually via <a href="<?php echo admin_url( 'plugin-install.php' ); ?>">Plugins → Add New → Upload</a>:<br>
                <?php echo implode( ', ', \$premium_notices ); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}
add_action( 'admin_notices', '{$prefix}_required_plugins_notice' );

/**
 * Block theme activation entirely if required plugins are missing
 */
function {$prefix}_check_plugins_on_activation(): void {
    \$missing = {$prefix}_get_missing_plugins();

    if ( empty( \$missing ) ) {
        return;
    }

    \$names = array_column( \$missing, 'name' );

    wp_die(
        '<h2 style="color:#b91c1c;">⚠ Cannot Activate {$name}</h2>' .
        '<p>The following required plugins must be installed and activated first:</p>' .
        '<ul style="list-style:disc;padding-left:20px"><li>' .
            implode( '</li><li>', array_map( 'esc_html', \$names ) ) .
        '</li></ul>' .
        '<p><a href="' . admin_url( 'plugins.php' ) . '" class="button button-primary">→ Go to Plugins</a></p>',
        'Plugin Requirements Not Met',
        [ 'back_link' => true ]
    );
}
add_action( 'after_switch_theme', '{$prefix}_check_plugins_on_activation' );
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
