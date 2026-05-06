#!/bin/sh
#
# Build a release zip from the current working tree.
#
# Honours .distignore at the plugin root — the same file the
# `Deploy to WordPress.org` workflow consumes via the 10up
# action-wordpress-plugin-deploy. Single source of truth: the
# WooCommerce.com-bound zip and the wp.org SVN-bound zip ship the
# same set of files.
#
# Folds in `composer install --no-dev` so the bundled vendor/ tree
# matches what ships to customers (no dev dependencies, optimized
# autoloader). On exit — success or failure — the dev install is
# restored via `composer install` and the staging directory is
# cleaned up.
#
set -e

plugin_slug="gtm-kit"

# Get the current directory.
current_dir=$(pwd)

# If invoked from inside ./bin, step up to the plugin root.
base_dir=$(basename "$current_dir")
if [ "$base_dir" = "bin" ]; then
    cd ..
fi

# Capture the plugin root as an absolute path so the trap can find composer.json
# regardless of where the script's cwd ended up when the trap fires.
plugin_root=$(pwd)

if [ ! -f "${plugin_root}/.distignore" ]; then
    echo "ERROR: .distignore not found at ${plugin_root}/.distignore" >&2
    echo "       The zip script reads exclusions from this file; create it before running." >&2
    exit 1
fi

stage_dir=$(mktemp -d -t "${plugin_slug}-zip.XXXXXX")

# Always restore the dev composer install and clean the staging dir on exit,
# even on failure.
#
# After this trap fires, vendor/composer/*.php and vendor/composer/installed.*
# will be in their dev-install form — i.e. the same state `composer install`
# leaves them in. That is *normal* developer-machine state and intentionally
# uncommitted. Do not `git add vendor/composer/` afterwards; tasks/lessons.md
# documents the CI breakage that committing those files causes.
trap '
    echo "Restoring dev composer install...";
    (cd "$plugin_root" && composer install --no-progress >/dev/null 2>&1) || echo "WARNING: dev composer install failed to restore — run \"composer install\" manually from $plugin_root.";
    rm -rf "$stage_dir";
' EXIT

echo "Installing production dependencies (composer install --no-dev)..."
composer install --no-dev --no-progress

# Ensure the bundled directory exists.
[ ! -d "${plugin_root}/bundled" ] && mkdir "${plugin_root}/bundled"

# Remove the previous zip file if it exists.
[ -f "${plugin_root}/bundled/${plugin_slug}.zip" ] && rm "${plugin_root}/bundled/${plugin_slug}.zip"

# Stage the plugin into a temp dir, honouring .distignore. rsync's
# --exclude-from semantics match those of 10up's WP plugin deploy action,
# so the staged tree mirrors what would land in SVN trunk.
echo "Staging files (honouring .distignore)..."
mkdir -p "${stage_dir}/${plugin_slug}"
rsync -a --exclude-from="${plugin_root}/.distignore" "${plugin_root}/" "${stage_dir}/${plugin_slug}/"

# Build the zip from the staged tree.
echo "Building zip..."
( cd "$stage_dir" && zip -rq "${plugin_root}/bundled/${plugin_slug}.zip" "$plugin_slug" )

echo "Done — zip at ${plugin_slug}/bundled/${plugin_slug}.zip"
