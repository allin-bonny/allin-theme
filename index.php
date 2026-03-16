<?php
require_once __DIR__ . '/generator.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
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
			$generator->generate();
		} catch (Exception $e) {
			$errors[] = 'Generator error: ' . $e->getMessage();
		}
	}
}

// Plugin data (mirrors generator.php — keep in sync)
$wp_org_plugins = [
	'secure-custom-fields' => 'Secure Custom Fields',
	'elementor' => 'Elementor',
	'wordpress-seo' => 'Yoast SEO',
	'better-search-replace' => 'Better Search Replace',
	'cptui' => 'Custom Post Type UI',
	'query-monitor' => 'Query Monitor',
];

$premium_plugins = [];
$plugin_dir = __DIR__ . '/plugins';
if (is_dir($plugin_dir)) {
	foreach (glob($plugin_dir . '/*.zip') as $p) {
		$basename = basename($p, '.zip');
		$premium_plugins[$basename] = ucwords(str_replace('-', ' ', $basename));
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Allin's WP Starter Theme Generator</title>
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
			max-width: 640px;
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
			margin-bottom: 0.75rem;
		}

		/* Plugin blocks */
		.plugin-group {
			margin-bottom: 1rem;
		}

		.plugin-group-label {
			font-size: 0.7rem;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			margin-bottom: 0.5rem;
		}

		.plugin-group-label.free {
			color: #a78bfa;
		}

		.plugin-group-label.premium {
			color: #fbbf24;
		}

		.plugins-list {
			display: flex;
			flex-wrap: wrap;
			gap: 0.4rem;
		}

		.plugin-badge {
			border-radius: 20px;
			padding: 0.28rem 0.7rem;
			font-size: 0.76rem;
			display: flex;
			align-items: center;
			gap: 0.3rem;
		}

		.plugin-badge.free {
			background: rgba(102, 126, 234, 0.1);
			border: 1px solid rgba(102, 126, 234, 0.3);
			color: #a78bfa;
		}

		.plugin-badge.premium {
			background: rgba(245, 158, 11, 0.1);
			border: 1px solid rgba(245, 158, 11, 0.3);
			color: #fbbf24;
		}

		.plugin-badge small {
			opacity: 0.55;
			font-size: 0.68rem;
		}

		.info-box {
			background: rgba(102, 126, 234, 0.06);
			border: 1px solid rgba(102, 126, 234, 0.2);
			border-radius: 8px;
			padding: 0.75rem 1rem;
			font-size: 0.78rem;
			color: #94a3b8;
			line-height: 1.6;
			margin-top: 0.75rem;
		}

		.info-box strong {
			color: #a78bfa;
		}
	</style>
</head>

<body>
	<div class="card">

		<div class="logo">⚡ StarterTheme</div>
		<p class="tagline">Generate a branded WordPress starter theme in seconds</p>

		<?php if (!empty($errors)): ?>
			<div class="errors">
				<?php foreach ($errors as $e)
					echo '<div>⚠ ' . htmlspecialchars($e) . '</div>'; ?>
			</div>
		<?php endif; ?>

		<form method="POST">

			<p class="section-title">🎨 Theme Identity</p>

			<div class="form-row">
				<div class="form-group">
					<label for="name">Theme Name *</label>
					<input type="text" id="name" name="name" placeholder="TDD Starter Theme"
						value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
				</div>
				<div class="form-group">
					<label for="slug">Theme Slug *</label>
					<input type="text" id="slug" name="slug" placeholder="tdd-starter-theme"
						value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>" required>
					<p class="hint">Lowercase, hyphens only. Used for prefix &amp; text domain.</p>
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
					<input type="text" id="author" name="author" placeholder="TDD"
						value="<?= htmlspecialchars($_POST['author'] ?? 'TDD') ?>">
				</div>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label for="author_uri">Author URI</label>
					<input type="url" id="author_uri" name="author_uri"
						value="<?= htmlspecialchars($_POST['author_uri'] ?? 'https://thedigitaldepartment.ie/') ?>">
				</div>
				<div class="form-group">
					<label for="theme_uri">Theme URI</label>
					<input type="url" id="theme_uri" name="theme_uri"
						value="<?= htmlspecialchars($_POST['theme_uri'] ?? 'https://thedigitaldepartment.ie/') ?>">
				</div>
			</div>

			<hr class="divider">

			<p class="section-title">📦 Plugins Included</p>

			<!-- Free wp.org plugins -->
			<div class="plugin-group">
				<p class="plugin-group-label free">⬇ From WordPress.org — installed via setup page</p>
				<div class="plugins-list">
					<?php foreach ($wp_org_plugins as $slug => $name): ?>
						<span class="plugin-badge free">
							<?= htmlspecialchars($name) ?>
							<small>(wp.org)</small>
						</span>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Premium bundled plugins -->
			<?php if (!empty($premium_plugins)): ?>
				<div class="plugin-group">
					<p class="plugin-group-label premium">⭐ Premium — auto-installed on theme activation</p>
					<div class="plugins-list">
						<?php foreach ($premium_plugins as $basename => $name): ?>
							<span class="plugin-badge premium">
								<?= htmlspecialchars($name) ?>
								<small>(bundled)</small>
							</span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else: ?>
				<div class="plugin-group">
					<p style="color:#4a5568;font-size:0.8rem;">No premium plugins found in /plugins folder.</p>
				</div>
			<?php endif; ?>

			<div class="info-box">
				<strong>How plugins work:</strong><br>
				• <strong>wp.org plugins</strong> — listed on the Theme Setup page after activation. Install with one
				click.<br>
				• <strong>Premium plugins</strong> — automatically installed (not activated) when the theme is
				activated. Activate them manually from the Theme Setup page.
			</div>

			<hr class="divider">

			<button type="submit" name="generate" value="1" class="btn">
				⬇ Generate &amp; Download Theme ZIP
			</button>

		</form>
	</div>

	<script>
		document.getElementById('name').addEventListener('input', function () {
			const slugField = document.getElementById('slug');
			if (slugField.dataset.manual) return;
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