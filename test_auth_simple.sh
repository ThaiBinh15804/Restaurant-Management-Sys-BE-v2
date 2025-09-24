#!/bin/bash

# Simple test for Cookie Authentication
BASE_URL="http://localhost:8000"
COOKIE_FILE="cookies.txt"

echo "=== Testing Cookie-based Auth ==="

# Clean up
rm -f $COOKIE_FILE

# 1. Login with verbose output  
echo ""
echo "1. Login Test:"
echo "Using endpoint: $BASE_URL/api/auth/login"
echo ""

curl -v -c "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    -d '{"email":"admin@restaurant.com","password":"password123"}' \
    "$BASE_URL/api/auth/login"

echo ""
echo "2. Cookie file contents:"
if [ -f "$COOKIE_FILE" ]; then
    cat "$COOKIE_FILE"
else
    echo "No cookie file found"
fi

echo ""
echo "3. Refresh test:"
curl -v -b "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    "$BASE_URL/api/auth/refresh"

# Clean up
rm -f $COOKIE_FILE