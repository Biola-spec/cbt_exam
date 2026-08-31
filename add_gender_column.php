<?php
require_once 'config/database.php';

echo "<h2>Adding Gender Column to Users Table</h2>";
echo "<hr>";

$mysqli = getDBConnection();

// Check if column already exists
$stmt = $mysqli->query("SHOW COLUMNS FROM users LIKE 'gender'");
if ($stmt->num_rows > 0) {
    echo "<p style='color: blue;'>ⓘ Column 'gender' already exists in users table</p>";
} else {
    // Add gender column
    $sql = "ALTER TABLE users ADD COLUMN gender ENUM('male', 'female') DEFAULT NULL AFTER full_name";
    
    if ($mysqli->query($sql)) {
        echo "<p style='color: green;'>✓ Successfully added 'gender' column to users table</p>";
        
        // Update existing students to have NULL gender (or default)
        $update_sql = "UPDATE users SET gender = NULL WHERE role = 'student'";
        $mysqli->query($update_sql);
        
        echo "<p style='color: green;'>✓ Updated existing users (gender set to NULL)</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding column: " . $mysqli->error . "</p>";
    }
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Go to admin/student_management.php</li>";
echo "<li>Add/edit students with gender information</li>";
echo "<li>View class management to see gender statistics</li>";
echo "</ol>";

echo "<p><a href='admin/student_management.php' class='btn btn-primary'>Go to Student Management</a></p>";

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
h3 { color: #666; margin-top: 20px; }
.btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
.btn:hover { background: #0056b3; }
</style>";
?>
