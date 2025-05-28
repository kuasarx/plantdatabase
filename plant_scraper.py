import requests
from bs4 import BeautifulSoup
import sqlite3
import re # For parsing botanical name from URL

DB_PATH = 'permaculture_plants.db'

def scrape_plant_data(url: str) -> dict:
    """
    Fetches and parses the "Full Data" / "Facts about" section from a Practical Plants URL.
    """
    scraped_data = {}
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    }
    try:
        print(f"Fetching URL: {url}")
        response = requests.get(url, headers=headers, timeout=15)
        response.raise_for_status()
        soup = BeautifulSoup(response.content, 'html.parser')

        # Try to find the heading "Facts about [Plant Name]"
        # The plant name can be extracted from the URL for a more robust search
        plant_name_from_url = url.split('/')[-1].replace('_', ' ')
        
        facts_heading = soup.find(lambda tag: tag.name in ['h1', 'h2', 'h3', 'h4'] and \
                                  f"Facts about {plant_name_from_url}" in tag.get_text())

        property_elements = []
        if facts_heading:
            print(f"Found heading: {facts_heading.get_text(strip=True)}")
            # Look for a 'ul' element that is a sibling to the heading or its parent.
            # This 'ul' should contain the semantic properties as 'li' elements.
            container = facts_heading.find_next_sibling('ul')
            if not container:
                # Sometimes the heading is inside a div, and the ul is sibling to that div
                parent_div = facts_heading.parent
                container = parent_div.find_next_sibling('ul')
            
            if container:
                print("Found 'ul' container for properties.")
                property_elements = container.find_all('li', recursive=False) # Get direct children <li>
                if not property_elements:
                    # If no direct children, try finding all <li> within the container
                    property_elements = container.find_all('li') 
                    if property_elements:
                        print(f"Found {len(property_elements)} <li> elements recursively in container.")
                    else:
                        print("No <li> elements found in the 'ul' container.")
            else:
                print("Could not find 'ul' container for properties near the heading.")
        else:
            print(f"Could not find 'Facts about {plant_name_from_url}' heading. Will try direct search for smwprop_ ids.")
            # Fallback to direct search if heading is not found (as in previous attempts)
            property_elements = soup.find_all('li', id=lambda x: x and x.startswith('smwprop_'))
            if property_elements:
                print(f"Found {len(property_elements)} elements by smwprop_ id directly.")


        if not property_elements:
            print(f"No property elements found for {url} using heading-based search or direct id search.")
            # For debugging: print a snippet of the HTML if elements are not found
            # print(soup.prettify()[:2000]) # Print first 2000 chars of HTML
            return {}


        for item in property_elements:
            # The key is usually in the first 'a' tag within the 'li' that links to a Property: page
            key_element = item.find('a', href=lambda x: x and x.startswith('/wiki/Property:'))
            if not key_element and item.get('id','').startswith('smwprop_'): # If using direct id search, key_element might be the item itself if structure is flat
                key_element = item.find('a') # a bit more generic

            if key_element:
                key = key_element.get_text(strip=True)
                
                value_parts = []
                is_after_key_element = False
                for content in item.contents:
                    if content == key_element:
                        is_after_key_element = True
                        continue
                    
                    if is_after_key_element:
                        if isinstance(content, str):
                            cleaned_content = content.strip()
                            if cleaned_content:
                                value_parts.append(cleaned_content)
                        elif content.name == 'a':
                            if content.get('rel') == 'nofollow' and content.get_text(strip=True) == '+':
                                break 
                            text_content = content.get_text(strip=True)
                            if text_content:
                                value_parts.append(text_content)
                
                value = ' '.join(value_parts).replace(' , ', ', ').strip()
                
                if key in scraped_data:
                    if isinstance(scraped_data[key], list):
                        scraped_data[key].append(value)
                    else:
                        scraped_data[key] = [scraped_data[key], value]
                else:
                    scraped_data[key] = value
            # else:
                # print(f"Could not find key_element in item: {item.prettify()}")
        
        if not scraped_data:
            print(f"No data extracted from property_elements for {url}, though elements were found.")

    except requests.exceptions.RequestException as e:
        print(f"Error fetching URL {url}: {e}")
    except Exception as e:
        print(f"Error parsing data for {url}: {e}")
    return scraped_data

