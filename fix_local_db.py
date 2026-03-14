
import sqlite3
import os

db_path = os.path.join("data", "wishlist.db")

print(f"Versuche lokale Datenbank zu reparieren: {db_path}")

if not os.path.exists(db_path):
    print(f"FEHLER: Datei {db_path} nicht gefunden!")
else:
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        # Prüfen ob Spalte existiert
        cursor.execute("PRAGMA table_info(wl_wishes)")
        columns = [column[1] for column in cursor.fetchall()]
        
        if "price" not in columns:
            print("Spalte \"price\" fehlt. Füge hinzu...")
            cursor.execute("ALTER TABLE wl_wishes ADD COLUMN price REAL DEFAULT 0.0")
            conn.commit()
            print("✅ ERFOLG: Spalte \"price\" wurde lokal hinzugefügt!")
        else:
            print("✅ INFO: Spalte \"price\" existiert bereits lokal.")
            
        conn.close()
    except Exception as e:
        print(f"❌ FEHLER: {e}")

input("\nDrücken Sie Enter zum Beenden...")

