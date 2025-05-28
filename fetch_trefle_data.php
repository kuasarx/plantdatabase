<?php

// Attempt to include user's config file
if (!file_exists('config.php')) {
    die("Error: Configuration file 'config.php' not found. Please copy 'config.sample.php' to 'config.php' and update your database and Trefle API credentials.\n");
}
require_once 'config.php';

if (TREFLE_API_TOKEN === 'YOUR_TREFLE_API_TOKEN_HERE' || TREFLE_API_TOKEN === '') {
    die("Error: Trefle API token is not set in config.php. Please add your TREFLE_API_TOKEN.\n");
}

// --- Helper function to make GET requests with cURL ---
function http_get_trefle($url, $token) { // Renamed for clarity
    $full_url = $url . (strpos($url, '?') === false ? '?' : '&') . "token=" . $token;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Should be true in production with proper certs
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo "Error fetching data from Trefle: HTTP {$http_code}\n";
        echo "URL: {$full_url}\n";
        echo "Response: {$output}\n";
        return null;
    }
    return json_decode($output, true);
}

// --- Plant list to fetch (using known/guessed slugs) ---
// Associative array: "Scientific Name for logging" => "slug-for-url"
$plants_to_fetch = [
    "Malus domestica" => "malus-domestica",       // Apple
    "Allium sativum" => "allium-sativum",        // Garlic
    "Lavandula angustifolia" => "lavandula-angustifolia" // Lavender
];

$all_fetched_plant_data = [];

echo "Starting Trefle.io data fetching process using direct slugs...\n";

