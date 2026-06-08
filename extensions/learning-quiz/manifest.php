<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$manifest = array();

$manifest['standalone'] = true;

$manifest['version'] = '1.0.0';

$manifest['requirements'] = array(
	'extensions' => array(
		'builder'               => array(),
		'learning-apply-course' => array(),
	)
);
