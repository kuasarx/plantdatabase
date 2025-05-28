<?php

// Attempt to include user's config file
if (!file_exists('config.php')) {
    die("Error: Configuration file 'config.php' not found. Please copy 'config.sample.php' to 'config.php' and update your credentials.\n");
}
require_once 'config.php';

if (PERMAالفطر_API_KEY_ID === 'YOUR_PERMAالفطر_KEY_ID_HERE' || PERMAالفطر_API_KEY_ID === '' ||
    PERMAالفطر_API_KEY_SECRET === 'YOUR_PERMAالفطر_KEY_SECRET_HERE' || PERMAالفطر_API_KEY_SECRET === '') {
    die("Error: Permapeople API Key ID or Secret is not set in config.php. Please add your credentials.\n");
}

// --- Helper function to make POST requests with cURL (for search) ---
function http_post_permapeople($url, $apiKeyId, $apiKeySecret, $data_json) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-permapeople-key-id: ' . $apiKeyId,
        'x-permapeople-key-secret: ' . $apiKeySecret
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Should be true in production
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo "Error communicating with Permapeople API (Search): HTTP {$http_code}\n";
        echo "Response: {$output}\n";
        return null;
    }
    return json_decode($output, true);
}

// --- Helper function to make GET requests with cURL (for plant details) ---
function http_get_permapeople($url, $apiKeyId, $apiKeySecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-permapeople-key-id: ' . $apiKeyId,
        'x-permapeople-key-secret: ' . $apiKeySecret
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Should be true in production
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo "Error fetching data from Permapeople API (Details): HTTP {$http_code}\n";
        echo "Response: {$output}\n";
        return null;
    }
    return json_decode($output, true);
}

// --- Plant list to fetch (using scientific names for searching) ---
$plants_to_fetch = [
    "Malus domestica",    // Apple
    "Allium sativum",     // Garlic
    "Lavandula angustifolia" // Lavender
];

$all_fetched_plant_data = [];

echo "Starting Permapeople.org data fetching process...\n";

