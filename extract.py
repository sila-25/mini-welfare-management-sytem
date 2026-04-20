import os
import json

# ==============================
# CONFIGURATION
# ==============================

PROJECT_NAME = "careway"

HTDOCS_PATH = r"C:\xampp\htdocs"  # Change if needed

OUTPUT_FILE = "structure.json"


# ==============================
# FUNCTION TO BUILD STRUCTURE
# ==============================

def build_structure(path):
    structure = {}

    for item in sorted(os.listdir(path)):
        full_path = os.path.join(path, item)

        if os.path.isdir(full_path):
            structure[item] = build_structure(full_path)
        else:
            structure[item] = "file"

    return structure


# ==============================
# MAIN FUNCTION
# ==============================

def extract_structure():
    base_path = os.path.join(HTDOCS_PATH, PROJECT_NAME)

    if not os.path.exists(base_path):
        print(f"[ERROR] Folder not found: {base_path}")
        return

    print(f"[INFO] Scanning: {base_path}")

    structure = build_structure(base_path)

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        json.dump(structure, f, indent=4)

    print(f"\n✔ Structure exported to {OUTPUT_FILE}")


# ==============================
# RUN SCRIPT
# ==============================

if __name__ == "__main__":
    extract_structure()