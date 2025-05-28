import sqlite3

DB_PATH = 'permaculture_plants.db'

# 1. Define Python dictionaries for the plants
plants_data = [
    {
        'botanical_name': 'Malus domestica',
        'common_name': 'Apple',
        'plant_type': 'Tree',
        'growth_habit': 'Medium deciduous tree, typically 3-10m tall, can be dwarfed by rootstock.',
        'sun_exposure': 'Full sun',
        'water_needs': 'Moderate; consistent moisture needed, especially during fruiting.',
        'soil_preferences': 'Well-drained loam or sandy loam; pH 5.5-7.0. Tolerates a range but dislikes waterlogged conditions.',
        'hardiness_zones': '3-8 (varies by cultivar)',
        'edible_parts': 'Fruit (raw, cooked); Flowers (sometimes used in salads or as garnish).',
        'medicinal_uses': 'Fruit (pectin for digestive health, antioxidants); Bark (historically for fevers).',
        'nitrogen_fixing': 0,
        'pollinator_attractant': 1, # Flowers attract bees
        'other_ecological_functions': 'Provides nectar and pollen for bees and other insects; habitat and food for birds/wildlife (fruit).',
        'notes': 'Thousands of cultivars exist, varying in flavor, storage, and disease resistance. Requires cross-pollination for most varieties.'
    },
    {
        'botanical_name': 'Allium sativum',
        'common_name': 'Garlic',
        'plant_type': 'Herb',
        'growth_habit': 'Bulbous herbaceous perennial, typically grows up to 60cm tall. Produces a scape (flower stalk) if not removed.',
        'sun_exposure': 'Full sun',
        'water_needs': 'Moderate during bulb formation; reduce water as leaves begin to yellow before harvest. Avoid waterlogged soil.',
        'soil_preferences': 'Rich, well-drained loamy soil; pH 6.0-7.5. Needs good fertility for large bulbs.',
        'hardiness_zones': '4-9 (some varieties hardier)',
        'edible_parts': 'Bulbs (cloves); Leaves (young leaves, aka garlic greens); Scapes (flower stalks, aka garlic scapes).',
        'medicinal_uses': 'Bulb (antibacterial, antiviral, antifungal, immune support, cardiovascular health).',
        'nitrogen_fixing': 0,
        'pollinator_attractant': 1, # If allowed to flower, attracts bees and other beneficial insects.
        'other_ecological_functions': 'Can act as a pest repellent for some garden pests (e.g., aphids, Japanese beetles). Dynamic accumulator of sulfur.',
        'notes': 'Many types: hardneck (produces scapes, generally more cold-hardy) and softneck (no scape, better for braiding, longer storage). Plant cloves in fall for best results in most climates.'
    },
    {
        'botanical_name': 'Lavandula angustifolia',
        'common_name': 'Lavender (English Lavender)',
        'plant_type': 'Shrub',
        'growth_habit': 'Small evergreen shrub, typically 0.5-1m tall and wide, with a mounding habit.',
        'sun_exposure': 'Full sun (essential for good flowering and oil production).',
        'water_needs': 'Low to moderate; drought-tolerant once established. Prefers dry conditions over wet feet.',
        'soil_preferences': 'Well-drained, sandy or gravelly soil, slightly alkaline (pH 6.5-7.5). Dislikes acidic or heavy clay soils.',
        'hardiness_zones': '5-9',
        'edible_parts': 'Flowers (culinary, e.g., in teas, baked goods, syrups); Leaves (less common, can be used sparingly).',
        'medicinal_uses': 'Flowers & Essential Oil (calming, sedative, antiseptic, anti-inflammatory, headaches, burns, insect bites).',
        'nitrogen_fixing': 0,
        'pollinator_attractant': 1, # Highly attractive to bees, butterflies, and other pollinators.
        'other_ecological_functions': 'Repels some pests like moths, fleas, mosquitoes. Can be used for low hedging.',
        'notes': 'Many cultivars available (e.g., \'Hidcote\', \'Munstead\'). Prune after flowering to maintain shape and promote vigor. Harvest flowers just as they open.'
    }
]

# 2. Implement a function insert_manual_data(db_path, plant_list)
def insert_manual_data(db_path: str, plant_list: list):
    """
    Inserts a list of plant dictionaries into the SQLite database.
    """
    conn = None
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()

        for plant_data in plant_list:
            if not plant_data.get('botanical_name'):
                print(f"Skipping entry due to missing botanical_name: {plant_data.get('common_name', 'N/A')}")
                continue

            columns = ', '.join(plant_data.keys())
            placeholders = ', '.join('?' * len(plant_data))
            sql = f"INSERT OR IGNORE INTO plants ({columns}) VALUES ({placeholders})"
            
            try:
                cursor.execute(sql, tuple(plant_data.values()))
                if cursor.rowcount > 0:
                    print(f"Data inserted for {plant_data['botanical_name']}.")
                else:
                    # Check if it was an IGNORE due to existing entry
                    cursor.execute("SELECT 1 FROM plants WHERE botanical_name = ?", (plant_data['botanical_name'],))
                    if cursor.fetchone():
                        print(f"Data for {plant_data['botanical_name']} already exists (ignored).")
                    else:
                        # This case should ideally not be reached if INSERT OR IGNORE is working as expected
                        # unless there's a different constraint failure not caught by typical IntegrityError
                        print(f"Data for {plant_data['botanical_name']} was not inserted and does not seem to exist (check data/constraints).")
            except sqlite3.IntegrityError as ie:
                # This might catch issues if INSERT OR IGNORE somehow fails to handle a UNIQUE constraint,
                # or if other NOT NULL constraints are violated (though botanical_name is checked above).
                print(f"Integrity error inserting {plant_data['botanical_name']}: {ie}. May already exist or data violates schema.")
            except sqlite3.Error as e:
                print(f"SQLite error inserting {plant_data['botanical_name']}: {e}")

        conn.commit()
        print("\nManual data insertion process completed.")

    except sqlite3.Error as e:
        print(f"Database connection error: {e}")
    except Exception as e:
        print(f"An unexpected error occurred: {e}")
    finally:
        if conn:
            conn.close()

# 3. Main part of the script
if __name__ == '__main__':
    # First, ensure the database and table exist by running database_setup.py
    # This is typically done once, but for robustness in a script, one might check.
    # For this task, we assume database_setup.py has been run.
    
    print("Starting manual data entry...")
    
    # Check if table exists first as a safeguard
    conn_check = None
    try:
        conn_check = sqlite3.connect(DB_PATH)
        cursor = conn_check.cursor()
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='plants';")
        if not cursor.fetchone():
            print(f"CRITICAL ERROR: 'plants' table does not exist in {DB_PATH}.")
            print("Please run the database_setup.py script first.")
            # Exiting because the script cannot proceed without the table.
            exit(1) 
        print("'plants' table confirmed to exist.")
    except sqlite3.Error as e:
        print(f"CRITICAL ERROR: Could not connect to database {DB_PATH} to verify table: {e}")
        exit(1)
    finally:
        if conn_check:
            conn_check.close()

    insert_manual_data(DB_PATH, plants_data)
    print("Script finished.")