foreach ($plants_to_fetch as $scientific_name_query) {
    echo "Fetching data for: {$scientific_name_query}\n";

    // 1. Search for the plant by scientific name to get its ID
    $search_url = "https://permapeople.org/api/search";
    $search_payload = json_encode(['q' => $scientific_name_query]);
    $search_response = http_post_permapeople($search_url, PERMAالفطر_API_KEY_ID, PERMAالفطر_API_KEY_SECRET, $search_payload);

    if (!$search_response || empty($search_response['plants'])) {
        echo "Could not find '{$scientific_name_query}' in Permapeople search or error in response.\n";
        if ($search_response && isset($search_response['plants']) && count($search_response['plants']) === 0) {
             echo "Search returned 0 results for '{$scientific_name_query}'.\n";
        }
        continue;
    }

    // Attempt to find a plant with matching scientific name, otherwise take the first one.
    $plant_id = null;
    $found_plant_summary = null;
    foreach ($search_response['plants'] as $plant_in_search) {
        if (isset($plant_in_search['scientific_name']) && $plant_in_search['scientific_name'] === $scientific_name_query) {
            $plant_id = $plant_in_search['id'];
            $found_plant_summary = $plant_in_search;
            echo "Found exact match for '{$scientific_name_query}' with ID: {$plant_id}\n";
            break;
        }
    }

    if (!$plant_id && !empty($search_response['plants'][0]['id'])) {
        $plant_id = $search_response['plants'][0]['id'];
        $found_plant_summary = $search_response['plants'][0];
        echo "No exact scientific name match for '{$scientific_name_query}', using first search result with ID: {$plant_id} ('{$found_plant_summary['scientific_name']}')\n";
    }
    
    if (!$plant_id) {
        echo "Could not determine plant ID for '{$scientific_name_query}' from search results.\n";
        continue;
    }

    // 2. Fetch detailed plant data using the ID
    $details_url = "https://permapeople.org/api/plants/" . $plant_id;
    $details_response = http_get_permapeople($details_url, PERMAالفطر_API_KEY_ID, PERMAالفطر_API_KEY_SECRET);

    if (!$details_response) { // http_get_permapeople already checks for empty response body
        echo "Could not fetch details for plant ID {$plant_id}.\n";
        continue;
    }
    
    // Permapeople API nests the plant data inside a key matching the plant ID, or sometimes directly.
    // Let's check if $details_response itself is the plant object, or if $details_response[$plant_id] is.
    // Based on their docs GET /api/plants/<id> returns the plant object directly.
    $pp_plant = $details_response; // Assuming the response is the plant object itself

    // 3. Map Permapeople data to our schema
    $mapped_plant = [];
    $mapped_plant['id'] = $pp_plant['id'] ?? null; // Permapeople ID
    $mapped_plant['common_name'] = $pp_plant['name'] ?? null;
    $mapped_plant['slug'] = $pp_plant['slug'] ?? null;
    $mapped_plant['scientific_name'] = $pp_plant['scientific_name'] ?? null;
    // year, bibliography, author not directly available from root
    $mapped_plant['status'] = null; // Not directly available
    $mapped_plant['rank'] = null; // (e.g. species, var) - not directly available, type is 'Plant' or 'Variety'
    
    $mapped_plant['observations'] = $pp_plant['description'] ?? null;
    // image_path not directly available at root, may be in 'data' array or not available.

    // Process the 'data' array (key-value pairs)
    $pp_data_array = $pp_plant['data'] ?? [];
    $temp_data_map = [];
    foreach ($pp_data_array as $kv_pair) {
        if (isset($kv_pair['key']) && isset($kv_pair['value'])) {
            $temp_data_map[$kv_pair['key']] = $kv_pair['value'];
        }
    }

    $mapped_plant['family'] = $temp_data_map['Family'] ?? null;
    // family_common_name not typically separate in Permapeople
    // genus_id not available
    $mapped_plant['genus'] = $temp_data_map['Genus'] ?? null; // Assuming 'Genus' might be a key

    $mapped_plant['duration'] = isset($temp_data_map['Duration']) ? json_encode(explode(', ', $temp_data_map['Duration'])) : null;
    $mapped_plant['edible_part'] = isset($temp_data_map['Edible parts']) ? json_encode(explode(', ', $temp_data_map['Edible parts'])) : null;
    $mapped_plant['edible'] = (isset($temp_data_map['Edible']) && strtolower($temp_data_map['Edible']) === 'true') ? 1 : 0;
    $mapped_plant['vegetable'] = (isset($temp_data_map['Vegetable']) && strtolower($temp_data_map['Vegetable']) === 'true') ? 1 : 0; // Assuming 'Vegetable' key

    // common_names - Permapeople has 'name' as main common name. No separate multi-language common_names object.
    // distribution - complex object in schema, Permapeople might have 'Native range' or similar in data array
    // synonyms - not directly in example, might be in 'data'
    // sources - not directly in example

    // Images - Permapeople doesn't seem to provide detailed image links via API in example
    // flower_images, leaf_images etc. likely null unless found under specific keys in 'data'

    $mapped_plant['flower_color'] = isset($temp_data_map['Flower color']) ? json_encode(explode(', ', $temp_data_map['Flower color'])) : null;
    // flower_conspicuous - not in example
    $mapped_plant['foliage_texture'] = $temp_data_map['Foliage texture'] ?? null;
    $mapped_plant['foliage_color'] = isset($temp_data_map['Foliage color']) ? json_encode(explode(', ', $temp_data_map['Foliage color'])) : null;
    // foliage_leaf_retention - not in example

    // fruit fields - not in example
    
    $mapped_plant['ligneous_type'] = $temp_data_map['Ligneous type'] ?? $temp_data_map['Type'] ?? $temp_data_map['Layer'] ?? null; // 'Layer' or 'Type' might be proxies
    $mapped_plant['growth_form'] = $temp_data_map['Growth form'] ?? null;
    $mapped_plant['growth_habit'] = $temp_data_map['Growth habit'] ?? $temp_data_map['Habit'] ?? null;
    $mapped_plant['growth_rate'] = $temp_data_map['Growth'] ?? $temp_data_map['Growth rate'] ?? null;
    
    $avg_height_val = $temp_data_map['Height range'] ?? $temp_data_map['Height'] ?? null;
    if ($avg_height_val) { // e.g. "10-15m" or "10m" - needs parsing
        // Basic parsing, assuming 'm' for meters, convert to cm for consistency if possible or store as is.
        // This is a simplification. A more robust parser would be needed for ranges and units.
        if (preg_match('/([\d\.]+)\s*m/i', $avg_height_val, $matches)) {
            $mapped_plant['average_height'] = json_encode(['text_value' => $avg_height_val, 'm_estimate' => (float)$matches[1]]);
        } else {
             $mapped_plant['average_height'] = json_encode(['text_value' => $avg_height_val]);
        }
    } else {
        $mapped_plant['average_height'] = null;
    }
    // maximum_height - similar parsing from range if available

    $mapped_plant['nitrogen_fixation'] = $temp_data_map['Nitrogen fixer'] ?? $temp_data_map['Nitrogen fixation'] ?? null;
    // shape_and_orientation - not in example
    $mapped_plant['toxicity'] = $temp_data_map['Toxicity'] ?? null;

    // days_to_harvest - not in example
    $mapped_plant['growth_description'] = $temp_data_map['Growth notes'] ?? $pp_plant['description'] ?? null; // combine with main description?
    // growth_sowing - not in example

    $mapped_plant['ph_maximum'] = $temp_data_map['pH max'] ?? null; // Assuming keys like 'pH max', 'pH min'
    $mapped_plant['ph_minimum'] = $temp_data_map['pH min'] ?? null;
    $mapped_plant['light'] = $temp_data_map['Light requirement'] ?? $temp_data_map['Sun'] ?? null; // Store text value, user schema wants int
    // atmospheric_humidity - not in example

    $mapped_plant['growth_months'] = isset($temp_data_map['Growth months']) ? json_encode(explode(', ', $temp_data_map['Growth months'])) : null;
    $mapped_plant['bloom_months'] = isset($temp_data_map['Bloom months']) ? json_encode(explode(', ', $temp_data_map['Bloom months'])) : null;
    $mapped_plant['fruit_months'] = isset($temp_data_map['Fruit months']) ? json_encode(explode(', ', $temp_data_map['Fruit months'])) : null;

    // row_spacing, spread - not in example, might be text in 'data'
    // precipitations, root_depth, temperatures - not in example, might be text in 'data'
    
    $mapped_plant['soil_nutriments'] = $temp_data_map['Soil NPK'] ?? null; // Store text, user schema wants int
    $mapped_plant['soil_salinity'] = $temp_data_map['Salinity tolerance'] ?? null; // Store text, user schema wants int
    $mapped_plant['soil_texture'] = $temp_data_map['Soil type'] ?? $temp_data_map['Soil texture'] ?? null; // Store text, user schema wants int
    $mapped_plant['soil_humidity'] = $temp_data_map['Water requirement'] ?? $temp_data_map['Soil moisture'] ?? null; // Store text, user schema wants int
    
    $mapped_plant['last_updated'] = $pp_plant['updated_at'] ?? null; // Use Permapeople's updated_at

    // Add any other known mappings from $temp_data_map to $mapped_plant here
    // This mapping is very basic due to the generic key-value structure of Permapeople's 'data' field
    // and the limited example response. A full mapping would require analyzing many Permapeople plant profiles.

    $all_fetched_plant_data[] = $mapped_plant;
    echo "Successfully mapped data for Permapeople ID {$plant_id} ('{$pp_plant['scientific_name']}').\n";
    
    sleep(1); // Be respectful of API rate limits
}

echo "--------------------------------------------------\n";
echo "Finished fetching and mapping all Permapeople.org plant data.\n";
echo "Total plants processed: " . count($all_fetched_plant_data) . "\n\n";

echo "Mapped data (PHP array format):\n";
var_export($all_fetched_plant_data);
echo "\n";

$json_output = json_encode($all_fetched_plant_data, JSON_PRETTY_PRINT);
file_put_contents('permapeople_data.json', $json_output);
echo "Mapped data also saved to permapeople_data.json\n";

?>
