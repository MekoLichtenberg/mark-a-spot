#!/bin/bash
#
# Import Jurisdiction Configuration for Mark-a-Spot
#
# Usage:
#   ./setup/import-jurisdiction.sh           # Create new jurisdiction
#   ./setup/import-jurisdiction.sh GROUP_ID  # Update existing group ID
#
# This script reads jurisdiction-config.json and creates/updates a Group entity
# in Drupal with the configuration. This ensures the config is version-controlled
# in git and can be re-imported after database loss.
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CONFIG_FILE="$SCRIPT_DIR/jurisdiction-config.json"
LOGOS_DIR="$SCRIPT_DIR/logos"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Mark-a-Spot Jurisdiction Import ===${NC}"
echo ""

# Check if config file exists
if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${RED}Error: Config file not found: $CONFIG_FILE${NC}"
    echo "Create jurisdiction-config.json first."
    exit 1
fi

# Check if running in DDEV context
if ! command -v ddev &> /dev/null; then
    echo -e "${RED}Error: ddev command not found.${NC}"
    echo "Run this from the project root where .ddev/ is located."
    exit 1
fi

# Check if DDEV is running
if ! ddev describe > /dev/null 2>&1; then
    echo -e "${YELLOW}Starting DDEV...${NC}"
    ddev start
fi

# Get group ID from argument or create new
GROUP_ID="${1:-}"

if [ -n "$GROUP_ID" ] && ! [[ "$GROUP_ID" =~ ^[0-9]+$ ]]; then
    echo -e "${RED}Error: GROUP_ID must be numeric.${NC}"
    exit 1
fi

if [ -z "$GROUP_ID" ]; then
    EXISTING_JUR_GROUP_IDS=$(ddev drush php:eval '
        foreach (\Drupal::entityTypeManager()->getStorage("group")->loadMultiple() as $group) {
            if ($group->bundle() === "jur") {
                echo $group->id() . PHP_EOL;
            }
        }
    ')
    JUR_GROUP_COUNT=$(printf '%s\n' "$EXISTING_JUR_GROUP_IDS" | sed '/^$/d' | wc -l | tr -d ' ')

    if [ "$JUR_GROUP_COUNT" = "1" ]; then
        GROUP_ID=$(printf '%s\n' "$EXISTING_JUR_GROUP_IDS" | sed '/^$/d' | head -n 1)
        echo -e "${CYAN}Using existing jurisdiction group ID: $GROUP_ID${NC}"
    elif [ "$JUR_GROUP_COUNT" != "0" ]; then
        echo -e "${RED}Error: Multiple jurisdiction groups exist. Pass the target GROUP_ID explicitly.${NC}"
        printf '%s\n' "$EXISTING_JUR_GROUP_IDS" | sed '/^$/d;s/^/  - /'
        exit 1
    fi
fi

if [ -z "$GROUP_ID" ]; then
    echo -e "${YELLOW}No existing jurisdiction group found. Creating new jurisdiction group...${NC}"

    # Pass group config through JSON so labels cannot break the PHP eval string.
    GROUP_CONFIG=$(cat "$CONFIG_FILE" | ddev exec jq -c '.group')
    echo "$GROUP_CONFIG" | ddev exec -s web "cat > /tmp/jurisdiction_group.json"

    # Create new group
    GROUP_ID=$(ddev drush php:eval '
        use Drupal\group\Entity\Group;
        $group_config = json_decode(file_get_contents("/tmp/jurisdiction_group.json"), true);
        if (!is_array($group_config) || empty($group_config["label"])) {
            echo "ERROR: Missing group label";
            exit(1);
        }
        $group = Group::create([
            "type" => "jur",
            "label" => $group_config["label"],
        ]);
        $group->save();
        echo $group->id();
    ')

    if ! [[ "$GROUP_ID" =~ ^[0-9]+$ ]]; then
        echo -e "${RED}Error: Could not create numeric group ID. Got: $GROUP_ID${NC}"
        exit 1
    fi

    echo -e "${GREEN}Created new group with ID: $GROUP_ID${NC}"
else
    echo -e "${CYAN}Updating existing group ID: $GROUP_ID${NC}"
fi

DDEV_ENV_FILE="$PROJECT_ROOT/.ddev/.env"
if [ -d "$PROJECT_ROOT/.ddev" ]; then
    touch "$DDEV_ENV_FILE"
    grep -v "^JURISDICTION_ID=" "$DDEV_ENV_FILE" > "${DDEV_ENV_FILE}.tmp" 2>/dev/null || true
    mv "${DDEV_ENV_FILE}.tmp" "$DDEV_ENV_FILE"
    echo "JURISDICTION_ID=$GROUP_ID" >> "$DDEV_ENV_FILE"
fi

# Copy logos to Drupal files directory (if they exist)
if [ -d "$LOGOS_DIR" ] && [ "$(ls -A $LOGOS_DIR 2>/dev/null)" ]; then
    echo -e "${YELLOW}Copying logos to Drupal files...${NC}"
    ddev exec "mkdir -p /var/www/html/web/sites/default/files/jurisdiction-logos"

    for file in logo-light.svg logo-dark.svg favicon.svg icon-192.png icon-512.png; do
        if [ -f "$LOGOS_DIR/$file" ]; then
            cat "$LOGOS_DIR/$file" | ddev exec -s web "cat > /var/www/html/web/sites/default/files/jurisdiction-logos/$file"
            echo "  - Copied $file"
        fi
    done
else
    echo -e "${YELLOW}No logos directory found, skipping logo import.${NC}"
fi

# Read nuxt_config from JSON and set it on the group
echo -e "${YELLOW}Setting field_nuxt_config...${NC}"

# Extract just the nuxt_config part using jq
NUXT_CONFIG=$(cat "$CONFIG_FILE" | ddev exec jq -c '.nuxt_config')

if [ -z "$NUXT_CONFIG" ] || [ "$NUXT_CONFIG" = "null" ]; then
    echo -e "${RED}Error: Could not extract nuxt_config from JSON${NC}"
    exit 1
fi

# Write config to temp file in container to avoid escaping issues
echo "$NUXT_CONFIG" | ddev exec -s web "cat > /tmp/nuxt_config.json"

# Update the group with the config
ddev drush php:eval '
    use Drupal\group\Entity\Group;
    $group = Group::load('"$GROUP_ID"');
    if (!$group) {
        echo "ERROR: Group not found";
        exit(1);
    }
    $config = file_get_contents("/tmp/nuxt_config.json");
    $group->set("field_nuxt_config", $config);
    $group->save();
    echo "OK";
'

echo ""
echo -e "${GREEN}=== Import Complete ===${NC}"
echo ""
echo "Group ID: $GROUP_ID"
echo ""
echo -e "${CYAN}Next steps:${NC}"
echo ""
echo "1. Jurisdiction ID was written to .ddev/.env for the pre-built UI image:"
echo "   JURISDICTION_ID=$GROUP_ID"
echo ""
echo "   Or wire the Nuxt variables directly in Compose:"
echo "   environment:"
echo "     - NUXT_PUBLIC_JURISDICTION_ID=$GROUP_ID"
echo "     - NUXT_JURISDICTION_ID=$GROUP_ID"
echo ""
echo "2. Restart DDEV:"
echo "   ddev restart"
echo ""
echo "3. Test the frontend:"
echo "   https://$(ddev describe -j | grep -o '"primary_url":"[^"]*' | cut -d'"' -f4 | sed 's|https://||'):8040"
echo ""
