<?php if (!defined('FW')) die('Forbidden');

$manifest = array();

$manifest['name']        = __( 'Learning', 'fw' );
$manifest['slug']        = 'unysonplus-learning';
$manifest['description'] = __(
	'This extension adds a Learning module to your theme. '
	. 'Using this extension you can add courses, lessons, and tests for your users to take.',
	'fw'
);

$manifest['version']     = '1.0.13';
$manifest['display']     = true;
$manifest['standalone']  = true;

// Repository Info
$manifest['github_update'] = 'UnysonPlus/UnysonPlus-Learning-Extension';
$manifest['github_repo']   = 'https://github.com/UnysonPlus/UnysonPlus-Learning-Extension';
$manifest['github_branch'] = 'master';

// Author Info
$manifest['author']     = 'UnysonPlus';
$manifest['author_uri'] = 'https://www.lastimosa.com.ph/unysonplus';

// Meta
$manifest['license']      = 'GPL-2.0-or-later';
$manifest['text_domain']  = 'fw';
$manifest['requires_php'] = '7.4';
$manifest['requires_wp']  = '5.8';
