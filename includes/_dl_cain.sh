#!/bin/bash
MAP="cain"
BASE="https://reforger.recoil.org/map-tiles/${MAP}"
OUTDIR="tiles/${MAP}/LODS"
mkdir -p "$OUTDIR"
TOTAL=0; OK=0

for Z in 0 1 2 3 4 5; do
  case $Z in
    0) MAX=150 ;;
    1) MAX=75 ;;
    2) MAX=37 ;;
    3) MAX=18 ;;
    4) MAX=9 ;;
    5) MAX=4 ;;
  esac
  for X in $(seq 0 $MAX); do
    for Y in $(seq 0 $MAX); do
      URL="${BASE}/${Z}/${X}/${Y}/tile.jpg"
      DIR="${OUTDIR}/${Z}/${X}/${Y}"
      TOTAL=$((TOTAL + 1))
      [ -f "${DIR}/tile.jpg" ] && continue
      mkdir -p "$DIR"
      # timeout rápido de 3s para 404
      HTTP=$(curl -s -o "${DIR}/tile.jpg" -w "%{http_code}" --connect-timeout 3 --max-time 5 "$URL")
      if [ "$HTTP" = "200" ]; then
        OK=$((OK + 1))
        echo "OK $Z/$X/$Y ($OK)"
      else
        rm -f "${DIR}/tile.jpg"
        rmdir "$DIR" 2>/dev/null
      fi
    done
  done
done
echo "Cain/Kolguyev: TOTAL=$TOTAL OK=$OK"
