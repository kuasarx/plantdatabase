<?php

// Attempt to include user's config file
if (!file_exists('config.php')) {
    die("Error: Configuration file 'config.php' not found. Please copy 'config.sample.php' to 'config.php' and update your database credentials.\n");
}
require_once 'config.php';

// MySQLi connection to the server (without specifying a database initially)
$conn_server = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check server connection
if ($conn_server->connect_error) {
    die("Server Connection Failed: " . $conn_server->connect_error . "\n");
}
echo "Successfully connected to MySQL server.\n";

// Create database if it doesn't exist
$sql_create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn_server->query($sql_create_db) === TRUE) {
    echo "Database '" . DB_NAME . "' created successfully or already exists.\n";
} else {
    die("Error creating database: " . $conn_server->error . "\n");
}

// Close server connection
$conn_server->close();

// MySQLi connection to the specific database
$conn_db = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check database connection
if ($conn_db->connect_error) {
    die("Database Connection Failed: " . $conn_db->connect_error . "\n");
}
echo "Successfully connected to database '" . DB_NAME . "'.\n";

// SQL to create species table (user provided schema)
$sql_create_table = "
CREATE TABLE IF NOT EXISTS `species` (
  `id` int(11) NOT NULL,
  `common_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scientific_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `bibliography` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `family_common_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `family` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genus_id` int(11) DEFAULT NULL,
  `genus` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`duration`)),
  `edible_part` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`edible_part`)),
  `edible` tinyint(1) DEFAULT NULL,
  `vegetable` tinyint(1) DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `common_names` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`common_names`)),
  `distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distribution`)),
  `synonyms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`synonyms`)),
  `sources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sources`)),
  `flower_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`flower_images`)),
  `leaf_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`leaf_images`)),
  `habit_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`habit_images`)),
  `fruit_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fruit_images`)),
  `bark_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bark_images`)),
  `other_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`other_images`)),
  `distributions_native` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distributions_native`)),
  `distributions_introduced` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distributions_introduced`)),
  `distributions_doubtful` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distributions_doubtful`)),
  `distributions_absent` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distributions_absent`)),
  `distributions_extinct` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distributions_extinct`)),
  `flower_color` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`flower_color`)),
  `flower_conspicuous` tinyint(1) DEFAULT NULL,
  `foliage_texture` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foliage_color` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`foliage_color`)),
  `foliage_leaf_retention` tinyint(1) DEFAULT NULL,
  `fruit_conspicuous` tinyint(1) DEFAULT NULL,
  `fruit_color` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fruit_color`)),
  `fruit_shape` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fruit_seed_persistence` tinyint(1) DEFAULT NULL,
  `ligneous_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `growth_form` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `growth_habit` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `growth_rate` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `average_height` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`average_height`)),
  `maximum_height` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`maximum_height`)),
  `nitrogen_fixation` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shape_and_orientation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `toxicity` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `days_to_harvest` int(11) DEFAULT NULL,
  `growth_description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `growth_sowing` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ph_maximum` float DEFAULT NULL,
  `ph_minimum` float DEFAULT NULL,
  `light` int(11) DEFAULT NULL,
  `atmospheric_humidity` int(11) DEFAULT NULL,
  `growth_months` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`growth_months`)),
  `bloom_months` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bloom_months`)),
  `fruit_months` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fruit_months`)),
  `row_spacing` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`row_spacing`)),
  `spread` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`spread`)),
  `minimum_precipitation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`minimum_precipitation`)),
  `maximum_precipitation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`maximum_precipitation`)),
  `minimum_root_depth` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`minimum_root_depth`)),
  `minimum_temperature` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`minimum_temperature`)),
  `maximum_temperature` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`maximum_temperature`)),
  `soil_nutriments` int(11) DEFAULT NULL,
  `soil_salinity` int(11) DEFAULT NULL,
  `soil_texture` int(11) DEFAULT NULL,
  `soil_humidity` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn_db->query($sql_create_table) === TRUE) {
    echo "Table 'species' created successfully or already exists.\n";
} else {
    // Output detailed error if table creation fails
    die("Error creating table 'species': " . $conn_db->error . "\n");
}

// Close database connection
$conn_db->close();

echo "Database setup complete.\n";

?>