def map_data_to_schema(scraped_data: dict, plant_url: str) -> dict:
    """
    Maps scraped data to the database schema.
    """
    mapped_data = {}

    try:
        match = re.search(r'/wiki/([^/]+)$', plant_url)
        if match:
            botanical_name_url = match.group(1).replace('_', ' ')
            mapped_data['botanical_name'] = botanical_name_url
        else: 
            # Fallback to scraped data if URL parsing fails
            # Order of preference for botanical name keys
            b_name_keys = ['Has taxonomy name', 'Taxonomy name', 'Binomial name']
            for bk in b_name_keys:
                if scraped_data.get(bk):
                    mapped_data['botanical_name'] = scraped_data.get(bk)
                    break
            if 'botanical_name' not in mapped_data:
                 mapped_data['botanical_name'] = "Unknown"

    except Exception as e:
        print(f"Error parsing botanical name from URL {plant_url} or scraped data: {e}")
        mapped_data['botanical_name'] = "Error_Parsing_Botanical_Name"

    mapped_data['common_name'] = scraped_data.get('Has common name')
    
    # Plant Type: Could be 'Is deciduous or evergreen' or 'Has type'
    plant_type_raw = scraped_data.get('Is deciduous or evergreen') or scraped_data.get('Has type')
    if isinstance(plant_type_raw, list): # Take the first one if it's a list
        plant_type_raw = plant_type_raw[0] if plant_type_raw else None
    mapped_data['plant_type'] = plant_type_raw


    # Growth Habit: Combine 'Has habit', 'Mature Size' (Height x Width)
    growth_parts = []
    habit_value = scraped_data.get('Has habit')
    if habit_value:
        growth_parts.append(habit_value if isinstance(habit_value, str) else ', '.join(habit_value))

    mature_height = scraped_data.get('Has mature height')
    mature_width = scraped_data.get('Has mature width')
    size_unit = scraped_data.get('Uses mature size measurement unit', 'm') 
    if isinstance(mature_height, list): mature_height = mature_height[0] # take first if list
    if isinstance(mature_width, list): mature_width = mature_width[0]
    if isinstance(size_unit, list): size_unit = size_unit[0]


    if mature_height and mature_width:
        growth_parts.append(f"Size: {mature_height} x {mature_width} {size_unit}")
    elif mature_height:
        growth_parts.append(f"Height: {mature_height} {size_unit}")
    
    mapped_data['growth_habit'] = ', '.join(growth_parts) if growth_parts else None


    sun_exposure_raw = scraped_data.get('Has sun preference')
    mapped_data['sun_exposure'] = sun_exposure_raw[0] if isinstance(sun_exposure_raw, list) else sun_exposure_raw
    
    water_needs_raw = scraped_data.get('Has water requirements')
    mapped_data['water_needs'] = water_needs_raw[0] if isinstance(water_needs_raw, list) else water_needs_raw


    # Soil Preferences
    soil_prefs_list = []
    ph_pref_raw = scraped_data.get('Has soil ph preference')
    if ph_pref_raw:
        ph_pref = ph_pref_raw[0] if isinstance(ph_pref_raw, list) else ph_pref_raw
        soil_prefs_list.append(f"pH: {ph_pref.replace(' and ', ', ') if isinstance(ph_pref, str) else ph_pref}")
    
    texture_prefs = []
    # Specific texture properties like "Has soil tesandyture preference"
    # These keys are like "Has soil te[texture]ture preference"
    # The value for these is often the texture itself or a confirmation like "Yes"
    # We will prioritize the texture name from the key if value is just "Yes" or similar
    possible_textures = ["sandy", "loamy", "clay", "heavy clay"]
    for pt in possible_textures:
        texture_key_full = f"Has soil te{pt}ture preference" 
        if texture_key_full in scraped_data:
            # The value might be "Yes", or the texture name itself. We prefer the name from key.
            texture_prefs.append(pt.capitalize()) 

    if not texture_prefs and scraped_data.get('Has soil texture'): # Fallback to a general soil texture property
         general_texture = scraped_data.get('Has soil texture')
         general_texture = general_texture[0] if isinstance(general_texture, list) else general_texture
         if general_texture:
            texture_prefs.extend(re.split(r'\s+and\s+|\s*,\s*', general_texture))
    
    if texture_prefs:
        soil_prefs_list.append(f"Texture: {', '.join(sorted(list(set(texture_prefs))))}")

    water_ret_raw = scraped_data.get('Has soil water retention preference')
    if water_ret_raw:
        water_ret = water_ret_raw[0] if isinstance(water_ret_raw, list) else water_ret_raw
        soil_prefs_list.append(f"Water Retention: {water_ret}")
    
    mapped_data['soil_preferences'] = '; '.join(soil_prefs_list) if soil_prefs_list else None

    hardiness_raw = scraped_data.get('Has hardiness zone')
    mapped_data['hardiness_zones'] = hardiness_raw[0] if isinstance(hardiness_raw, list) else hardiness_raw


    # Edible Parts & Uses / Medicinal Uses & Parts
    def process_multi_value_field(data, key_base_name):
        # key_base_name could be 'edible' or 'medicinal'
        info = []
        
        # Property like "Has edible part Fruit and Seeds" or list of parts
        part_values = data.get(f'Has {key_base_name} part')
        if part_values:
            if not isinstance(part_values, list): part_values = [part_values]
            for item_val in part_values:
                info.extend(re.split(r'\s+and\s+|\s*,\s*', item_val))
        
        # Property like "Has edible use Jam, Jelly" or list of uses
        use_values = data.get(f'Has {key_base_name} use')
        if use_values:
            if not isinstance(use_values, list): use_values = [use_values]
            for item_val in use_values:
                info.extend(re.split(r'\s+and\s+|\s*,\s*', item_val))
        
        # Filter out empty strings that might result from splitting
        return '; '.join(sorted(list(set(filter(None, info))))) if info else None

    mapped_data['edible_parts'] = process_multi_value_field(scraped_data, 'edible')
    mapped_data['medicinal_uses'] = process_multi_value_field(scraped_data, 'medicinal')

    # Boolean fields
    is_n_fixer_prop = scraped_data.get('Is nitrogen fixer') # Explicit property
    functions_as_prop = scraped_data.get('Functions as', '') # Can be string or list
    
    n_fixer_flag = 0
    if is_n_fixer_prop and (is_n_fixer_prop == 'Yes' or (isinstance(is_n_fixer_prop, list) and 'Yes' in is_n_fixer_prop)):
        n_fixer_flag = 1
    elif isinstance(functions_as_prop, list) and 'Nitrogen fixer' in functions_as_prop:
        n_fixer_flag = 1
    elif isinstance(functions_as_prop, str) and 'Nitrogen fixer' == functions_as_prop: # Exact match
        n_fixer_flag = 1
    mapped_data['nitrogen_fixing'] = n_fixer_flag

    pollinator_flag = 0
    if isinstance(functions_as_prop, list):
        if any(pa_term in item for item in functions_as_prop for pa_term in ['Bee attractor', 'Pollinator attractor']):
            pollinator_flag = 1
    elif isinstance(functions_as_prop, str):
        if 'Bee attractor' in functions_as_prop or 'Pollinator attractor' in functions_as_prop:
            pollinator_flag = 1
    mapped_data['pollinator_attractant'] = pollinator_flag
    
    # Other Ecological Functions
    other_eco_funcs_list = []
    if isinstance(functions_as_prop, list):
        for func in functions_as_prop:
            is_n_fixer_func = ('Nitrogen fixer' == func)
            is_pollinator_func = ('Bee attractor' in func or 'Pollinator attractor' in func)
            
            if not (is_n_fixer_func and n_fixer_flag == 1) and \
               not (is_pollinator_func and pollinator_flag == 1):
                other_eco_funcs_list.append(func)
    elif isinstance(functions_as_prop, str): # Single string function
        is_n_fixer_func = ('Nitrogen fixer' == functions_as_prop)
        is_pollinator_func = ('Bee attractor' in functions_as_prop or 'Pollinator attractor' in functions_as_prop)
        if not (is_n_fixer_func and n_fixer_flag == 1) and \
           not (is_pollinator_func and pollinator_flag == 1) and \
           functions_as_prop: # ensure it's not empty
             other_eco_funcs_list.append(functions_as_prop)
             
    mapped_data['other_ecological_functions'] = '; '.join(sorted(list(set(other_eco_funcs_list)))) if other_eco_funcs_list else None
        
    # Notes
    notes_parts = []
    if scraped_data.get('Native climate zones'):
         notes_parts.append(f"Native climate: {scraped_data.get('Native climate zones')}")
    if scraped_data.get('Adapted climate zones'):
         notes_parts.append(f"Adapted climate: {scraped_data.get('Adapted climate zones')}")
    
    lifecycle_raw = scraped_data.get('Has lifecycle type')
    if lifecycle_raw:
        notes_parts.append(f"Lifecycle: {lifecycle_raw[0] if isinstance(lifecycle_raw, list) else lifecycle_raw}")

    family_raw = scraped_data.get('Belongs to family')
    if family_raw:
        notes_parts.append(f"Family: {family_raw[0] if isinstance(family_raw, list) else family_raw}")

    genus_raw = scraped_data.get('Belongs to genus')
    if genus_raw:
        notes_parts.append(f"Genus: {genus_raw[0] if isinstance(genus_raw, list) else genus_raw}")

    mapped_data['notes'] = '; '.join(notes_parts) if notes_parts else None
    
    # Ensure all DB fields are present, even if None, for database insertion
    db_fields = [
        'botanical_name', 'common_name', 'plant_type', 'growth_habit',
        'sun_exposure', 'water_needs', 'soil_preferences', 'hardiness_zones',
        'edible_parts', 'medicinal_uses', 'nitrogen_fixing',
        'pollinator_attractant', 'other_ecological_functions', 'notes'
    ]
    for field in db_fields:
        if field not in mapped_data:
            mapped_data[field] = None
            
    return mapped_data

