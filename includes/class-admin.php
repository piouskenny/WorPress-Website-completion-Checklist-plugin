<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QA_Checklist_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		register_setting( 'qa_checklist_settings_group', 'qa_checklist_extra_emails' );
	}

	public function register_menu() {
		add_menu_page(
			'QA Checklist',
			'QA Checklist',
			'edit_posts',
			'qa-checklist',
			array( $this, 'render_app' ),
			'dashicons-yes-alt',
			30
		);

		add_submenu_page(
			'qa-checklist',
			'Settings',
			'Settings',
			'manage_options',
			'qa-checklist-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_qa-checklist' !== $hook ) {
			return;
		}

		// Tailwind Play CDN (for development/internal tools)
		wp_enqueue_script( 'tailwind-cdn', 'https://cdn.tailwindcss.com', array(), '3.4.1' );

		wp_enqueue_style( 'qa-checklist-admin-css', QA_CHECKLIST_URL . 'assets/css/admin.css', array(), '1.0.0' );
		wp_enqueue_script( 'qa-checklist-admin-js', QA_CHECKLIST_URL . 'assets/js/admin.js', array(), '1.0.0', true );

		wp_localize_script( 'qa-checklist-admin-js', 'qaChecklistData', array(
			'root'     => esc_url_raw( rest_url() ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'siteName' => get_bloginfo( 'name' ),
		) );
	}

	public function render_app() {
		?>
		<div id="qa-checklist-app" class="p-6">
			<div class="flex justify-between items-center mb-8">
				<h1 class="text-3xl font-bold text-slate-800">Website QA Checklist</h1>
				<button id="new-project-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow-sm transition-colors flex items-center gap-2">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
					</svg>
					New Project
				</button>
			</div>

			<!-- Dashboard/List View -->
			<div id="dashboard-view">
				<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="projects-grid">
					<!-- Projects will be loaded here -->
					<div class="animate-pulse bg-white p-6 rounded-xl shadow-sm border border-slate-100">
						<div class="h-4 bg-slate-200 rounded w-3/4 mb-4"></div>
						<div class="h-3 bg-slate-100 rounded w-1/2 mb-2"></div>
						<div class="h-3 bg-slate-100 rounded w-1/4"></div>
					</div>
				</div>
			</div>

			<!-- Project Detail View (Hidden by default) -->
			<div id="project-detail-view" class="hidden">
				<button id="back-to-dashboard" class="mb-4 text-slate-500 hover:text-slate-700 flex items-center gap-1">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
					</svg>
					Back to Dashboard
				</button>
				<div id="project-content"></div>
			</div>

			<!-- New Project Modal (Hidden by default) -->
			<div id="new-project-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
				<div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
					<h2 class="text-xl font-bold mb-4">Create New Project</h2>
					<form id="new-project-form">
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Project Name</label>
								<input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Assign to</label>
								<select name="assigned_user_id" id="user-select" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none"></select>
							</div>
							<div class="space-y-2 pt-2 border-t border-slate-100">
								<p class="text-sm font-medium text-slate-700">Optional Sections</p>
								<label class="flex items-center gap-2 cursor-pointer">
									<input type="checkbox" name="has_woocommerce" class="w-4 h-4 text-indigo-600 rounded">
									<span class="text-sm text-slate-600">WooCommerce</span>
								</label>
								<label class="flex items-center gap-2 cursor-pointer">
									<input type="checkbox" name="has_forms" class="w-4 h-4 text-indigo-600 rounded">
									<span class="text-sm text-slate-600">Forms</span>
								</label>
								<label class="flex items-center gap-2 cursor-pointer">
									<input type="checkbox" name="has_seo" class="w-4 h-4 text-indigo-600 rounded">
									<span class="text-sm text-slate-600">SEO</span>
								</label>
							</div>
						</div>
						<div class="flex gap-3 mt-6">
							<button type="button" id="close-modal" class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors">Cancel</button>
							<button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Create Project</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>QA Checklist Settings</h1>
			<p>Configure additional settings for the QA Checklist.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'qa_checklist_settings_group' ); ?>
				<?php do_settings_sections( 'qa_checklist_settings_group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Additional Notification Emails</th>
						<td>
							<input type="text" name="qa_checklist_extra_emails" value="<?php echo esc_attr( get_option('qa_checklist_extra_emails') ); ?>" class="regular-text" style="width: 100%; max-width: 500px;" placeholder="e.g. email1@test.com, email2@test.com" />
							<p class="description">Enter comma-separated email addresses to receive completion notifications alongside the default manager email (kehindeu13@gmail.com).</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
