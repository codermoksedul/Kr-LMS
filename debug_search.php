<?php
// Load WordPress
require_once('../../../wp-load.php');

// Check Post Types
$types = get_post_types();
echo "Post Types: \n";
// print_r($types);

if (in_array('lp_lesson', $types)) echo "lp_lesson EXISTS\n";
else echo "lp_lesson MISSING\n";

if (in_array('lp_quiz', $types)) echo "lp_quiz EXISTS\n";
else echo "lp_quiz MISSING\n";

// Count
$count_lessons = wp_count_posts('lp_lesson');
$count_quizzes = wp_count_posts('lp_quiz');
echo "Lessons: " . $count_lessons->publish . "\n";
echo "Quizzes: " . $count_quizzes->publish . "\n";

// Test Search
$term = ""; // Search all
$posts = get_posts(['post_type' => ['lp_lesson', 'lp_quiz'], 'posts_per_page' => 5]);
echo "Found " . count($posts) . " items:\n";
foreach($posts as $p) {
    echo "- " . $p->post_title . " (" . $p->post_type . ")\n";
}