foreach ($plants_to_fetch as $scientific_name_log => $slug) {
    echo "Fetching data for: {$scientific_name_log} (slug: {$slug})\n";

    $details_url = "https://trefle.io/api/v1/species/" . $slug;
    
    $details_response_raw = http_get_trefle($details_url, TREFLE_API_TOKEN);

    if (!$details_response_raw || empty($details_response_raw['data'])) {
        echo "Could not fetch details for '{$scientific_name_log}' using slug '{$slug}' from {$details_url}.\n";
        continue;
    }
    
    $trefle_plant = $details_response_raw['data'];

    // 3. Map Trefle data to our schema (mapping logic remains the same as before)
    $mapped_plant = [];

    // Basic fields
    $mapped_plant['id'] = $trefle_plant['id'] ?? null;
    $mapped_plant['common_name'] = $trefle_plant['common_name'] ?? null;
    $mapped_plant['slug'] = $trefle_plant['slug'] ?? null;
    $mapped_plant['scientific_name'] = $trefle_plant['scientific_name'] ?? null;
    $mapped_plant['year'] = $trefle_plant['year'] ?? null;
    $mapped_plant['bibliography'] = $trefle_plant['bibliography'] ?? null;
    $mapped_plant['author'] = $trefle_plant['author'] ?? null;
    $mapped_plant['status'] = $trefle_plant['status'] ?? null;
    $mapped_plant['rank'] = $trefle_plant['rank'] ?? null;
    $mapped_plant['family_common_name'] = $trefle_plant['family_common_name'] ?? null;
    $mapped_plant['family'] = $trefle_plant['family']['name'] ?? $trefle_plant['family'] ?? null;
    $mapped_plant['genus_id'] = $trefle_plant['genus']['id'] ?? $trefle_plant['genus_id'] ?? null;
    $mapped_plant['genus'] = $trefle_plant['genus']['name'] ?? $trefle_plant['genus'] ?? null;
    $mapped_plant['image_path'] = $trefle_plant['image_url'] ?? null;

    $mapped_plant['duration'] = isset($trefle_plant['duration']) && is_array($trefle_plant['duration']) ? json_encode($trefle_plant['duration']) : null;
    $mapped_plant['edible_part'] = isset($trefle_plant['edible_parts']) && is_array($trefle_plant['edible_parts']) ? json_encode($trefle_plant['edible_parts']) : (isset($trefle_plant['parts_used_as_edible']) && is_array($trefle_plant['parts_used_as_edible']) ? json_encode($trefle_plant['parts_used_as_edible']) : null);
    $mapped_plant['edible'] = isset($trefle_plant['edible']) ? ($trefle_plant['edible'] ? 1 : 0) : null;
    $mapped_plant['vegetable'] = isset($trefle_plant['vegetable']) ? ($trefle_plant['vegetable'] ? 1 : 0) : null;
    $mapped_plant['observations'] = $trefle_plant['observations'] ?? null;
    $mapped_plant['common_names'] = isset($trefle_plant['common_names']) && is_array($trefle_plant['common_names']) ? json_encode($trefle_plant['common_names']) : (isset($trefle_plant['common_names']) && is_object($trefle_plant['common_names']) ? json_encode($trefle_plant['common_names']) : null);

    $distribution_data = [];
    if (isset($trefle_plant['distributions']['native'])) $distribution_data['native'] = $trefle_plant['distributions']['native'];
    if (isset($trefle_plant['distributions']['introduced'])) $distribution_data['introduced'] = $trefle_plant['distributions']['introduced'];
    $mapped_plant['distribution'] = !empty($distribution_data) ? json_encode($distribution_data) : null;
    
    $mapped_plant['distributions_native'] = isset($trefle_plant['distributions']['native']) ? json_encode($trefle_plant['distributions']['native']) : null;
    $mapped_plant['distributions_introduced'] = isset($trefle_plant['distributions']['introduced']) ? json_encode($trefle_plant['distributions']['introduced']) : null;
    // Adding other distribution types based on schema
    $mapped_plant['distributions_doubtful'] = isset($trefle_plant['distributions']['doubtful']) ? json_encode($trefle_plant['distributions']['doubtful']) : null;
    $mapped_plant['distributions_absent'] = isset($trefle_plant['distributions']['absent']) ? json_encode($trefle_plant['distributions']['absent']) : null;
    $mapped_plant['distributions_extinct'] = isset($trefle_plant['distributions']['extinct']) ? json_encode($trefle_plant['distributions']['extinct']) : null;

    $mapped_plant['synonyms'] = isset($trefle_plant['synonyms']) && is_array($trefle_plant['synonyms']) ? json_encode($trefle_plant['synonyms']) : null;
    $mapped_plant['sources'] = isset($trefle_plant['sources']) && is_array($trefle_plant['sources']) ? json_encode($trefle_plant['sources']) : null;

    $main_species_data = $trefle_plant['main_species'] ?? []; // Use main_species as a fallback for nested data

    // Access images through $trefle_plant['images'] or $main_species_data['images']
    $images_source = $trefle_plant['images'] ?? $main_species_data['images'] ?? [];
    $mapped_plant['flower_images'] = isset($images_source['flower']) ? json_encode($images_source['flower']) : null;
    $mapped_plant['leaf_images'] = isset($images_source['leaf']) ? json_encode($images_source['leaf']) : null;
    $mapped_plant['habit_images'] = isset($images_source['habit']) ? json_encode($images_source['habit']) : null;
    $mapped_plant['fruit_images'] = isset($images_source['fruit']) ? json_encode($images_source['fruit']) : null;
    $mapped_plant['bark_images'] = isset($images_source['bark']) ? json_encode($images_source['bark']) : null;
    $mapped_plant['other_images'] = isset($images_source['other']) ? json_encode($images_source['other']) : null;
    
    $flower_data = $trefle_plant['flower'] ?? $main_species_data['flower'] ?? [];
    $foliage_data = $trefle_plant['foliage'] ?? $main_species_data['foliage'] ?? [];
    $fruit_seed_data = $trefle_plant['fruit_or_seed'] ?? $main_species_data['fruit_or_seed'] ?? [];
    $specifications_data = $trefle_plant['specifications'] ?? $main_species_data['specifications'] ?? [];
    $growth_data = $trefle_plant['growth'] ?? $main_species_data['growth'] ?? [];

    $mapped_plant['flower_color'] = isset($flower_data['color']) ? json_encode($flower_data['color']) : null;
    $mapped_plant['flower_conspicuous'] = isset($flower_data['conspicuous']) ? ($flower_data['conspicuous'] ? 1:0) : null;
    
    $mapped_plant['foliage_texture'] = $foliage_data['texture'] ?? null;
    $mapped_plant['foliage_color'] = isset($foliage_data['color']) ? json_encode($foliage_data['color']) : null;
    $mapped_plant['foliage_leaf_retention'] = isset($foliage_data['leaf_retention']) ? ($foliage_data['leaf_retention'] ? 1:0) : null;

    $mapped_plant['fruit_conspicuous'] = isset($fruit_seed_data['conspicuous']) ? ($fruit_seed_data['conspicuous'] ? 1:0) : null;
    $mapped_plant['fruit_color'] = isset($fruit_seed_data['color']) ? json_encode($fruit_seed_data['color']) : null;
    $mapped_plant['fruit_shape'] = $fruit_seed_data['shape'] ?? null;
    $mapped_plant['fruit_seed_persistence'] = isset($fruit_seed_data['seed_persistence']) ? ($fruit_seed_data['seed_persistence'] ? 1:0) : null;

    $mapped_plant['ligneous_type'] = $specifications_data['ligneous_type'] ?? null;
    $mapped_plant['growth_form'] = $specifications_data['growth_form'] ?? null;
    $mapped_plant['growth_habit'] = $specifications_data['growth_habit'] ?? null;
    $mapped_plant['growth_rate'] = $specifications_data['growth_rate'] ?? null;
    
    // Ensure correct extraction for unit-based values
    $avg_height_cm_val = $specifications_data['average_height']['cm'] ?? null;
    $mapped_plant['average_height'] = $avg_height_cm_val !== null ? json_encode(['cm' => $avg_height_cm_val]) : null;

    $max_height_cm_val = $specifications_data['maximum_height']['cm'] ?? null;
    $mapped_plant['maximum_height'] = $max_height_cm_val !== null ? json_encode(['cm' => $max_height_cm_val]) : null;
    
    $mapped_plant['nitrogen_fixation'] = $specifications_data['nitrogen_fixation'] ?? null;
    $mapped_plant['shape_and_orientation'] = $specifications_data['shape_and_orientation'] ?? null;
    $mapped_plant['toxicity'] = $specifications_data['toxicity'] ?? null;
    
    $mapped_plant['days_to_harvest'] = $growth_data['days_to_harvest'] ?? null;
    $mapped_plant['growth_description'] = $growth_data['description'] ?? null;
    $mapped_plant['growth_sowing'] = $growth_data['sowing'] ?? null;
    $mapped_plant['ph_maximum'] = $growth_data['ph_maximum'] ?? null;
    $mapped_plant['ph_minimum'] = $growth_data['ph_minimum'] ?? null;
    $mapped_plant['light'] = $growth_data['light'] ?? null;
    $mapped_plant['atmospheric_humidity'] = $growth_data['atmospheric_humidity'] ?? null;
    
    $mapped_plant['growth_months'] = isset($growth_data['growth_months']) && is_array($growth_data['growth_months']) ? json_encode($growth_data['growth_months']) : null;
    $mapped_plant['bloom_months'] = isset($growth_data['bloom_months']) && is_array($growth_data['bloom_months']) ? json_encode($growth_data['bloom_months']) : null;
    $mapped_plant['fruit_months'] = isset($growth_data['fruit_months']) && is_array($growth_data['fruit_months']) ? json_encode($growth_data['fruit_months']) : null;
    
    $row_spacing_cm_val = $growth_data['row_spacing']['cm'] ?? null;
    $mapped_plant['row_spacing'] = $row_spacing_cm_val !== null ? json_encode(['cm' => $row_spacing_cm_val]) : null;

    $spread_cm_val = $growth_data['spread']['cm'] ?? null;
    $mapped_plant['spread'] = $spread_cm_val !== null ? json_encode(['cm' => $spread_cm_val]) : null;
    
    $min_precip_mm_val = $growth_data['minimum_precipitation']['mm'] ?? null;
    $mapped_plant['minimum_precipitation'] = $min_precip_mm_val !== null ? json_encode(['mm' => $min_precip_mm_val]) : null;

    $max_precip_mm_val = $growth_data['maximum_precipitation']['mm'] ?? null;
    $mapped_plant['maximum_precipitation'] = $max_precip_mm_val !== null ? json_encode(['mm' => $max_precip_mm_val]) : null;

    $min_root_depth_cm_val = $growth_data['minimum_root_depth']['cm'] ?? null;
    $mapped_plant['minimum_root_depth'] = $min_root_depth_cm_val !== null ? json_encode(['cm' => $min_root_depth_cm_val]) : null;
    
    $min_temp_deg_c_val = $growth_data['minimum_temperature']['deg_c'] ?? null;
    $mapped_plant['minimum_temperature'] = $min_temp_deg_c_val !== null ? json_encode(['deg_c' => $min_temp_deg_c_val]) : null;

    $max_temp_deg_c_val = $growth_data['maximum_temperature']['deg_c'] ?? null;
    $mapped_plant['maximum_temperature'] = $max_temp_deg_c_val !== null ? json_encode(['deg_c' => $max_temp_deg_c_val]) : null;

    $mapped_plant['soil_nutriments'] = $growth_data['soil_nutriments'] ?? null;
    $mapped_plant['soil_salinity'] = $growth_data['soil_salinity'] ?? null;
    $mapped_plant['soil_texture'] = $growth_data['soil_texture'] ?? null;
    $mapped_plant['soil_humidity'] = $growth_data['soil_humidity'] ?? null;

    $all_fetched_plant_data[] = $mapped_plant;
    echo "Successfully mapped data for {$scientific_name_log}.\n";
    
    sleep(1); 
}

echo "--------------------------------------------------\n";
echo "Finished fetching and mapping all plant data from Trefle (direct slug method).\n";
echo "Total plants processed: " . count($all_fetched_plant_data) . "\n\n";

echo "Mapped data (PHP array format):\n";
var_export($all_fetched_plant_data);
echo "\n";

$json_output = json_encode($all_fetched_plant_data, JSON_PRETTY_PRINT);
file_put_contents('trefle_data_direct.json', $json_output); // Save to a new file to avoid overwriting search results if they later work
echo "Mapped data also saved to trefle_data_direct.json\n";

?>
