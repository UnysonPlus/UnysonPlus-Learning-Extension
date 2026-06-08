<?php if (!defined('FW')) die('Forbidden');

/**
 * Changelog ----------------------------------------------------------------
 *
 * 1.0.18 - Short Answer question type. Adds a free-text question that the
 *          teacher auto-grades by supplying one or more accepted answers;
 *          a submission matching any of them (case-insensitive, whitespace
 *          collapsed) earns full points. Registered alongside the existing
 *          single/multiple choice, true-false and gap-fill items.
 *
 * 1.0.17 - Quiz shuffle and time limit. New per-lesson Quiz Settings let a
 *          teacher randomize question order per attempt and set a countdown
 *          (minutes) that auto-submits the quiz at zero. The limit is also
 *          enforced server-side: a submission arriving after the allotted
 *          time (plus a short grace) cannot pass.
 *
 * 1.0.16 - Per-question answer feedback. After submitting, students can see
 *          which questions they got right, wrong, or partially right, their
 *          answer, the correct answer, and an optional teacher-written
 *          Explanation (a new field available on every question type).
 *          Toggle per lesson via "Show answer feedback".
 *
 * 1.0.15 - Teacher gradebook. Every graded quiz submission is now persisted
 *          to a dedicated table ({prefix}fw_learning_quiz_attempts) instead
 *          of being discarded with the page session. A new "Quiz Results"
 *          admin screen under the Learning menu lists each attempt (student,
 *          quiz, course, score, pass/fail, date) with a per-quiz filter and
 *          CSV export. Scores are always recomputed server-side, never taken
 *          from the submission.
 */

$manifest = array();

$manifest['name']        = __( 'Learning', 'fw' );
$manifest['slug']        = 'unysonplus-learning';
$manifest['description'] = __(
	'This extension adds a Learning module to your theme. '
	. 'Using this extension you can add courses, lessons, and tests for your users to take.',
	'fw'
);

$manifest['version']     = '1.0.18';
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
