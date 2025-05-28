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
$conn->set_charset("utf8mb4"); // Ensure UTF-8 is used

// --- Get JSON file from command line argument ---
if ($argc < 2) {
    die("Usage: php insert_plant_data.php <json_file_path>\nExample: php insert_plant_data.php trefle_data.json\n");
}
$json_file_path = $argv[1];
if (!file_exists($json_file_path)) {
    die("Error: JSON file '{$json_file_path}' not found.\n");
}
$json_data = file_get_contents($json_file_path);
$plants_to_insert = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error decoding JSON from '{$json_file_path}': " . json_last_error_msg() . "\n");
}

if (empty($plants_to_insert) || !is_array($plants_to_insert)) {
    die("No plant data found in '{$json_file_path}' or data is not in expected array format.\n");
}

echo "Processing " . count($plants_to_insert) . " plant(s) from '{$json_file_path}'...\n";

// --- Prepare the INSERT statement ---
// Dynamically build column list and placeholders for prepared statement
// Assumes all plants in the JSON have the same structure (all potential keys)
// Get all possible column names from the first plant record, then filter against schema.

$first_plant = $plants_to_insert[0];
$all_possible_columns = array_keys($first_plant);

// Define the full list of columns in your 'species' table from the schema
// This is crucial to ensure we only try to insert valid columns
$schema_columns = [
    'id', 'common_name', 'slug', 'scientific_name', 'year', 'bibliography', 'author', 'status', 'rank',
    'family_common_name', 'family', 'genus_id', 'genus', 'image_path', 'duration', 'edible_part',
    'edible', 'vegetable', 'observations', 'common_names', 'distribution', 'synonyms', 'sources',
    'flower_images', 'leaf_images', 'habit_images', 'fruit_images', 'bark_images', 'other_images',
    'distributions_native', 'distributions_introduced', 'distributions_doubtful', 'distributions_absent',
    'distributions_extinct', 'flower_color', 'flower_conspicuous', 'foliage_texture', 'foliage_color',
    'foliage_leaf_retention', 'fruit_conspicuous', 'fruit_color', 'fruit_shape', 'fruit_seed_persistence',
    'ligneous_type', 'growth_form', 'growth_habit', 'growth_rate', 'average_height', 'maximum_height',
    'nitrogen_fixation', 'shape_and_orientation', 'toxicity', 'days_to_harvest', 'growth_description',
    'growth_sowing', 'ph_maximum', 'ph_minimum', 'light', 'atmospheric_humidity', 'growth_months',
    'bloom_months', 'fruit_months', 'row_spacing', 'spread', 'minimum_precipitation', 'maximum_precipitation',
    'minimum_root_depth', 'minimum_temperature', 'maximum_temperature', 'soil_nutriments', 'soil_salinity',
    'soil_texture', 'soil_humidity' // 'last_updated' is auto by MySQL
];

// Filter keys from data to only include those that are actual columns in the table
$columns_to_insert = [];
foreach ($all_possible_columns as $col) {
    if (in_array($col, $schema_columns)) {
        $columns_to_insert[] = $col;
    }
}

if (empty($columns_to_insert)) {
    die("Error: No valid columns to insert found based on the input data and schema.\n");
}

$column_list_sql = "`" . implode("`, `", $columns_to_insert) . "`";
$placeholder_list_sql = rtrim(str_repeat("?, ", count($columns_to_insert)), ", ");

// Using INSERT IGNORE to skip duplicates based on PRIMARY KEY (id from Trefle/Permapeople)
// or any other UNIQUE keys defined in the table.
// For more sophisticated duplicate handling (e.g., on scientific_name if it's not PK but should be unique),
// a SELECT before INSERT or INSERT ... ON DUPLICATE KEY UPDATE would be needed.
$sql = "INSERT IGNORE INTO `species` ({$column_list_sql}) VALUES ({$placeholder_list_sql})";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error . "\nSQL: {$sql}\n");
}

echo "Prepared statement: {$sql}\n";

$type_string = "";
foreach ($columns_to_insert as $col) {
    // Determine type for bind_param based on typical data. This is a simplification.
    // A more robust way would be to inspect schema types or know data types precisely.
    $sample_value = $first_plant[$col] ?? null;
    if (is_int($sample_value)) {
        $type_string .= "i";
    } elseif (is_float($sample_value)) {
        $type_string .= "d";
    } else {
        $type_string .= "s"; // Default to string for nulls, JSON strings, text
    }
}

$inserted_count = 0;
$skipped_count = 0;

foreach ($plants_to_insert as $plant_data) {
    $bind_params_values = []; // Array to hold actual values for current row

    foreach ($columns_to_insert as $col_name) {
        $bind_params_values[] = $plant_data[$col_name] ?? null;
    }
    
    // Check if the number of values matches the type string length
    if (count($bind_params_values) !== strlen($type_string)) {
        echo "Warning: Parameter count (" . count($bind_params_values) . ") does not match type string length ('{$type_string}'=" . strlen($type_string) . ") for plant ID {$plant_data['id']}. Skipping this record.\n";
        // Debugging output for mismatch:
        // echo "Columns for type string: " . implode(", ", $columns_to_insert) . "\n";
        // echo "Plant data keys: " . implode(", ", array_keys($plant_data)) . "\n";
        continue;
    }
    
    $stmt->bind_param($type_string, ...$bind_params_values);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "Successfully inserted plant with ID: " . ($plant_data['id'] ?? 'N/A') . " (Scientific Name: " . ($plant_data['scientific_name'] ?? 'N/A') . ").\n";
            $inserted_count++;
        } else {
            echo "Skipped plant with ID: " . ($plant_data['id'] ?? 'N/A') . " (Scientific Name: " . ($plant_data['scientific_name'] ?? 'N/A') . ") - likely a duplicate ID.\n";
            $skipped_count++;
        }
    } else {
        echo "Error inserting plant with ID: " . ($plant_data['id'] ?? 'N/A') . " - " . $stmt->error . "\n";
    }
}

$stmt->close();
$conn->close();

echo "--------------------------------------------------\n";
echo "Data insertion process complete.\n";
echo "Successfully inserted records: {$inserted_count}\n";
echo "Skipped (duplicate ID) records: {$skipped_count}\n";

?>
