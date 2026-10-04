#!/usr/bin/env bash
# Copy the Vecteezy photos from ~/Downloads into the web and mobile apps, resized for fast loading.
#   shop.jpg   <- "blurring-of-nuts-screws..." (Yelena Akulova)
#   hero.jpg   <- "screws-with-plastic-nozzles..." (Vitalii Borkovskyi)
#   cement.jpg <- "hand-of-worker-plastering-cement..." (papan saenkutrueang)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:-$HOME/Downloads}"
declare -A MAP=([shop]="*nuts*screws*spare*|*19771700*|*shop-window*" [hero]="*screws*plastic*nozzles*|*72945840*" [cement]="*plastering*cement*|*8423322*")
for name in shop hero cement; do
  file=""
  IFS='|' read -ra pats <<< "${MAP[$name]}"
  for p in "${pats[@]}"; do
    f=$(find "$SRC" -maxdepth 2 -iname "$p" \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' \) -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | cut -d' ' -f2-)
    [ -n "$f" ] && { file="$f"; break; }
  done
  if [ -z "$file" ]; then echo "missing: $name"; continue; fi
  ffmpeg -loglevel error -y -i "$file" -vf "scale='min(1920,iw)':-2" -q:v 4 "$ROOT/backend/public/images/$name.jpg"
  ffmpeg -loglevel error -y -i "$file" -vf "scale='min(1200,iw)':-2" -q:v 5 "$ROOT/mobile/assets/images/$name.jpg"
  echo "ok: $name <- $(basename "$file") ($(du -h "$ROOT/backend/public/images/$name.jpg" | cut -f1) web, $(du -h "$ROOT/mobile/assets/images/$name.jpg" | cut -f1) mobile)"
done
