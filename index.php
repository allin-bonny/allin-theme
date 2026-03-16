<?php
require_once __DIR__ . '/generator.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {

	// Basic validation
	if (empty($_POST['name']))
		$errors[] = 'Theme name is required.';
	if (empty($_POST['slug']))
		$errors[] = 'Theme slug is required.';

	if (empty($errors)) {
		try {
			$generator = new StarterKitGenerator(
				__DIR__ . '/boilerplate',
				__DIR__ . '/plugins'
			);
			$generator->set_theme($_POST);
			$generator->generate(); // Streams ZIP + exits
		} catch (Exception $e) {
			$errors[] = 'Generator error: ' . $e->getMessage();
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>🚀 Bonny's WP Starter Kit Generator</title>
	<style>
		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
			background: #0f0f1a;
			color: #e2e8f0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 2rem;
		}

		.card {
			background: #1a1a2e;
			border: 1px solid #2d2d4e;
			border-radius: 16px;
			padding: 2.5rem;
			width: 100%;
			max-width: 600px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
		}

		.logo {
			font-size: 2rem;
			font-weight: 800;
			background: linear-gradient(135deg, #667eea, #764ba2);
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			margin-bottom: 0.25rem;
		}

		.tagline {
			color: #718096;
			font-size: 0.9rem;
			margin-bottom: 2rem;
		}

		.form-row {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 1rem;
		}

		.form-group {
			margin-bottom: 1.25rem;
		}

		label {
			display: block;
			font-size: 0.8rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			color: #a0aec0;
			margin-bottom: 0.4rem;
		}

		input,
		textarea {
			width: 100%;
			background: #0f0f1a;
			border: 1px solid #2d2d4e;
			border-radius: 8px;
			padding: 0.65rem 0.9rem;
			color: #e2e8f0;
			font-size: 0.95rem;
			transition: border-color 0.2s;
		}

		input:focus,
		textarea:focus {
			outline: none;
			border-color: #667eea;
		}

		textarea {
			resize: vertical;
			min-height: 80px;
		}

		.hint {
			font-size: 0.75rem;
			color: #4a5568;
			margin-top: 0.3rem;
		}

		.btn {
			width: 100%;
			padding: 0.9rem;
			background: linear-gradient(135deg, #667eea, #764ba2);
			color: white;
			font-size: 1rem;
			font-weight: 700;
			border: none;
			border-radius: 10px;
			cursor: pointer;
			margin-top: 0.5rem;
			letter-spacing: 0.03em;
			transition: opacity 0.2s, transform 0.1s;
		}

		.btn:hover {
			opacity: 0.9;
		}

		.btn:active {
			transform: scale(0.99);
		}

		.errors {
			background: rgba(245, 101, 101, 0.1);
			border: 1px solid rgba(245, 101, 101, 0.3);
			border-radius: 8px;
			padding: 0.9rem 1rem;
			margin-bottom: 1.5rem;
			color: #fc8181;
			font-size: 0.9rem;
		}

		.divider {
			border: none;
			border-top: 1px solid #2d2d4e;
			margin: 1.5rem 0;
		}

		.section-title {
			font-size: 0.75rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #667eea;
			margin-bottom: 1rem;
		}

		.plugins-list {
			display: flex;
			flex-wrap: wrap;
			gap: 0.5rem;
			margin-bottom: 1rem;
		}

		.plugin-badge {
			background: rgba(102, 126, 234, 0.1);
			border: 1px solid rgba(102, 126, 234, 0.3);
			border-radius: 20px;
			padding: 0.3rem 0.75rem;
			font-size: 0.78rem;
			color: #a78bfa;
		}

		.plugin-badge.premium {
			background: rgba(245, 158, 11, 0.1);
			border-color: rgba(245, 158, 11, 0.3);
			color: #fbbf24;
		}

		.plugin-badge small {
			opacity: 0.6;
			font-size: 0.7rem;
		}
	</style>
</head>

<body>
	<div class="card">

		<div class="logo">⚡ StarterKit</div>
		<p class="tagline">Generate a branded WordPress starter kit in seconds</p>

		<?php if (!empty($errors)): ?>
			<div class="errors">
				<?php foreach ($errors as $e)
					echo '<div>⚠ ' . esc_html($e) . '</div>'; ?>
			</div>
		<?php endif; ?>

		<form method="POST">

			<p class="section-title">🎨 Theme Identity</p>

			<div class="form-row">
				<div class="form-group">
					<label for="name">Theme Name *</label>
					<input type="text" id="name" name="name" placeholder="Bonny Starter"
						value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
				</div>
				<div class="form-group">
					<label for="slug">Theme Slug *</label>
					<input type="text" id="slug" name="slug" placeholder="bonny-starter"
						value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>" required>
					<p class="hint">Lowercase, hyphens only. Used for prefixes &amp; text domain.</p>
				</div>
			</div>

			<div class="form-group">
				<label for="description">Description</label>
				<textarea id="description" name="description"
					placeholder="A clean, fast WordPress starter theme."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label for="version">Version</label>
					<input type="text" id="version" name="version" placeholder="1.0.0"
						value="<?= htmlspecialchars($_POST['version'] ?? '1.0.0') ?>">
				</div>
				<div class="form-group">
					<label for="author">Author</label>
					<input type="text" id="author" name="author" placeholder="Bonny Elangbam"
						value="<?= htmlspecialchars($_POST['author'] ?? '') ?>">
				</div>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label for="author_uri">Author URI</label>
					<input type="url" id="author_uri" name="author_uri" placeholder="https://yourdomain.com"
						value="<?= htmlspecialchars($_POST['author_uri'] ?? '') ?>">
				</div>
				<div class="form-group">
					<label for="theme_uri">Theme URI</label>
					<input type="url" id="theme_uri" name="theme_uri" placeholder="https://yourdomain.com/theme"
						value="<?= htmlspecialchars($_POST['theme_uri'] ?? '') ?>">
				</div>
			</div>

			<hr class="divider">

			<p class="section-title">📦 Bundled Plugins</p>
			<div class="plugins-list">

				<?php
				// Free plugins from WordPress.org
				$wp_org = [
					'secure-custom-fields' => 'Secure Custom Fields',
					'wordpress-seo' => 'Yoast SEO',
					'better-search-replace' => 'Better Search Replace',
					'query-monitor' => 'Query Monitor',
					'cptui' => 'Custom Post Type UI',
				];
				foreach ($wp_org as $slug => $name) {
					echo '<span class="plugin-badge">⬇ ' . $name . ' <small>(wp.org)</small></span>';
				}

				// Premium plugins from local /plugins folder
				$plugin_dir = __DIR__ . '/plugins';
				if (is_dir($plugin_dir)) {
					foreach (glob($plugin_dir . '/*.zip') as $p) {
						$name = ucwords(str_replace(['-', '.zip'], [' ', ''], basename($p)));
						echo '<span class="plugin-badge premium">⭐ ' . $name . ' <small>(premium)</small></span>';
					}
				}
				?>

			</div>


			<button type="submit" name="generate" value="1" class="btn">
				⬇ Generate &amp; Download Theme
			</button>

		</form>
	</div>

	<script>
		// Auto-generate slug from theme name
		document.getElementById('name').addEventListener('input', function () {
			const slugField = document.getElementById('slug');
			if (slugField.dataset.manual) return; // don't override manual input
			slugField.value = this.value
				.toLowerCase()
				.trim()
				.replace(/[^a-z0-9\s-]/g, '')
				.replace(/\s+/g, '-');
		});
		document.getElementById('slug').addEventListener('input', function () {
			this.dataset.manual = 'true';
		});
	</script>

</body>

</html>