def insert_plant_data(db_path: str, plant_data: dict):
    conn = None
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()

        if plant_data.get('botanical_name') is None or plant_data.get('botanical_name') == "Unknown" or plant_data.get('botanical_name') == "Error_Parsing_Botanical_Name":
            print(f"Skipping insertion: botanical_name is invalid for data: {plant_data.get('common_name', 'N/A')}")
            return

        columns = []
        values_placeholders = []
        actual_values = []

        for key, value in plant_data.items():
            columns.append(key)
            values_placeholders.append('?')
            actual_values.append(value)
        
        sql = f"INSERT OR IGNORE INTO plants ({', '.join(columns)}) VALUES ({', '.join(values_placeholders)})"
        
        cursor.execute(sql, tuple(actual_values))
        conn.commit()
        
        if cursor.rowcount > 0:
            print(f"Data inserted for {plant_data.get('botanical_name')}")
        else:
            cursor.execute("SELECT 1 FROM plants WHERE botanical_name = ?", (plant_data.get('botanical_name'),))
            if cursor.fetchone():
                print(f"Data for {plant_data.get('botanical_name')} already exists.")
            else:
                print(f"Data for {plant_data.get('botanical_name')} failed to insert (not a duplicate, check constraints or data).")

    except sqlite3.IntegrityError as e:
         print(f"Database integrity error for {plant_data.get('botanical_name')}: {e}. (e.g. NOT NULL constraint failed).")
    except sqlite3.Error as e:
        print(f"Database error for {plant_data.get('botanical_name')}: {e}")
    except Exception as e:
        print(f"An unexpected error in insert_plant_data for {plant_data.get('botanical_name')}: {e}")
    finally:
        if conn:
            conn.close()

