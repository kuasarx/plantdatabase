import sqlite3

DB_PATH = 'permaculture_plants.db'

def run_tests(db_path: str):
    """
    Runs a series of tests on the permaculture_plants.db database.
    """
    print(f"Starting database tests on {db_path}...")
    conn = None
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        print("Successfully connected to the database.\n")

        # Test 1: Count total rows
        print("--- Test 1: Count total rows ---")
        cursor.execute("SELECT COUNT(*) FROM plants")
        total_rows = cursor.fetchone()[0]
        print(f"Total rows in 'plants' table: {total_rows}")
        if total_rows >= 3: # Should be at least 3 if manual data entry was successful
            print("Test 1 PASSED (found 3 or more rows).\n")
        else:
            print(f"Test 1 FAILED (expected at least 3 rows, found {total_rows}).\n")

        # Test 2: Retrieve all data for a specific plant (Malus domestica)
        print("--- Test 2: Retrieve all data for 'Malus domestica' ---")
        cursor.execute("SELECT * FROM plants WHERE botanical_name = ?", ('Malus domestica',))
        malus_data = cursor.fetchone()
        if malus_data:
            print(f"Data for Malus domestica: {malus_data}")
            # You could add more assertions here, e.g., check common_name
            if malus_data[2] == 'Apple': # common_name is the 3rd column (index 2)
                 print("Test 2 PASSED (found 'Malus domestica' with correct common name).\n")
            else:
                print(f"Test 2 FAILED (found 'Malus domestica', but common_name was '{malus_data[2]}', expected 'Apple').\n")
        else:
            print("Test 2 FAILED ('Malus domestica' not found).\n")

        # Test 3: Retrieve plants by a specific criterion (pollinator attractants)
        print("--- Test 3: Retrieve pollinator attractants (botanical_name, common_name) ---")
        cursor.execute("SELECT botanical_name, common_name FROM plants WHERE pollinator_attractant = 1")
        pollinator_plants = cursor.fetchall()
        if pollinator_plants:
            print(f"Pollinator attractant plants found: {len(pollinator_plants)}")
            for plant in pollinator_plants:
                print(f"  - {plant[0]} ({plant[1]})")
            # All manually added plants are pollinator attractants
            if len(pollinator_plants) >= 3:
                 print("Test 3 PASSED (found 3 or more pollinator attractants).\n")
            else:
                print(f"Test 3 FAILED (expected at least 3 pollinator attractants, found {len(pollinator_plants)}).\n")
        else:
            print("Test 3 FAILED (No pollinator attractant plants found).\n")

        # Test 4: Check for a plant not expected to be there
        print("--- Test 4: Check for a non-existent plant ---")
        cursor.execute("SELECT * FROM plants WHERE botanical_name = ?", ('NonExistentPlantus',))
        non_existent_data = cursor.fetchone()
        if non_existent_data is None:
            print("No data found for 'NonExistentPlantus', as expected.")
            print("Test 4 PASSED.\n")
        else:
            print(f"Test 4 FAILED (found data for 'NonExistentPlantus': {non_existent_data}).\n")
            
        # Test 5: Check nitrogen fixers
        print("--- Test 5: Retrieve nitrogen-fixing plants (botanical_name, common_name) ---")
        # Assuming Trifolium repens was added by scraper and is a nitrogen fixer,
        # or if manual data includes one. Currently, manual data has none.
        cursor.execute("SELECT botanical_name, common_name FROM plants WHERE nitrogen_fixing = 1")
        nitrogen_fixers = cursor.fetchall()
        if nitrogen_fixers:
            print(f"Nitrogen-fixing plants found: {len(nitrogen_fixers)}")
            for plant in nitrogen_fixers:
                print(f"  - {plant[0]} ({plant[1]})")
            print("Test 5 Information: Listing nitrogen fixers found (no strict pass/fail without knowing scraper status).\n")
        else:
            print("No nitrogen-fixing plants found in the current dataset.")
            print("Test 5 Information: No nitrogen fixers found (this is expected if only manual data is present and has no N-fixers).\n")


    except sqlite3.Error as e:
        print(f"Database error during tests: {e}")
    except Exception as e:
        print(f"An unexpected error occurred during tests: {e}")
    finally:
        if conn:
            conn.close()
            print("Database connection closed.")
        print("\nDatabase tests completed.")

if __name__ == '__main__':
    # Ensure database is set up and populated by manual_data_entry.py before running tests
    # This would typically be part of a larger test setup script or CI process.
    # For this task, we assume manual_data_entry.py has been run.
    
    # First, check if the database file exists.
    # And if the table 'plants' exists.
    conn_check = None
    try:
        conn_check = sqlite3.connect(DB_PATH)
        cursor = conn_check.cursor()
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='plants';")
        if not cursor.fetchone():
            print(f"CRITICAL ERROR: 'plants' table does not exist in {DB_PATH}.")
            print("Please run database_setup.py and manual_data_entry.py scripts first.")
            exit(1) # Exit if the table isn't there, tests cannot run.
        print(f"Confirmed 'plants' table exists in {DB_PATH}.")
    except sqlite3.Error as e:
        print(f"CRITICAL ERROR: Could not connect to database {DB_PATH} to verify table: {e}")
        exit(1) # Exit if DB connection fails.
    finally:
        if conn_check:
            conn_check.close()
            
    run_tests(DB_PATH)
