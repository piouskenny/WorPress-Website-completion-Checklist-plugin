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
		register_setting( 'qa_checklist_settings_group', 'qa_checklist_original_address' );
		register_setting( 'qa_checklist_settings_group', 'qa_checklist_site_url' );
		register_setting( 'qa_checklist_settings_group', 'qa_checklist_expected_currency' );
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

		// Fonts and Tailwind
		wp_enqueue_style( 'google-font-outfit', 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap', array(), null );
		wp_enqueue_script( 'tailwind-cdn', 'https://cdn.tailwindcss.com', array(), '3.4.1' );

		wp_enqueue_style( 'qa-checklist-admin-css', QA_CHECKLIST_URL . 'assets/css/admin.css', array(), '1.1.0' );
		wp_enqueue_script( 'qa-checklist-admin-js', QA_CHECKLIST_URL . 'assets/js/admin.js', array(), '1.1.0', true );

		wp_localize_script( 'qa-checklist-admin-js', 'qaChecklistData', array(
			'root'     => esc_url_raw( rest_url() ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'siteName' => get_bloginfo( 'name' ),
		) );
	}

	public function render_app() {
		?>
		<div id="qa-checklist-app" class="font-['Outfit',sans-serif] text-slate-900 leading-relaxed overflow-x-hidden">
			<div class="max-w-7xl mx-auto p-6 lg:p-12 min-h-screen">
				<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-6 animate-fade-in">
					<div>
						<h1 class="text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight">Website QA Checklist</h1>
						<p class="text-slate-500 mt-2 font-medium italic">Quality Assurance Pipeline & Monitoring</p>
					</div>
					<button id="new-project-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3.5 rounded-2xl shadow-xl shadow-indigo-600/20 transition-all active:scale-95 flex items-center gap-2 font-bold tracking-wide">
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
			<div id="new-project-modal" class="hidden fixed inset-0 bg-indigo-950/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 transition-all duration-300">
				<div class="glass-card rounded-[2rem] shadow-2xl w-full max-w-lg p-10 border-0 animate-fade-in">
					<div class="flex justify-between items-center mb-8">
						<h2 class="text-3xl font-bold text-indigo-950 tracking-tight">New Pipeline</h2>
						<button id="close-modal" class="text-indigo-900/40 hover:text-indigo-900 transition-colors">
							<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
							</svg>
						</button>
					</div>
					<form id="new-project-form">
						<div class="space-y-6">
							<div>
								<label class="block text-[10px] font-black text-indigo-900/40 uppercase tracking-[0.2em] mb-2 px-1">Project Identifier</label>
								<input type="text" name="name" required placeholder="e.g. Acme Corp Website" class="w-full px-5 py-4 bg-white/50 border-indigo-100 rounded-2xl focus:ring-4 focus:ring-indigo-100 focus:border-indigo-500 outline-none transition-all font-medium text-indigo-950 placeholder:text-indigo-200">
							</div>
							<div>
								<label class="block text-[10px] font-black text-indigo-900/40 uppercase tracking-[0.2em] mb-2 px-1">Assigned Analyst</label>
								<select name="assigned_user_id" id="user-select" class="w-full px-5 py-4 bg-white/50 border-indigo-100 rounded-2xl outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-500 transition-all font-medium text-indigo-950"></select>
							</div>
							<div class="space-y-3 pt-4 border-t border-indigo-900/5">
								<p class="text-[10px] font-black text-indigo-900/40 uppercase tracking-[0.2em] mb-4 px-1">Optional Scope</p>
								<div class="grid grid-cols-1 gap-3">
									<label class="flex items-center gap-4 bg-white/30 p-4 rounded-2xl border border-transparent hover:border-indigo-100 peer-checked:bg-indigo-50 transition-all cursor-pointer group">
										<input type="checkbox" name="has_woocommerce" class="w-5 h-5 text-indigo-600 rounded-lg border-indigo-200 focus:ring-indigo-500">
										<span class="text-sm font-bold text-indigo-900/70 group-hover:text-indigo-900">WooCommerce Ecosystem</span>
									</label>
									<label class="flex items-center gap-4 bg-white/30 p-4 rounded-2xl border border-transparent hover:border-indigo-100 transition-all cursor-pointer group">
										<input type="checkbox" name="has_forms" class="w-5 h-5 text-indigo-600 rounded-lg border-indigo-200 focus:ring-indigo-500">
										<span class="text-sm font-bold text-indigo-900/70 group-hover:text-indigo-900">Interactive Form Audits</span>
									</label>
									<label class="flex items-center gap-4 bg-white/30 p-4 rounded-2xl border border-transparent hover:border-indigo-100 transition-all cursor-pointer group">
										<input type="checkbox" name="has_seo" class="w-5 h-5 text-indigo-600 rounded-lg border-indigo-200 focus:ring-indigo-500">
										<span class="text-sm font-bold text-indigo-900/70 group-hover:text-indigo-900">Search Engine Optimization</span>
									</label>
								</div>
							</div>
						</div>
						<div class="mt-10">
							<button type="submit" class="w-full py-5 bg-indigo-600 text-white rounded-[1.5rem] font-black uppercase tracking-widest hover:bg-indigo-700 shadow-2xl shadow-indigo-600/30 transition-all active:scale-95">Initialize Project</button>
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
						<th scope="row">Target Website URL</th>
						<td>
							<input type="url" name="qa_checklist_site_url" value="<?php echo esc_url( get_option('qa_checklist_site_url', get_home_url()) ); ?>" class="regular-text" style="width: 100%; max-width: 500px;" />
							<p class="description">The URL of the website to be audited. Defaults to the current site URL.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Expected Store Currency (WC)</th>
						<td>
							<input type="text" name="qa_checklist_expected_currency" value="<?php echo esc_attr( get_option('qa_checklist_expected_currency', 'USD') ); ?>" class="regular-text" style="width: 100%; max-width: 100px;" placeholder="e.g. USD" />
							<p class="description">Enter the 3-letter currency code (e.g., USD, NGN, GBP) to validate against the WooCommerce settings.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Additional Notification Emails</th>
						<td>
							<input type="text" name="qa_checklist_extra_emails" value="<?php echo esc_attr( get_option('qa_checklist_extra_emails') ); ?>" class="regular-text" style="width: 100%; max-width: 500px;" placeholder="e.g. email1@test.com, email2@test.com" />
							<p class="description">Enter comma-separated email addresses to receive completion notifications alongside the default manager email (kehindeu13@gmail.com).</p>
						</td>
					<tr valign="top">
						<th scope="row">Original Company Address</th>
						<td>
							<textarea name="qa_checklist_original_address" rows="3" class="regular-text" style="width: 100%; max-width: 500px;" placeholder="e.g. 123 Business Rd, Suite 100, City, Country"><?php echo esc_textarea( get_option('qa_checklist_original_address') ); ?></textarea>
							<p class="description">Enter the official company address. The automation engine will verify if this address is present on the website and correctly linked in the map embed.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>

		<div class="wrap" style="margin-top: 40px; border-top: 1px solid #ccd0d4; padding-top: 20px;">
			<h2>Search and Replace (Elementor Supported)</h2>
			<p>Find and replace text across your entire site, including Elementor widgets and layouts. Changes will automatically clear cache plugins.</p>
			
			<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px; max-width: 600px;">
				<div id="sr-search-step">
					<label style="font-weight: 600; display: block; margin-bottom: 8px;">Search Term:</label>
					<input type="text" id="sr-search-term" class="regular-text" style="width: 100%; margin-bottom: 12px;" placeholder="Word to find..." />
					<button type="button" id="sr-find-btn" class="button button-secondary">Find Occurrences</button>
					<span id="sr-search-loading" style="display:none; margin-left:10px;">Searching...</span>
				</div>
				
				<div id="sr-replace-step" style="display:none; margin-top: 20px; padding-top: 20px; border-top: 1px dashed #ccd0d4;">
					<p style="color: #007017; font-weight: bold;" id="sr-result-text"></p>
					
					<label style="font-weight: 600; display: block; margin-bottom: 8px;">Replace With (Leave blank for empty space):</label>
					<input type="text" id="sr-replace-term" class="regular-text" style="width: 100%; margin-bottom: 12px;" placeholder="Replacement text..." />
					<button type="button" id="sr-replace-btn" class="button button-primary">Save Changes & Clear Cache</button>
					<span id="sr-replace-loading" style="display:none; margin-left:10px;">Replacing & caching...</span>
				</div>
			</div>
		</div>

		<!-- Toast Notification -->
		<div id="qa-toast" style="position: fixed; bottom: 20px; right: -300px; background: #1f2937; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); transition: right 0.4s ease-out; z-index: 100000; display: flex; align-items: center; gap: 12px; font-family: sans-serif;">
			<svg xmlns="http://www.w3.org/2000/svg" style="width:24px; height:24px; color:#10b981;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
			</svg>
			<span id="qa-toast-msg" style="font-weight: 500;">Success</span>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const root = '<?php echo esc_url_raw( rest_url( "qa-checklist/v1" ) ); ?>';
			const nonce = '<?php echo wp_create_nonce( "wp_rest" ); ?>';
			
			const searchBtn = document.getElementById('sr-find-btn');
			const replaceBtn = document.getElementById('sr-replace-btn');
			const step2 = document.getElementById('sr-replace-step');
			const toast = document.getElementById('qa-toast');
			const toastMsg = document.getElementById('qa-toast-msg');
			let currentSearchTerm = '';

			function showToast(message) {
				toastMsg.textContent = message;
				toast.style.right = '20px';
				setTimeout(() => { toast.style.right = '-300px'; }, 4000);
			}

			searchBtn.addEventListener('click', async () => {
				const term = document.getElementById('sr-search-term').value.trim();
				if(!term) return alert('Enter a search term');
				
				currentSearchTerm = term;
				document.getElementById('sr-search-loading').style.display = 'inline';
				searchBtn.disabled = true;

				try {
					const res = await fetch(`${root}/search?term=${encodeURIComponent(term)}`, {
						headers: { 'X-WP-Nonce': nonce }
					});
					const data = await res.json();
					
					document.getElementById('sr-result-text').textContent = `Found ${data.count} occurrences of "${term}".`;
					step2.style.display = 'block';
				} catch (e) {
					alert('Search failed.');
				} finally {
					document.getElementById('sr-search-loading').style.display = 'none';
					searchBtn.disabled = false;
				}
			});

			replaceBtn.addEventListener('click', async () => {
				const replaceTerm = document.getElementById('sr-replace-term').value;
				
				document.getElementById('sr-replace-loading').style.display = 'inline';
				replaceBtn.disabled = true;

				try {
					const res = await fetch(`${root}/replace`, {
						method: 'POST',
						headers: { 
							'X-WP-Nonce': nonce,
							'Content-Type': 'application/json' 
						},
						body: JSON.stringify({
							search_term: currentSearchTerm,
							replace_term: replaceTerm
						})
					});
					const data = await res.json();
					
					if(data.success) {
						showToast(data.message);
						step2.style.display = 'none';
						document.getElementById('sr-search-term').value = '';
						document.getElementById('sr-replace-term').value = '';
					} else {
						alert('Error: ' + data.message);
					}
				} catch (e) {
					alert('Replacement failed.');
				} finally {
					document.getElementById('sr-replace-loading').style.display = 'none';
					replaceBtn.disabled = false;
				}
			});
		});
		</script>
		<?php
	}
}
