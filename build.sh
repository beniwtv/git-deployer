#!/bin/bash

set -e

if [ $# -ne 1 ]; then
  echo "Usage: `basename $0` <tag>"
  exit 65
fi

#
# Tag & build master branch
#
git checkout master
git tag ${TAG}

box build

git commit -m "Version ${TAG}"

echo "New version created. Now you should run:"
echo "git push ${TAG}"
