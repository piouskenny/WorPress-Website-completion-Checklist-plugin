<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qa_checklist_get_default_items() {
	return array(
		'core' => array(
			'Logo accuracy (desktop & mobile)',
			'Testimonials correctness',
			'Map & address validation',
			'Footer links validation',
			'CTA functionality',
			'Content validation (no dummy/AI errors)',
			'Image validation (relevance, not excessive AI)',
			'Mobile responsiveness',
			'Company email accuracy',
			'Logo presence check',
		),
		'woocommerce' => array(
			'Shop accessibility (public)',
			'Product display validation',
			'Pricing accuracy',
			'Checkout functionality',
			'Payment configuration',
			'Currency configuration check',
		),
		'forms' => array(
			'Form submission success',
			'Email delivery confirmation',
			'Success/error messaging',
		),
		'seo' => array(
			'Meta titles/descriptions',
			'Heading structure (H1, H2, etc.)',
		),
	);
}
