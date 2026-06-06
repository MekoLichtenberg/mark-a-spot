#!/bin/bash
#
# Import Jurisdiction Configuration for Mark-a-Spot
#
# Usage:
#   ./setup/import-jurisdiction.sh           # Create new jurisdiction
#   ./setup/import-jurisdiction.sh 19        # Update existing group ID 19
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

if [ -z "$GROUP_ID" ]; then
    echo -e "${YELLOW}No GROUP_ID provided. Creating new jurisdiction group...${NC}"

    # Read label from config using jq
    LABEL=$(cat "$CONFIG_FILE" | ddev exec jq -r '.group.label')

    # Create new group
    GROUP_ID=$(ddev drush php:eval "
        use Drupal\group\Entity\Group;
        \$group = Group::create([
            'type' => 'jur',
            'label' => '$LABEL',
        ]);
        \$group->save();
        echo \$group->id();
    ")

    echo -e "${GREEN}Created new group with ID: $GROUP_ID${NC}"
else
    echo -e "${CYAN}Updating existing group ID: $GROUP_ID${NC}"
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
echo "1. Update DDEV config (.ddev/docker-compose.node-dev.yaml):"
echo "   environment:"
echo "     - NUXT_PUBLIC_JURISDICTION_ID=$GROUP_ID"
echo ""
echo "2. Restart DDEV:"
echo "   ddev restart"
echo ""
echo "3. Test the frontend:"
echo "   https://$(ddev describe -j | grep -o '"primary_url":"[^"]*' | cut -d'"' -f4 | sed 's|https://||'):3001"
echo ""
