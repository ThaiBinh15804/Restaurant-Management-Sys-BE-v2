#!/bin/bash

# Simple test for Cookie Authentication
BASE_URL="http://localhost:8000"
COOKIE_FILE="cookies.txt"
LOG_FILE="test_output.txt"

# Ghi toàn bộ stdout + stderr vào file log và vẫn hiện trên terminal
exec > >(tee "$LOG_FILE") 2>&1

echo "🚀 Testing Cookie-based Auth"
echo "==============================="

# Clean up
rm -f "$COOKIE_FILE"

# 1. Login Test
echo ""
echo "1️⃣  Login Test"
echo "Endpoint: $BASE_URL/api/auth/login"

curl -v -c "$COOKIE_FILE" \
    -H "Content-Type: application/json" \
    -d '{"email":"admin@restaurant.com","password":"password123"}' \
    "$BASE_URL/api/auth/login"

# 2. Cookie file contents
echo ""
echo "2️⃣  Cookie file contents:"
if [ -f "$COOKIE_FILE" ]; then
    cat "$COOKIE_FILE"
else
    echo "No cookie file found"
fi

# 3. Refresh Test
echo ""
echo "3️⃣  Refresh Test"

curl -v -b "$COOKIE_FILE" \
  -X POST \
  -H "Content-Type: application/json" \
  "$BASE_URL/api/auth/refresh"

# Clean up
rm -f "$COOKIE_FILE"

echo ""
echo "✅ Test completed! Full output is saved in $LOG_FILE"
