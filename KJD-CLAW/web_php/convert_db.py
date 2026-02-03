import re
import json
import os

sql_path = 'kubajadesigns_eu_.sql'
output_path = 'data/products.json'

if not os.path.exists('data'):
    os.makedirs('data')

products = []

with open(sql_path, 'r', encoding='utf-8', errors='ignore') as f:
    in_values = False
    for line in f:
        line = line.strip()
        if line.startswith("INSERT INTO `product`"):
            in_values = True
            # Might contain values on same line
            if "VALUES" in line and line.endswith(");"):
                # Single line insert?
                pass
            continue
        
        if in_values:
            if line.startswith("(") or line.startswith("INSERT"): # Some dumps repeat INSERT
                # It's a row
                # (1, 'Name', ...
                
                # Manual CSV parsing again
                parts = []
                current = ''
                in_quote = False
                escape = False
                
                # Remove leading ( and trailing ), or );
                clean_line = line.lstrip('(').rstrip(',;')
                if clean_line.endswith(')'): clean_line = clean_line[:-1]
                
                for char in clean_line:
                    if escape:
                        current += char
                        escape = False
                    elif char == '\\':
                        escape = True
                    elif char == "'" and not escape:
                        in_quote = not in_quote
                    elif char == ',' and not in_quote:
                        parts.append(current.strip())
                        current = ''
                    else:
                        current += char
                parts.append(current.strip())
                
                if len(parts) > 13:
                    try:
                        pid = parts[0]
                        name = parts[1].strip("'")
                        price = parts[7].strip("'")
                        img_raw = parts[13].strip("'")
                        
                        imgs = img_raw.split(',')
                        img = imgs[0].strip()
                        if not img: img = 'images/lampy.webp'
                        elif img.startswith('https'): pass
                        else: img = 'https://kubajadesigns.eu/uploads/products/' + img
                        
                        products.append({
                            'id': pid,
                            'name': name,
                            'price': price,
                            'image': img
                        })
                    except: pass
            
            if line.endswith(";"):
                in_values = False # End of block

print(f"Parsed {len(products)} products.")
with open(output_path, 'w', encoding='utf-8') as f:
    json.dump(products, f, indent=2, ensure_ascii=False)
