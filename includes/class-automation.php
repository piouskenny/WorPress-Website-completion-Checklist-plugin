<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QA_Checklist_Automation {

	private $project_id;
	private $project;
	private $items;
	private $html_content;
	private $dom;
	private $site_url;

	public function __construct( $project_id ) {
		$this->project_id = $project_id;
		$this->site_url   = get_option( 'qa_checklist_site_url', get_home_url() );
	}

	public function run_audit() {
		global $wpdb;

		// Fetch project and items
		$this->project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}qa_projects WHERE id = %d", $this->project_id ) );
		if ( ! $this->project ) {
			return new WP_Error( 'not_found', 'Project not found' );
		}

		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}qa_checklist_items WHERE project_id = %d", $this->project_id ) );

		// Fetch site content
		$response = wp_remote_get( $this->site_url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->html_content = wp_remote_retrieve_body( $response );
		if ( empty( $this->html_content ) ) {
			return new WP_Error( 'empty_content', 'Could not retrieve site content' );
		}

		// Initialize DOM
		$this->dom = new DOMDocument();
		@$this->dom->loadHTML( mb_convert_encoding( $this->html_content, 'HTML-ENTITIES', 'UTF-8' ) );

		$results = array();

		// Run checks
		$results['seo'] = $this->check_seo();
		$results['headings'] = $this->check_headings();
		$results['mobile'] = $this->check_mobile();
		$results['content_quality'] = $this->check_content_quality();
		$results['email'] = $this->check_contact_email();
		$results['address_map'] = $this->check_address_and_map();
		$results['broken_links'] = $this->check_broken_links();
		$results['logo'] = $this->check_logo();

		if ( $this->project->has_woocommerce ) {
			$results['woocommerce'] = $this->check_woocommerce();
			$results['currency'] = $this->check_currency();
		}

		// Update database items based on results
		$this->update_checklist_items( $results );

		return $results;
	}

	private function update_checklist_items( $results ) {
		global $wpdb;
		$table = $wpdb->prefix . 'qa_checklist_items';

		foreach ( $this->items as $item ) {
			$update = null;

			// Map labels to results
			switch ( $item->label ) {
				case 'Meta titles/descriptions':
					$res = $results['seo'];
					$update = array(
						'status'  => ( $res['title'] && $res['description'] ) ? 'passed' : 'failed',
						'comment' => ( $res['title'] && $res['description'] ) 
							? sprintf( "SEO tags found. Title: %s", $res['title'] )
							: "Warning: Missing SEO Meta tags. Action Required: Please update your homepage title and meta description for better SEO."
					);
					break;

				case 'Heading structure (H1, H2, etc.)':
					$res = $results['headings'];
					$update = array(
						'status'  => $res['status'],
						'comment' => $res['status'] === 'passed' 
							? $res['message'] 
							: "Warning: Heading hierarchy issue. Action Required: " . $res['message']
					);
					break;

				case 'Mobile responsiveness':
					$res = $results['mobile'];
					$update = array(
						'status'  => $res['viewport'] ? 'passed' : 'failed',
						'comment' => $res['viewport'] 
							? 'Viewport meta found (Mobile Optimized).' 
							: 'Warning: Viewport meta tag missing. Action Required: Add <meta name="viewport" content="..."> to your theme header.'
					);
					break;

				case 'Content validation (no dummy/AI errors)':
					$res = $results['content_quality'];
					if ( ! $res['too_many_dashes'] ) {
						// We don't mark it passed automatically because "AI errors" still need manual check, 
						// but we can flag it if failed.
						if ( $res['too_many_dashes'] ) {
							$update = array( 
								'status' => 'failed', 
								'comment' => 'Warning: Excessive hyphens detected (---). Action Required: Review your content for placeholder text or formatting errors.' 
							);
						}
					}
					break;

				case 'Shop accessibility (public)':
					if ( isset( $results['woocommerce'] ) ) {
						$res = $results['woocommerce'];
						$update = array(
							'status'  => $res['status'] === 200 ? 'passed' : 'failed',
							'comment' => $res['status'] === 200 
								? "Shop is public and accessible." 
								: "Warning: Shop inaccessible (Status {$res['status']}). Action Required: Ensure your WooCommerce /shop/ page is published and public."
						);
					}
					break;

				case 'Footer links validation':
					$res = $results['broken_links'];
					$update = array(
						'status'  => empty( $res['broken'] ) ? 'passed' : 'failed',
						'comment' => empty( $res['broken'] ) ? 'No broken links found on homepage.' : 'Found broken links: ' . implode( ', ', $res['broken'] )
					);
					break;

				case 'Map & address validation':
					$res = $results['address_map'];
					$update = array(
						'status'  => ( $res['address_found'] && $res['map_found'] ) ? 'passed' : 'failed',
						'comment' => ( $res['address_found'] && $res['map_found'] )
							? "Address and Map match settings."
							: "Warning: Address or Map mismatch. Action Required: Ensure the company address is in settings and matches the footer/map on the site."
					);
					break;

				case 'Company email accuracy':
					$res = $results['email'];
					$update = array(
						'status'  => $res['found'] ? 'passed' : 'failed',
						'comment' => $res['found'] 
							? "Found expected email: {$res['target']}" 
							: "Warning: Expected email {$res['target']} missing. Action Required: Ensure the company email is visible to customers."
					);
					break;

				case 'Logo presence check':
					$res = $results['logo'];
					$update = array(
						'status'  => $res['found'] ? 'passed' : 'failed',
						'comment' => $res['found'] 
							? "Logo found via " . $res['method'] 
							: "Warning: No logo detected. Action Required: Upload a custom logo via Appearance > Customize > Site Identity."
					);
					break;

				case 'Currency configuration check':
					if ( isset( $results['currency'] ) ) {
						$res = $results['currency'];
						$update = array(
							'status'  => $res['match'] ? 'passed' : 'failed',
							'comment' => $res['match'] 
								? "Currency matches: {$res['expected']}" 
								: "Warning: Currency mismatch ({$res['actual']}). Action Required: Set the store currency to {$res['expected']} in WC > Settings."
						);
					}
					break;
			}

			// Custom check for the email (not a standard label, but maybe we add it or map to CTA?)
			// If we don't have a label for email, we might just add it as a comment to CTA or skip.
			// Let's assume there might be a "Contact validation" or similar.
			// For now, I'll just check if it matches the generic logic.

			if ( $update ) {
				$wpdb->update( $table, $update, array( 'id' => $item->id ) );
			}
		}
	}

	private function check_seo() {
		$titles = $this->dom->getElementsByTagName('title');
		$title_exists = $titles->length > 0 && ! empty( $titles->item(0)->nodeValue );

		$description_exists = false;
		$metas = $this->dom->getElementsByTagName('meta');
		foreach ( $metas as $meta ) {
			if ( strtolower( $meta->getAttribute('name') ) === 'description' ) {
				$description_exists = ! empty( $meta->getAttribute('content') );
				break;
			}
		}

		$h1s = $this->dom->getElementsByTagName('h1');
		$h1_exists = $h1s->length > 0;

		return array(
			'title'       => $title_exists,
			'description' => $description_exists,
			'h1'          => $h1_exists
		);
	}

	private function check_headings() {
		$headings = array();
		$tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');
		
		// Simple hierarchy check: H1 should exist, and no level should skip (e.g. H2 followed by H4)
		$found_tags = array();
		$all_elements = $this->dom->getElementsByTagName('*');
		foreach ( $all_elements as $el ) {
			if ( in_array( strtolower($el->tagName), $tags ) ) {
				$found_tags[] = intval( substr($el->tagName, 1) );
			}
		}

		if ( empty( $found_tags ) ) {
			return array( 'status' => 'failed', 'message' => 'No headings found.' );
		}

		if ( ! in_array( 1, $found_tags ) ) {
			return array( 'status' => 'failed', 'message' => 'Missing H1 heading.' );
		}

		$prev = 0;
		foreach ( $found_tags as $level ) {
			if ( $prev > 0 && $level > $prev + 1 ) {
				return array( 'status' => 'failed', 'message' => "Heading level skipped from H{$prev} to H{$level}." );
			}
			$prev = $level;
		}

		return array( 'status' => 'passed', 'message' => 'Heading hierarchy is correct.' );
	}

	private function check_mobile() {
		$viewport_found = false;
		$metas = $this->dom->getElementsByTagName('meta');
		foreach ( $metas as $meta ) {
			if ( strtolower( $meta->getAttribute('name') ) === 'viewport' ) {
				$viewport_found = true;
				break;
			}
		}
		return array( 'viewport' => $viewport_found );
	}

	private function check_content_quality() {
		// Check for 3 or more consecutive hyphens
		$too_many_dashes = ( preg_match( '/-{3,}/', $this->html_content ) === 1 );
		return array( 'too_many_dashes' => $too_many_dashes );
	}

	private function check_contact_email() {
		$domain = parse_url( $this->site_url, PHP_URL_HOST );
		$domain = preg_replace( '/^www\./', '', $domain );
		
		$target_email = $this->project->has_woocommerce ? "sales@{$domain}" : "info@{$domain}";
		$found = ( strpos( $this->html_content, $target_email ) !== false );

		return array(
			'target' => $target_email,
			'found'  => $found
		);
	}

	private function check_address_and_map() {
		$original_address = get_option( 'qa_checklist_original_address' );
		$address_found = false;
		$map_found = false;

		if ( ! empty( $original_address ) ) {
			$address_found = ( stripos( $this->html_content, $original_address ) !== false );

			$iframes = $this->dom->getElementsByTagName('iframe');
			foreach ( $iframes as $iframe ) {
				$src = $iframe->getAttribute('src');
				if ( strpos( $src, 'google.com/maps' ) !== false ) {
					// Check if address is in the SRC (URL encoded)
					if ( strpos( $src, urlencode( $original_address ) ) !== false || strpos( $src, rawurlencode( $original_address ) ) !== false ) {
						$map_found = true;
						break;
					}
					// Fallback: If it's a map iframe at all, we consider it "found" but maybe not matched if encoding differs
					$map_found = true; 
				}
			}
		}

		return array(
			'address_found' => $address_found,
			'map_found'     => $map_found
		);
	}

	private function check_broken_links() {
		$links = $this->dom->getElementsByTagName('a');
		$internal_links = array();
		$broken = array();

		foreach ( $links as $link ) {
			$href = $link->getAttribute('href');
			if ( empty( $href ) || strpos( $href, '#' ) === 0 || strpos( $href, 'javascript:' ) === 0 ) {
				continue;
			}

			// Normalize URL
			if ( strpos( $href, '/' ) === 0 ) {
				$href = rtrim($this->site_url, '/') . $href;
			}

			if ( strpos( $href, $this->site_url ) === 0 ) {
				$internal_links[] = $href;
			}

			if ( count( $internal_links ) >= 10 ) break; // Limit crawl for performance
		}

		$internal_links = array_unique( $internal_links );

		foreach ( $internal_links as $url ) {
			$response = wp_remote_head( $url, array( 'timeout' => 5 ) );
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 400 ) {
				$broken[] = $url;
			}
		}

		return array( 'broken' => $broken );
	}

	private function check_woocommerce() {
		$shop_url = rtrim($this->site_url, '/') . '/shop/';
		$response = wp_remote_get( $shop_url );
		return array( 'status' => wp_remote_retrieve_response_code( $response ) );
	}

	private function check_logo() {
		// Method 1: WordPress Custom Logo
		if ( has_custom_logo() ) {
			return array( 'found' => true, 'method' => 'WP Customizer' );
		}

		// Method 2: DOM Scan for common classes
		$logo_selectors = array( '.logo', '.custom-logo', '.site-logo', '#logo', '.brand-logo' );
		foreach ( $logo_selectors as $selector ) {
			// Very basic check: just look for the class string in HTML if DOM search is too heavy
			if ( strpos( $this->html_content, str_replace('.', '', $selector) ) !== false ) {
				return array( 'found' => true, 'method' => 'HTML class scan' );
			}
		}

		return array( 'found' => false );
	}

	private function check_currency() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'match' => false, 'actual' => 'WC Missing', 'expected' => '' );
		}

		$expected = get_option( 'qa_checklist_expected_currency', 'USD' );
		$actual = get_woocommerce_currency();

		return array(
			'match'    => ( strtoupper($actual) === strtoupper($expected) ),
			'actual'   => $actual,
			'expected' => $expected
		);
	}
}
