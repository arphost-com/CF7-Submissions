#!/bin/bash
# One-shot: commit GPLv3 + GitHub-release pipeline, push everywhere, cut release.
set -e
cd /Users/bstetler/projects/arphost-com/cf7-db-gsheets

VERSION=$(grep -E '^\s*\*\s*Version:' cf7-db-gsheets.php | awk '{print $NF}')
TAG="v${VERSION}"

git add -A
git commit -m "GPLv3 license (full text + headers); pipeline: manual GitHub release job replaces production deploy" || echo "nothing to commit"

# GitLab (origin)
git push origin main

# GitHub mirror
git remote get-url github >/dev/null 2>&1 || git remote add github https://github.com/arphost-com/CF7-Submissions.git
git push -u github main

# Tag + release
git tag "$TAG" 2>/dev/null || echo "tag $TAG exists"
git push github "$TAG" 2>/dev/null || true
git push origin "$TAG" 2>/dev/null || true

if command -v gh >/dev/null; then
  gh release create "$TAG" "cf7-db-gsheets-${VERSION}.zip" \
    --repo arphost-com/CF7-Submissions \
    --title "$TAG" --generate-notes 2>/dev/null || echo "release may already exist"
  echo "RELEASE_DONE"
else
  echo "NO_GH_CLI"
fi
echo "SCRIPT_OK version=$VERSION"