def main():
    plant_urls = [
        "https://practicalplants.org/wiki/Malus_domestica",
        "https://practicalplants.org/wiki/Allium_sativum",
        "https://practicalplants.org/wiki/Lavandula_angustifolia",
        "https://practicalplants.org/wiki/Trifolium_repens" 
    ]
    
    conn_check = None
    try:
        conn_check = sqlite3.connect(DB_PATH)
        cursor = conn_check.cursor()
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='plants';")
        if not cursor.fetchone():
            print(f"Error: 'plants' table does not exist in {DB_PATH}. Run database_setup.py first.")
            return
    except sqlite3.Error as e:
        print(f"Error connecting to {DB_PATH} to check for table: {e}")
        return
    finally:
        if conn_check:
            conn_check.close()

    for url in plant_urls:
        print(f"\nProcessing URL: {url}")
        raw_data = scrape_plant_data(url)
        
        if not raw_data:
            print(f"No raw data scraped for {url}. Skipping.")
            continue

        print(f"\n--- Raw Scraped Data for {url.split('/')[-1]} ({len(raw_data)} items) ---")
        # for key, value in raw_data.items():
        #     print(f"  {key}: {value}") # Commented out for brevity in test runs
        
        mapped_data = map_data_to_schema(raw_data, url)
        print(f"\n--- Mapped Data for {mapped_data.get('botanical_name')} ---")
        # for key, value in mapped_data.items():
        #     print(f"  {key}: {value}") # Commented out for brevity
        
        print(f"\n--- Inserting Data for {mapped_data.get('botanical_name')} ---")
        insert_plant_data(DB_PATH, mapped_data)

if __name__ == '__main__':
    main()
