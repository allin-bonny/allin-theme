<?php
/**
 * Bonny's WordPress Starter Kit Generator
 * Core ZIP builder engine
 */

class StarterKitGenerator
{

    private array $theme = [];
    private string $boilerplate_dir;
    private string $plugins_dir;
    private array $replacements = [];

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
            'prefix' => $this->slug_to_prefix($slug),   // e.g. "my_theme"
            'const' => strtoupper($this->slug_to_prefix($slug)), // MY_THEME
            'author' => sanitize_text($data['author'] ?? ''),
            'author_uri' => sanitize_text($data['author_uri'] ?? ''),
            'uri' => sanitize_text($data['theme_uri'] ?? ''),
            'description' => sanitize_text($data['description'] ?? ''),
            'version' => sanitize_text($data['version'] ?? '1.0.0'),
        ];

        // Build all find→replace pairs (mirrors Automattic's approach)
        $this->replacements = [
            // Style.css header fields
            'Theme Name: _sk' => 'Theme Name: ' . $this->theme['name'],
            'Author: _sk Author' => 'Author: ' . $this->theme['author'],
            'Author URI: https://example.com' => 'Author URI: ' . $this->theme['author_uri'],
            'Theme URI: https://example.com' => 'Theme URI: ' . $this->theme['uri'],
            'Description: _sk Description' => 'Description: ' . $this->theme['description'],
            'Version: 1.0.0' => 'Version: ' . $this->theme['version'],
            'Text Domain: _sk' => 'Text Domain: ' . $this->theme['slug'],

            // PHP function/class prefixes
            '_sk_' => $this->theme['prefix'] . '_',        // functions
            "'_sk'" => "'" . $this->theme['slug'] . "'",    // text domain strings
            '_SK_' => $this->theme['const'] . '_',         // constants
            'class _Sk' => 'class ' . $this->slug_to_class($slug), // Class names
            '_sk-' => $this->theme['slug'] . '-',          // CSS handles & enqueue slugs
            '"_sk"' => '"' . $this->theme['slug'] . '"',    // double-quoted slug refs

            // ACF/SCF options page & field group keys
            '"_sk_options"' => '"' . $this->theme['prefix'] . '_options"',
            "'_sk_options'" => "'" . $this->theme['prefix'] . "_options'",
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

        // 1. Add the processed theme files
        $this->add_directory($zip, $this->boilerplate_dir, $this->theme['slug']);

        // 2. Bundle pre-selected plugins
        $this->add_plugins($zip);

        // 3. Add a mu-plugin that auto-activates bundled plugins on first load
        $this->add_auto_activator($zip);

        $zip->close();

        // Stream ZIP to browser
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
            // Path inside ZIP: slug/inc/acf-options.php etc.
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
        // Skip binary files (images, fonts, etc.)
        $text_extensions = ['php', 'css', 'js', 'json', 'txt', 'md', 'html', 'xml', 'po', 'pot'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $text_extensions, true)) {
            return $contents;
        }

        // Run all string replacements
        foreach ($this->replacements as $find => $replace) {
            $contents = str_replace($find, $replace, $contents);
        }

        // Rename the theme slug in JSON field group keys (ACF/SCF)
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

        // Update the menu_slug in options pages, and location rules that reference theme
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = str_replace('_sk', $this->theme['prefix'], $value);
                $value = str_replace('_sk-', $this->theme['slug'] . '-', $value);
            }
        });

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Add bundled plugin ZIPs into a _plugins/ folder inside the kit
     */
    private function add_plugins(ZipArchive $zip): void
    {
        if (!is_dir($this->plugins_dir)) {
            return;
        }

        $plugin_files = glob($this->plugins_dir . '/*.zip');

        foreach ($plugin_files as $plugin_zip) {
            $basename = basename($plugin_zip);
            // Store as: {theme-slug}/_plugins/plugin-name.zip
            $zip->addFile($plugin_zip, $this->theme['slug'] . '/_plugins/' . $basename);
        }
    }

    /**
     * Inject a mu-plugin that auto-activates bundled plugins on first WP load
     */
    private function add_auto_activator(ZipArchive $zip): void
    {
        $slug = $this->theme['slug'];
        $prefix = $this->theme['prefix'];

        $mu_plugin = <<<PHP
<?php
/**
 * Auto Plugin Activator — generated by {$this->theme['name']} Starter Kit
 * Place this file in /wp-content/mu-plugins/
 * It runs once, installs & activates bundled plugins, then self-deletes.
 */

add_action( 'admin_init', '{$prefix}_auto_activate_plugins' );

function {$prefix}_auto_activate_plugins() {
    \$flag = get_option( '{$prefix}_plugins_activated' );
    if ( \$flag ) return;

    \$plugins_dir = get_template_directory() . '/_plugins/';
    if ( ! is_dir( \$plugins_dir ) ) return;

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    \$zip_files = glob( \$plugins_dir . '*.zip' );

    foreach ( \$zip_files as \$zip_file ) {
        \$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
        \$result   = \$upgrader->install( \$zip_file );

        if ( ! is_wp_error( \$result ) ) {
            \$plugin_data = get_plugins( '/' . \$upgrader->result['destination_name'] );
            if ( ! empty( \$plugin_data ) ) {
                \$plugin_file = \$upgrader->result['destination_name'] . '/' . array_key_first( \$plugin_data );
                activate_plugin( \$plugin_file );
            }
        }
    }

    update_option( '{$prefix}_plugins_activated', true );
}
PHP;

        $zip->addFromString(
            $slug . '/_mu-plugin/' . $prefix . '-auto-activate.php',
            $mu_plugin
        );
    }

    /**
     * Stream the ZIP file to the browser as a download
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

        // Cleanup temp file
        @unlink($zip_path);
        exit;
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function sanitize_slug(string $slug): string
    {
        return strtolower(preg_replace('/[^a-z0-9-]/', '-', $slug));
    }

    private function slug_to_prefix(string $slug): string
    {
        return str_replace('-', '_', $slug);  // my-theme → my_theme
    }

    private function slug_to_class(string $slug): string
    {
        return str_replace(' ', '_', ucwords(str_replace('-', ' ', $slug))); // my-theme → My_Theme
    }
}

function sanitize_text(string $str): string
{
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}
