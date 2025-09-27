#!/usr/bin/env bash
set -euo pipefail

# FTPS recursive inventory using curl (explicit TLS)
# Outputs:
# - remote_inventory_tree.txt (human-readable tree)
# - remote_inventory.csv (path,type,size,mtime)

MAX_DEPTH="${MAX_DEPTH:-3}"
START_PATH="${START_PATH:-/}"

# Load .env if present (expects FTP_SERVER, FTP_USER, FTP_PASSWORD)
if [[ -f .env ]]; then
  # shellcheck disable=SC1091
  set -a; source .env; set +a
fi

: "${FTP_SERVER:?FTP_SERVER required}"
: "${FTP_USER:?FTP_USER required}"
: "${FTP_PASSWORD:?FTP_PASSWORD required}"

OUT_TREE="remote_inventory_tree.txt"
OUT_CSV="remote_inventory.csv"

echo "# Remote inventory for $FTP_SERVER (start: $START_PATH, depth: $MAX_DEPTH)" > "$OUT_TREE"
echo "path,type,size,mtime" > "$OUT_CSV"

indent() { local n=$1; printf '%*s' $((n*2)) ""; }

# List names in a directory using NLST
list_long() {
  local path="$1"
  # Ensure directories end with '/'
  if [[ "$path" != "/" && "$path" != */ ]]; then
    path="$path/"
  fi
  # Use LIST (long format) once per directory
  curl -sS --ssl-reqd "ftp://$FTP_USER:$FTP_PASSWORD@$FTP_SERVER$path" || true
}

declare -a Q
declare -a D

Q+=("$START_PATH")
D+=(0)

while ((${#Q[@]})); do
  path="${Q[0]}"; Q=(${Q[@]:1})
  depth="${D[0]}"; D=(${D[@]:1})

  # Normalize root path formatting
  [[ "$path" == "/" ]] || path="${path%/}"

  # Print directory header in tree
  indent "$depth" >> "$OUT_TREE"; echo "📁 $path" >> "$OUT_TREE"

  # Get long listing and parse
  while IFS= read -r line; do
    # Expect lines like: drwxr-xr-x  2 user group   4096 Aug 14 12:10 dirname
    # or: -rw-r--r--  1 user group    123 Aug 14 12:10 filename
    [[ -z "$line" ]] && continue
    # Some FTP servers send Windows-style; skip unsupported formats
    perms=$(echo "$line" | awk '{print $1}')
    firstchar=${perms:0:1}
    # Name is from field 9..NF; handle spaces in names
    name=$(echo "$line" | awk '{if (NF>=9){for(i=9;i<=NF;i++){printf (i==9?"%s":" %s"),$i}; print ""}else{print $NF}}')
    [[ -z "$name" || "$name" == "." || "$name" == ".." ]] && continue

    # Size heuristic: usually field 5
    size=$(echo "$line" | awk '{print $5}')

    if [[ "$firstchar" == "d" ]]; then
      indent $((depth+1)) >> "$OUT_TREE"; echo "📂 $name/" >> "$OUT_TREE"
      if [[ "$path" == "/" ]]; then
        child="/$name"
      else
        child="$path/$name"
      fi
      echo "$child,dir,," >> "$OUT_CSV"
      if (( depth+1 < MAX_DEPTH )); then
        Q+=("$child")
        D+=($((depth+1)))
      fi
    elif [[ "$firstchar" == "-" || "$firstchar" == "l" ]]; then
      indent $((depth+1)) >> "$OUT_TREE"; echo "📄 $name${size:+ (${size}B)}" >> "$OUT_TREE"
      if [[ "$path" == "/" ]]; then
        child="/$name"
      else
        child="$path/$name"
      fi
      echo "$child,file,${size:-}," >> "$OUT_CSV"
    else
      # Unknown line format; just print raw
      indent $((depth+1)) >> "$OUT_TREE"; echo "❓ $line" >> "$OUT_TREE"
    fi
  done < <(list_long "$path")
done

echo "Done. Wrote $OUT_TREE and $OUT_CSV"
