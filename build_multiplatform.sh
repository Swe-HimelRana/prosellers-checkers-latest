#!/bin/bash

# Configuration
IMAGE_NAME="app-checker"
TAG="multi-1.0.0"
PLATFORMS="linux/amd64,linux/arm64"

# Check if a repo name is provided (optional now)
if [ -z "$1" ]; then
    REPO=$IMAGE_NAME
else
    REPO=$1
fi

echo "Building $REPO:$TAG..."

# 1. Build and Load for Local Architecture
# This makes the image available in 'docker images' for manual usage/pushing
echo "------------------------------------------------"
echo "Building and Loading for Local Architecture (Host)"
echo "------------------------------------------------"
docker build -t "$REPO:$TAG" --load .

echo "------------------------------------------------"
echo "Done! Image loaded locally as: $REPO:$TAG"
echo "You can now push it manually: docker push $REPO:$TAG"
echo ""
echo "NOTE: 'docker images' can only hold one architecture at a time."
echo " To build and push for MULTIPLE platforms (amd64 + arm64), you must run:"
echo " docker buildx build --platform $PLATFORMS -t $REPO:$TAG --push ."
echo "------------------------------------------------"
