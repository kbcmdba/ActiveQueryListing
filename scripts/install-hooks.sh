#!/usr/bin/env bash
#
# Install AQL git hooks by pointing core.hooksPath at .githooks/
#
# This is reversible: `git config --unset core.hooksPath`
#

set -euo pipefail

repo_root="$(git rev-parse --show-toplevel 2>/dev/null)"
if [[ -z "$repo_root" ]]; then
    echo "Error: not inside a git repository" >&2
    exit 1
fi

cd "$repo_root"

if [[ ! -d .githooks ]]; then
    echo "Error: .githooks/ directory not found" >&2
    exit 1
fi

git config core.hooksPath .githooks
echo "Git hooks installed: core.hooksPath = .githooks"
echo ""
echo "Available hooks:"
ls -1 .githooks/ | sed 's/^/  /'
echo ""
echo "To uninstall: git config --unset core.hooksPath"
