#!/bin/bash

# Test script cho Cookie-based Refresh Token System
# Chạy từ terminal: bash test_cookie_auth.sh

BASE_URL="http://localhost:8000/api"
TEST_EMAIL="admin@restaurant.local"
TEST_PASSWORD="password123"
COOKIE_FILE="cookies.txt"

echo "🚀 Testing Cookie-based Refresh Token System"
echo "=============================================="

# Cleanup old cookie file
rm -f $COOKIE_FILE

echo ""
echo "1️⃣  Testing Login (should set refresh token cookie)"
echo "---------------------------------------------------"

# Test login and capture cookies
echo "Sending login request..."
RESPONSE=$(curl -s -c $COOKIE_FILE \
  -X POST \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"$TEST_EMAIL\",
    \"password\": \"$TEST_PASSWORD\"
  }" \
  "$BASE_URL/auth/login")

echo "Response:"
echo "$RESPONSE" | python -m json.tool 2>/dev/null || echo "$RESPONSE"

echo ""
echo "📋 Cookies after login:"
cat $COOKIE_FILE 2>/dev/null || echo "No cookie file found"

echo ""
echo "2️⃣  Testing Token Refresh (should use cookie)"
echo "---------------------------------------------"

# Test refresh using cookies
echo "Sending refresh request..."
REFRESH_RESPONSE=$(curl -s -b $COOKIE_FILE \
  -X POST \
  -H "Content-Type: application/json" \
  "$BASE_URL/auth/refresh")

echo "Response:"
echo "$REFRESH_RESPONSE" | python -m json.tool 2>/dev/null || echo "$REFRESH_RESPONSE"

echo ""
echo "📋 Cookies after refresh:"
cat $COOKIE_FILE 2>/dev/null || echo "No cookie file found"

echo ""
echo "3️⃣  Testing Logout (should clear cookie)"
echo "----------------------------------------"

# Get access token from previous login response
ACCESS_TOKEN=$(echo "$RESPONSE" | python -c "import sys, json; data=json.load(sys.stdin); print(data.get('data', {}).get('access_token', 'NONE'))" 2>/dev/null)

if [ "$ACCESS_TOKEN" = "NONE" ] || [ -z "$ACCESS_TOKEN" ]; then
    echo "⚠️  Getting new access token..."
    LOGIN_RESPONSE=$(curl -s -c $COOKIE_FILE \
      -X POST \
      -H "Content-Type: application/json" \
      -d "{
        \"email\": \"$TEST_EMAIL\",
        \"password\": \"$TEST_PASSWORD\"
      }" \
      "$BASE_URL/auth/login")
    
    ACCESS_TOKEN=$(echo "$LOGIN_RESPONSE" | python -c "import sys, json; data=json.load(sys.stdin); print(data.get('data', {}).get('access_token', 'NONE'))" 2>/dev/null)
fi

echo "Access Token: ${ACCESS_TOKEN:0:50}..."

# Test logout
echo "Sending logout request..."
LOGOUT_RESPONSE=$(curl -s -c $COOKIE_FILE \
  -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  "$BASE_URL/auth/logout")

echo "Response:"
echo "$LOGOUT_RESPONSE" | python -m json.tool 2>/dev/null || echo "$LOGOUT_RESPONSE"

echo ""
echo "📋 Cookies after logout:"
cat $COOKIE_FILE 2>/dev/null || echo "No cookie file found"

echo ""
echo "4️⃣  Testing Refresh after logout (should fail)"
echo "----------------------------------------------"

# Try refresh after logout
echo "Sending refresh request after logout..."
FINAL_RESPONSE=$(curl -s -b $COOKIE_FILE \
  -X POST \
  -H "Content-Type: application/json" \
  "$BASE_URL/auth/refresh")

echo "Response:"
echo "$FINAL_RESPONSE" | python -m json.tool 2>/dev/null || echo "$FINAL_RESPONSE"

echo ""
echo "✅ Cookie-based authentication test completed!"

# Cleanup
rm -f $COOKIE_FILE