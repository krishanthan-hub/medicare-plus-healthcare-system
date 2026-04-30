<?php
// Run this once to create database
$conn = new mysqli('localhost', 'root', '');

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS medicare_plus";
if ($conn->query($sql)) {
    echo "Database created successfully!<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

$conn->select_db('medicare_plus');

// Read and execute SQL file
$sql = file_get_contents('database.sql');
$queries = explode(';', $sql);

foreach($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        if ($conn->query($query)) {
            echo "✓ Table created<br>";
        } else {
            echo "✗ Error: " . $conn->error . "<br>";
        }
    }
}

echo "<br><h2> Database setup complete!</h2>";
echo "<p>You can now <a href='index.php'>visit your website</a></p>";
?>