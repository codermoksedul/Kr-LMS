<?php
require_once('../../../wp-load.php');

global $wpdb;
$table_lb = $wpdb->prefix . "kr_leaderboard";

// Check valid course ID first (from existing data)
$course_check = $wpdb->get_row("SELECT course_id FROM $table_lb LIMIT 1");

if (!$course_check) {
    echo "No leaderboard data found at all.<br>";
    // Look for a valid course ID from posts
    $course_id = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='lp_course' LIMIT 1");
} else {
    $course_id = $course_check->course_id;
}

if (!$course_id) {
    die("No courses found to seed data.");
}

// Check count
$count = $wpdb->get_var("SELECT count(*) FROM $table_lb WHERE course_id = $course_id");
echo "Current records for Course $course_id: $count<br>";

// If less than 5, seed some data
if ($count < 5) {
    echo "Seeding dummy data...<br>";
    $names = ['Alice', 'Bob', 'Charlie', 'David', 'Eve', 'Frank', 'Grace', 'Heidi', 'Ivan', 'Judy'];
    
    foreach ($names as $i => $name) {
        $user_id = 9000 + $i; // Fake user IDs
        $points = rand(50, 100) / 10;
        
        $wpdb->insert($table_lb, [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'exam_name' => 'Demo Exam ' . ($i+1),
            'points' => $points,
            'date' => date('Y-m-d', strtotime("-$i days"))
        ]);
        
        // Use user_id 0 or update row to simulate name if user doesn't exist?
        // Actually the query joins with users table. 
        // If user doesn't exist, display_name will be null.
        // My code handles null with "Unknown User".
        // To make it pretty, let's just insert into users table strictly for test? No, unsafe.
        // Let's just rely on "Unknown User" or try to find real users.
    }
    echo "Seeded 10 records.<br>";
} else {
    echo "Sufficient data exists.<br>";
}

// Display Table
$results = $wpdb->get_results("SELECT * FROM $table_lb WHERE course_id = $course_id ORDER BY points DESC LIMIT 20");
echo "<pre>";
print_r($results);
echo "</pre>";
