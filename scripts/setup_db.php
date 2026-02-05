<?php
$host = 'localhost';
$user = 'root';
$password = '';

// Connect to MySQL server (no database yet)
$conn = new mysqli($host, $user, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read the SQL file
$sql = file_get_contents('database.sql');

// Execute multi query
if ($conn->multi_query($sql)) {
    echo "Database created and tables setup successfully.";
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
        // Prepare next result set
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error creating database: " . $conn->error;
}

$conn->close();
?>