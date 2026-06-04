#!/bin/bash

# WordPress.org Deployment Script für PerForm
# Author: dbw media
# Usage: ./deploy.sh [VERSION]
#
# WICHTIG: Dieses Script erwartet eine lokale SVN-Working-Copy unter ./_wporg-svn/
# Diese existiert ERST, nachdem WordPress.org das Plugin approved und einen Slug
# vergeben hat. Erstmaliges Auschecken (nach Approval):
#
#   svn checkout https://plugins.svn.wordpress.org/perform-forms _wporg-svn
#
# Vor der ersten Veröffentlichung gibt's noch kein SVN-Repo — dann das Plugin als
# ZIP einreichen über https://wordpress.org/plugins/developers/add

set -e  # Exit bei Fehler

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

error() {
    echo -e "${RED}ERROR: $1${NC}" >&2
    exit 1
}

info() {
    echo -e "${BLUE}INFO: $1${NC}"
}

success() {
    echo -e "${GREEN}SUCCESS: $1${NC}"
}

warning() {
    echo -e "${YELLOW}WARNING: $1${NC}"
}

# Version Parameter prüfen
if [ -z "$1" ]; then
    error "Version parameter required! Usage: ./deploy.sh [VERSION]"
fi

VERSION="$1"
SVN_USERNAME="dbwmediadennis"
SVN_PATH="./_wporg-svn"
SVN_URL="https://plugins.svn.wordpress.org/perform-forms"

info "Starting deployment for PerForm v$VERSION"

# 1. Prüfungen
info "Running pre-deployment checks..."

# In richtigem Projektordner?
if [ ! -f "perform-forms.php" ]; then
    error "Not in the correct project directory! perform-forms.php not found."
fi

# SVN Working Copy vorhanden?
if [ ! -d "$SVN_PATH" ]; then
    error "SVN working copy not found at $SVN_PATH. Run: svn checkout $SVN_URL $SVN_PATH"
fi

if [ ! -d "$SVN_PATH/.svn" ]; then
    error "$SVN_PATH exists but is not an SVN working copy"
fi

if [ ! -f "package.json" ]; then
    error "package.json not found"
fi

success "Pre-deployment checks passed"

# 2. Build erstellen
info "Creating production build..."
npm run build || error "Build failed"
success "Production build created"

# 3. SVN Repository updaten
info "Updating SVN working copy..."
cd "$SVN_PATH"
svn update || error "SVN update failed"
cd ..
success "SVN working copy updated"

# 4. Dateien zu SVN trunk kopieren
info "Copying files to SVN trunk..."
# Exclude list kept in sync with .distignore (single source of truth for what
# ships). The compiled build/ directory is intentionally NOT excluded.
rsync -av \
    --exclude='.git*' \
    --exclude='.github' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='*.log' \
    --exclude='*.zip' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='src' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='.phpcs.xml.dist' \
    --exclude='.claude*' \
    --exclude='.dev' \
    --exclude='.wordpress-org' \
    --exclude='.distignore' \
    --exclude='DEPLOY-*.md' \
    --exclude='PLUGIN-CHECK-*.md' \
    --exclude='PERFORM_SPEC.md' \
    --exclude='PERFORM_ROADMAP.md' \
    --exclude='PERFORM_LANDINGPAGE.md' \
    --exclude='AUDIT_PROMPT.md' \
    --exclude='CONTINUE_PROMPT.md' \
    --exclude='INITIAL_PROMPT.md' \
    --exclude='readme.md' \
    --exclude='includes/Bridge/README.md' \
    --exclude='.editorconfig' \
    --exclude='_wporg-svn' \
    --exclude='deploy.sh' \
    ./ "$SVN_PATH/trunk/" || error "File copy failed"
success "Files copied to SVN trunk"

# 5. SVN Changes verarbeiten
info "Processing SVN changes..."
cd "$SVN_PATH"

# Add new files
svn add trunk --force 2>/dev/null || true

# Remove deleted files
svn status | grep '^!' | awk '{print $2}' | xargs -r svn delete 2>/dev/null || true

# Show status
info "SVN Status:"
svn status

# 6. User Confirmation vor Commit
echo ""
warning "About to commit to SVN trunk with version $VERSION"
read -p "Continue? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    error "Deployment aborted by user"
fi

# 7. SVN Trunk Commit
info "Committing to SVN trunk..."
svn commit -m "v$VERSION: WordPress.org release" --username "$SVN_USERNAME" || error "SVN trunk commit failed"
success "SVN trunk committed"

# 8. Tag erstellen
info "Creating SVN tag $VERSION..."
svn copy trunk "tags/$VERSION" || error "SVN tag creation failed"
success "SVN tag created"

# 9. Tag committen
info "Committing SVN tag..."
svn commit -m "Tag version $VERSION" --username "$SVN_USERNAME" || error "SVN tag commit failed"
success "SVN tag committed"

# 10. Verification (mit kurzer Wartezeit, falls SVN-Server den Tag noch nicht ausliefert)
info "Verifying deployment..."
sleep 3
if svn list tags/ 2>/dev/null | grep -q "$VERSION/"; then
    success "Tag $VERSION successfully created"
else
    warning "Tag verification inconclusive — check https://plugins.svn.wordpress.org/perform-forms/tags/ manually"
fi

cd ..

# 11. Final Success Message
echo ""
success "🚀 DEPLOYMENT SUCCESSFUL!"
echo ""
info "Version $VERSION has been deployed to WordPress.org"
info "Check: https://wordpress.org/plugins/perform-forms/"
info "Plugin will be available in ~15 minutes"
echo ""
warning "Don't forget to:"
echo "  - Create a Git tag: git tag v$VERSION && git push origin v$VERSION"
echo "  - Update any documentation"
echo "  - Test the plugin installation from WordPress.org"
