<?php

// Attempt to include user's config file
if (!file_exists('config.php')) {
    die("Error: Configuration file 'config.php' not found. Please copy 'config.sample.php' to 'config.php' and update your database credentials.\n");
}
require_once 'config.php';

// --- Database Connection ---
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("MySQL Connection Failed: " . $conn->connect_error . "\n");
}
echo "Successfully connected to MySQL database '" . DB_NAME . "'.\n";
$conn->set_charset("utf8mb4");

echo "Attempting to retrieve test records...\n";

// --- Test Query 1: Retrieve a plant known to be from Trefle (e.g., Apple - Malus domestica) ---
// Note: The ID will be Trefle's ID. We search by scientific name for robustness.
$scientific_name_trefle = "Malus domestica"; // Assuming this was in trefle_data.json
$sql_trefle = "SELECT id, common_name, scientific_name, slug, family, edible_part, growth_habit FROM `species` WHERE `scientific_name` = ? LIMIT 1";

$stmt_trefle = $conn->prepare($sql_trefle);
if (!$stmt_trefle) {
    die("Error preparing Trefle test statement: " . $conn->error . "\n");
}
$stmt_trefle->bind_param("s", $scientific_name_trefle);
$stmt_trefle->execute();
$result_trefle = $stmt_trefle->get_result();

if ($result_trefle->num_rows > 0) {
    echo "\n--- Plant Data (Expected from Trefle - searched by '{$scientific_name_trefle}') ---\n";
    while($row = $result_trefle->fetch_assoc()) {
        print_r($row);
        // Try to decode JSON fields if they exist and are requested
        if (isset($row['edible_part'])) {
            echo "Decoded edible_part: ";
            print_r(json_decode($row['edible_part'], true));
            echo "\n";
        }
    }
} else {
    echo "\nNo plant found with scientific_name '{$scientific_name_trefle}' (expected from Trefle).\n";
}
$stmt_trefle->close();


// --- Test Query 2: Retrieve a plant known to be from Permapeople (e.g., Garlic - Allium sativum) ---
// Note: The ID will be Permapeople's ID.
$scientific_name_permapeople = "Allium sativum"; // Assuming this was in permapeople_data.json
$sql_permapeople = "SELECT id, common_name, scientific_name, slug, family, edible_part, growth_habit, last_updated FROM `species` WHERE `scientific_name` = ? ORDER BY `id` DESC LIMIT 1"; 
// Order by ID DESC in case multiple versions/sources inserted same scientific_name and we want the one from permapeople (assuming higher ID or just to get one)

$stmt_permapeople = $conn->prepare($sql_permapeople);
if (!$stmt_permapeople) {
    die("Error preparing Permapeople test statement: " . $conn->error . "\n");
}
$stmt_permapeople->bind_param("s", $scientific_name_permapeople);
$stmt_permapeople->execute();
$result_permapeople = $stmt_permapeople->get_result();

if ($result_permapeople->num_rows > 0) {
    echo "\n--- Plant Data (Expected from Permapeople - searched by '{$scientific_name_permapeople}') ---\n";
    while($row = $result_permapeople->fetch_assoc()) {
        print_r($row);
         if (isset($row['edible_part'])) {
            echo "Decoded edible_part: ";
            print_r(json_decode($row['edible_part'], true));
            echo "\n";
        }
    }
} else {
    echo "\nNo plant found with scientific_name '{$scientific_name_permapeople}' (expected from Permapeople).\n";
}
$stmt_permapeople->close();


// --- Test Query 3: Count total rows ---
$sql_count = "SELECT COUNT(*) as total_plants FROM `species`";
$result_count = $conn->query($sql_count);
if ($result_count && $result_count->num_rows > 0) {
    $count_row = $result_count->fetch_assoc();
    echo "\n--- Total Plants in Database ---\n";
    echo "Total rows in species table: " . $count_row['total_plants'] . "\n";
    // Expected to be around 3 from Trefle + 3 from Permapeople = 6 if IDs don't clash and all inserted.
    // If IDs clash and INSERT IGNORE worked, it might be less.
} else {
    echo "\nCould not retrieve total plant count.\n";
}


$conn->close();
echo "\nTest retrieval script finished.\n";

?>
