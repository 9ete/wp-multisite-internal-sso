#!/usr/bin/env bash
set -euo pipefail

# Parse command line arguments
QUIET=false
while getopts "q" opt; do
  case $opt in
    q)
      QUIET=true
      ;;
    \?)
      echo "Usage: $0 [-q]" >&2
      echo "  -q    Suppress output (quiet mode)" >&2
      exit 1
      ;;
  esac
done

die() { echo "Error: $*" >&2; exit 1; }

# Output function that respects quiet mode
output() {
  if [[ "$QUIET" == false ]]; then
    echo "$@"
  fi
}

# Script lives in <plugin-root>/bin
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"

# Default output directory inside plugin root currently "zips"
DIST_DIR="${DIST_DIR:-zips}"

# Allow override, otherwise auto-detect main plugin file in root.
MAIN_FILE="${MAIN_FILE:-}"

if [[ -n "$MAIN_FILE" ]]; then
  # If user provided a relative path, make it relative to plugin root.
  [[ "$MAIN_FILE" = /* ]] || MAIN_FILE="${PLUGIN_ROOT}/${MAIN_FILE}"
  [[ -f "$MAIN_FILE" ]] || die "MAIN_FILE not found: $MAIN_FILE"
else
  # Try the conventional <slug>.php in the plugin root; otherwise scan the root
  # for a file carrying the plugin header (glob loop — safe with spaces in path).
  _slug="$(basename "$PLUGIN_ROOT")"
  if [[ -f "${PLUGIN_ROOT}/${_slug}.php" ]]; then
    MAIN_FILE="${PLUGIN_ROOT}/${_slug}.php"
  else
    MAIN_FILE=""
    for _f in "${PLUGIN_ROOT}"/*.php; do
      [[ -f "$_f" ]] || continue
      if grep -qE "^[[:space:]]*\*?[[:space:]]*Plugin Name:" "$_f" && grep -qE "^[[:space:]]*\*?[[:space:]]*Version:" "$_f"; then
        MAIN_FILE="$_f"
        break
      fi
    done
    [[ -n "$MAIN_FILE" ]] || die "Could not locate a main plugin file in plugin root (looking for Plugin Name + Version headers). Set MAIN_FILE=..."
  fi
fi

# Determine slug from directory name (plugin root folder)
PLUGIN_SLUG="$(basename "$PLUGIN_ROOT")"

# Extract Version: x.y.z (supports both:
#  - " * Version: 0.0.48" (docblock style)
#  - "Version: 0.0.48" (header style)
PLUGIN_VERSION="$(
  awk '
    BEGIN { IGNORECASE=1 }
    /^[[:space:]]*(\*?[[:space:]]*)?Version[[:space:]]*:/ {
      sub(/^[[:space:]]*(\*?[[:space:]]*)?Version[[:space:]]*:[[:space:]]*/, "", $0)
      gsub(/\r/, "", $0)
      print $0
      exit
    }
  ' "$MAIN_FILE"
)"

[[ -n "$PLUGIN_VERSION" ]] || die "Could not determine Version from $(basename "$MAIN_FILE")"

ZIP_FILE_VERSIONED="${PLUGIN_SLUG}-${PLUGIN_VERSION}.zip"
ZIP_FILE_ARCHIVE="${PLUGIN_SLUG}-${PLUGIN_VERSION}-archive.zip"
TEMP_DIR="${PLUGIN_ROOT}/${DIST_DIR}/${PLUGIN_SLUG}-${PLUGIN_VERSION}"
WPORG_DIR="${PLUGIN_ROOT}/${DIST_DIR}/${PLUGIN_SLUG}"

mkdir -p "$TEMP_DIR"

# Build rsync excludes from .distignore (in plugin root)
RSYNC_EXCLUDES=(
  "--exclude=.distignore"
  "--exclude=.git/"
  "--exclude=.gitignore"
  "--exclude=__MACOSX/"
  "--exclude=.DS_Store"
  "--exclude=*.zip"
)

DISTIGNORE="${PLUGIN_ROOT}/.distignore"
if [[ -f "$DISTIGNORE" ]]; then
  while IFS= read -r raw || [[ -n "$raw" ]]; do
    line="$(printf '%s' "$raw" | tr -d '\r' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    [[ -z "$line" ]] && continue
    [[ "$line" =~ ^# ]] && continue
    RSYNC_EXCLUDES+=( "--exclude=${line}" )
  done < "$DISTIGNORE"
fi

# Stage files (versioned root)
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"

rsync -a --delete \
  "${RSYNC_EXCLUDES[@]}" \
  "${PLUGIN_ROOT}/" \
  "${TEMP_DIR}/"

# Stage files for WP.org zip (slug root)
rm -rf "$WPORG_DIR"
mkdir -p "$WPORG_DIR"

rsync -a --delete \
  "${RSYNC_EXCLUDES[@]}" \
  "${PLUGIN_ROOT}/" \
  "${WPORG_DIR}/"

# Zip
mkdir -p "${PLUGIN_ROOT}/${DIST_DIR}"
(
  cd "${PLUGIN_ROOT}/${DIST_DIR}"
  rm -f "$ZIP_FILE_VERSIONED" "$ZIP_FILE_ARCHIVE"
  zip -qr "$ZIP_FILE_VERSIONED" "${PLUGIN_SLUG}"
  zip -qr "$ZIP_FILE_ARCHIVE" "${PLUGIN_SLUG}-${PLUGIN_VERSION}"
)

# Verify zips and root folders
unzip -t "${PLUGIN_ROOT}/${DIST_DIR}/${ZIP_FILE_VERSIONED}" >/dev/null || die "Zip test failed: ${ZIP_FILE_VERSIONED}"
unzip -t "${PLUGIN_ROOT}/${DIST_DIR}/${ZIP_FILE_ARCHIVE}" >/dev/null || die "Zip test failed: ${ZIP_FILE_ARCHIVE}"

WPORG_FIRST_ENTRY="$(unzip -Z1 "${PLUGIN_ROOT}/${DIST_DIR}/${ZIP_FILE_VERSIONED}" | head -n 1)"
ARCHIVE_FIRST_ENTRY="$(unzip -Z1 "${PLUGIN_ROOT}/${DIST_DIR}/${ZIP_FILE_ARCHIVE}" | head -n 1)"

[[ "$WPORG_FIRST_ENTRY" == "${PLUGIN_SLUG}/"* ]] || die "WP.org zip root folder mismatch: expected ${PLUGIN_SLUG}/"
[[ "$ARCHIVE_FIRST_ENTRY" == "${PLUGIN_SLUG}-${PLUGIN_VERSION}/"* ]] || die "Archive zip root folder mismatch: expected ${PLUGIN_SLUG}-${PLUGIN_VERSION}/"

output "Created distribution package: ${PLUGIN_ROOT}/${DIST_DIR}/${ZIP_FILE_VERSIONED}"
output "Created archive package: ${PLUGIN_ROOT}/${DIST_DIR}/${ZIP_FILE_ARCHIVE}"

# Lastly, create a copy of the WP.org zip w/o the version in the plugin root, overwrite if one already exists.
cp -f "${PLUGIN_ROOT}/${DIST_DIR}/${ZIP_FILE_VERSIONED}" "${PLUGIN_ROOT}/${PLUGIN_SLUG}.zip"
output "Created copy without version: ${PLUGIN_ROOT}/${PLUGIN_SLUG}.zip"
