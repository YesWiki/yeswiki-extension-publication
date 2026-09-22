#!/usr/bin/env bash

# Extract from node_modules the files the extension ships, so that a production
# server never needs node. The extracted files are ignored by git.

cd "$(dirname "$0")/.." || exit 1

# Copy a JS file while stripping sourceMappingURL comments
copy_js() { sed '/^[[:space:]]*\/\/#[[:space:]]*sourceMappingURL=/d' "$1" > "$2"; }
# Copy a CSS file while stripping sourceMappingURL comments
copy_css() { sed '/^[[:space:]]*\/\*#[[:space:]]*sourceMappingURL=/d' "$1" > "$2"; }

mkdir -p javascripts/vendor/pagedjs
copy_js node_modules/pagedjs/dist/paged.esm.js javascripts/vendor/pagedjs/paged.esm.js
cp -f node_modules/pagedjs/LICENSE.md javascripts/vendor/pagedjs/LICENSE.md
