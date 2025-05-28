import sqlite3

def setup_database():
    """Creates the permaculture_plants.db database and the plants table."""
    try:
        conn = sqlite3.connect('permaculture_plants.db')
        cursor = conn.cursor()

        cursor.execute('''
            CREATE TABLE IF NOT EXISTS plants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                botanical_name TEXT NOT NULL UNIQUE,
                common_name TEXT,
                plant_type TEXT,
                growth_habit TEXT,
                sun_exposure TEXT,
                water_needs TEXT,
                soil_preferences TEXT,
                hardiness_zones TEXT,
                edible_parts TEXT,
                medicinal_uses TEXT,
                nitrogen_fixing INTEGER,
                pollinator_attractant INTEGER,
                other_ecological_functions TEXT,
                notes TEXT
            )
        ''')

        conn.commit()
        print("Database and plants table created successfully.")

    except sqlite3.Error as e:
        print(f"Error creating database or table: {e}")

    finally:
        if conn:
            conn.close()

if __name__ == '__main__':
    setup_database()
