#!/usr/bin/env bash
#
# sync-wp-repo-screenshots.sh
#
# Promote PNGs captured by tests/e2e/cypress/e2e/wp-repo-screenshots.cy.js to
# the plugin root as screenshot-{N}.png so they ship with the WP.org release
# zip and show up under readme.txt's "== Screenshots ==" section.
#
# Cypress writes screenshots to:
#   tests/e2e/cypress/screenshots/wp-repo-screenshots.cy.js/{NN-slug}.png
#
# This script sorts those files by their leading two-digit prefix and copies
# them in order to:
#   screenshot-1.png, screenshot-2.png, ...
#
# Usage:
#   ./bin/sync-wp-repo-screenshots.sh          # promote captured screenshots
#   ./bin/sync-wp-repo-screenshots.sh --check  # verify counts match readme.txt
#
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"
SRC_DIR="${PLUGIN_ROOT}/tests/e2e/cypress/screenshots/wp-repo-screenshots.cy.js"

die() { echo "Error: $*" >&2; exit 1; }

MODE="sync"
if [[ "${1:-}" == "--check" ]]; then
	MODE="check"
fi

[[ -d "$SRC_DIR" ]] || die "No captured screenshots at ${SRC_DIR}. Run: npx cypress run --spec tests/e2e/cypress/e2e/wp-repo-screenshots.cy.js"

# Collect captured PNGs sorted by filename (the leading NN- prefix drives order).
shopt -s nullglob
CAPTURED=( "${SRC_DIR}"/*.png )
shopt -u nullglob

(( ${#CAPTURED[@]} > 0 )) || die "No .png files found under ${SRC_DIR}."

IFS=$'\n' SORTED=( $( printf '%s\n' "${CAPTURED[@]}" | sort ) )
unset IFS

if [[ "$MODE" == "check" ]]; then
	README="${PLUGIN_ROOT}/readme.txt"
	[[ -f "$README" ]] || die "readme.txt not found at plugin root."
	# Count numbered entries in the Screenshots section (`N. description` lines
	# between `== Screenshots ==` and the next `==` heading).
	README_COUNT="$(
		awk '
			/^== Screenshots ==/ { in_section = 1; next }
			in_section && /^== / { in_section = 0 }
			in_section && /^[0-9]+\. / { count++ }
			END { print count + 0 }
		' "$README"
	)"
	echo "Captured PNGs:         ${#SORTED[@]}"
	echo "readme.txt entries:    ${README_COUNT}"
	if [[ "${#SORTED[@]}" -ne "${README_COUNT}" ]]; then
		die "Count mismatch — update readme.txt '== Screenshots ==' to match captured PNGs."
	fi
	echo "OK: screenshot count matches readme.txt."
	exit 0
fi

# Promote each captured PNG to screenshot-{N}.png at plugin root.
i=1
for src in "${SORTED[@]}"; do
	dest="${PLUGIN_ROOT}/screenshot-${i}.png"
	cp -f "$src" "$dest"
	echo "Promoted $(basename "$src") -> screenshot-${i}.png"
	i=$(( i + 1 ))
done

echo "Done. ${#SORTED[@]} screenshot(s) copied to plugin root."
echo "Next: verify with './bin/sync-wp-repo-screenshots.sh --check' and commit the PNGs + any readme.txt updates."
