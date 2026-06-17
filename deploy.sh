#!/bin/bash

# WordPress.org Deployment Script for Flinkform
# Author: dbw media
# Usage: ./deploy.sh [VERSION]
#
# On first run the script checks out the SVN working copy automatically.
# Assets (banners, icons) from .wordpress-org/ are synced to SVN assets/.

set -e

# --- Colours ---------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

error()   { echo -e "${RED}ERROR: $1${NC}" >&2; exit 1; }
info()    { echo -e "${BLUE}INFO: $1${NC}"; }
success() { echo -e "${GREEN}SUCCESS: $1${NC}"; }
warning() { echo -e "${YELLOW}WARNING: $1${NC}"; }

# --- Config ----------------------------------------------------------------
SVN_USERNAME="dbwmediadennis"
SVN_URL="https://plugins.svn.wordpress.org/flinkform"
SVN_PATH="./_wporg-svn"
ASSETS_DIR="./.wordpress-org"
DISTIGNORE="./.distignore"
PLUGIN_FILE="flinkform.php"

# --- Version ---------------------------------------------------------------
if [ -z "$1" ]; then
    error "Version parameter required! Usage: ./deploy.sh [VERSION]"
fi

VERSION="$1"
info "Starting deployment for Flinkform v$VERSION"

# --- Pre-flight checks -----------------------------------------------------
info "Running pre-deployment checks..."

[ -f "$PLUGIN_FILE" ] || error "Not in the correct project directory! $PLUGIN_FILE not found."
[ -f "package.json" ]  || error "package.json not found"
[ -f "$DISTIGNORE" ]   || error ".distignore not found"

# Version in plugin header must match
HEADER_VERSION=$(grep -m1 'Version:' "$PLUGIN_FILE" | sed 's/.*Version:[[:space:]]*//')
if [ "$HEADER_VERSION" != "$VERSION" ]; then
    error "Version mismatch! Plugin header says $HEADER_VERSION but you passed $VERSION"
fi

# Version in readme.txt Stable tag must match
README_VERSION=$(grep -m1 'Stable tag:' readme.txt | sed 's/.*Stable tag:[[:space:]]*//')
if [ "$README_VERSION" != "$VERSION" ]; then
    error "Version mismatch! readme.txt Stable tag says $README_VERSION but you passed $VERSION"
fi

success "Pre-deployment checks passed (v$VERSION)"

# --- SVN checkout (first run) ----------------------------------------------
if [ ! -d "$SVN_PATH/.svn" ]; then
    if [ -d "$SVN_PATH" ]; then
        error "$SVN_PATH exists but is not an SVN working copy. Remove it and retry."
    fi
    info "SVN working copy not found. Checking out from $SVN_URL ..."
    svn checkout "$SVN_URL" "$SVN_PATH" || error "SVN checkout failed"
    success "SVN working copy checked out"
fi

# --- Build -----------------------------------------------------------------
info "Creating production build..."
npm run build || error "Build failed"
success "Production build created"

# --- SVN update ------------------------------------------------------------
info "Updating SVN working copy..."
svn update "$SVN_PATH" || error "SVN update failed"
success "SVN working copy updated"

# --- Build rsync exclude list from .distignore -----------------------------
# .distignore uses the same patterns wp-scripts plugin-zip understands.
# We convert them to rsync --exclude flags. Lines starting with # and empty
# lines are skipped. Leading slashes are stripped (rsync expects relative).
RSYNC_EXCLUDES=()
while IFS= read -r line; do
    # skip blanks and comments
    [[ -z "$line" || "$line" =~ ^# ]] && continue
    # strip leading slash (rsync is relative to the source dir)
    pattern="${line#/}"
    RSYNC_EXCLUDES+=( --exclude="$pattern" )
done < "$DISTIGNORE"

# Always exclude the SVN working copy itself
RSYNC_EXCLUDES+=( --exclude="_wporg-svn" )

# --- Sync plugin files to trunk -------------------------------------------
info "Syncing files to SVN trunk..."
mkdir -p "$SVN_PATH/trunk"
rsync -av --delete "${RSYNC_EXCLUDES[@]}" ./ "$SVN_PATH/trunk/" || error "File sync to trunk failed"
success "Files synced to SVN trunk"

# --- Sync assets (banners, icons, screenshots) to SVN assets/ -------------
if [ -d "$ASSETS_DIR" ]; then
    info "Syncing assets to SVN assets/..."
    mkdir -p "$SVN_PATH/assets"
    rsync -av --delete "$ASSETS_DIR/" "$SVN_PATH/assets/" || error "Asset sync failed"
    success "Assets synced"
else
    warning "No .wordpress-org/ directory found, skipping assets"
fi

# --- Stage SVN changes -----------------------------------------------------
info "Processing SVN changes..."
cd "$SVN_PATH"

# Add new files (force so already-versioned files are silently skipped)
svn add --force trunk assets 2>/dev/null || true

# Remove files that were deleted from the working copy.
# macOS xargs has no -r flag; the if-check guards against an empty list.
DELETED=$(svn status | grep '^!' | awk '{print $2}')
if [ -n "$DELETED" ]; then
    echo "$DELETED" | xargs svn delete
fi

# Show what will be committed
echo ""
info "SVN status:"
svn status
echo ""

# --- Confirmation ----------------------------------------------------------
warning "About to commit trunk + assets for v$VERSION to WordPress.org"
read -p "Continue? (y/N): " -n 1 -r
echo
[[ $REPLY =~ ^[Yy]$ ]] || error "Deployment aborted by user"

# --- Commit trunk + assets -------------------------------------------------
info "Committing trunk and assets..."
svn commit -m "v$VERSION: WordPress.org release" --username "$SVN_USERNAME" || error "SVN commit failed"
success "Trunk and assets committed"

# --- Create and commit tag -------------------------------------------------
info "Creating SVN tag $VERSION..."
svn copy trunk "tags/$VERSION" || error "SVN tag copy failed"
svn commit -m "Tag version $VERSION" --username "$SVN_USERNAME" || error "SVN tag commit failed"
success "Tag $VERSION committed"

# --- Verify ----------------------------------------------------------------
info "Verifying deployment..."
sleep 3
if svn list tags/ 2>/dev/null | grep -q "$VERSION/"; then
    success "Tag $VERSION verified"
else
    warning "Tag verification inconclusive - check https://plugins.svn.wordpress.org/flinkform/tags/ manually"
fi

cd ..

# --- Done ------------------------------------------------------------------
echo ""
success "DEPLOYMENT SUCCESSFUL!"
echo ""
info "Version $VERSION deployed to WordPress.org"
info "Plugin page: https://wordpress.org/plugins/flinkform/"
info "Visible in ~15 minutes, search index up to 72h"
echo ""
warning "Don't forget:"
echo "  - Git tag: git tag v$VERSION && git push origin v$VERSION"
echo "  - Test install from WordPress.org once available"
