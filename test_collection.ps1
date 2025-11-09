# Harag Direct Sale Flow Test Script

$baseUrl = "http://127.0.0.1:8000/api"

# Function to make POST request
function Invoke-PostRequest {
    param (
        [string]$url,
        [string]$body,
        [hashtable]$headers = @{}
    )
    $headers["Content-Type"] = "application/json"
    try {
        $response = Invoke-RestMethod -Uri $url -Method Post -Body $body -Headers $headers
        return $response
    } catch {
        Write-Host "Error in POST to $url : $($_.Exception.Message)"
        return $null
    }
}

# Function to make GET request
function Invoke-GetRequest {
    param (
        [string]$url,
        [hashtable]$headers = @{}
    )
    try {
        $response = Invoke-RestMethod -Uri $url -Method Get -Headers $headers
        return $response
    } catch {
        Write-Host "Error in GET to $url : $($_.Exception.Message)"
        return $null
    }
}

Write-Host "Starting Harag Direct Sale Flow Test"

# 1. Admin Login
Write-Host "1. Admin Login"
$adminLoginBody = '{"email": "admin@example.com", "password": "12345678"}'
$adminLoginResponse = Invoke-PostRequest -url "$baseUrl/auth/login" -body $adminLoginBody
if ($adminLoginResponse) {
    $adminToken = $adminLoginResponse.access_token
    Write-Host "Admin Token: $adminToken"
} else {
    Write-Host "Admin login failed"
    exit
}

# 2. Seller Login (User 1)
Write-Host "2. Seller Login (User 1)"
$sellerLoginBody = '{"email": "salmamohamed0266@gmail.com", "password": "123456"}'
$sellerLoginResponse = Invoke-PostRequest -url "$baseUrl/auth/login" -body $sellerLoginBody
if ($sellerLoginResponse) {
    $userToken = $sellerLoginResponse.access_token
    Write-Host "User Token: $userToken"
} else {
    Write-Host "Seller login failed"
    exit
}

# 3. Seller Top-up Wallet
Write-Host "3. Seller Top-up Wallet"
$topupBody = '{"amount": 120000}'
$headers = @{"Authorization" = "Bearer $userToken"}
$topupResponse = Invoke-PostRequest -url "$baseUrl/wallet/topup" -body $topupBody -headers $headers
if ($topupResponse) {
    Write-Host "Top-up Response: $($topupResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Top-up failed"
}

# 4. Create Direct Sale Listing (User Seller)
Write-Host "4. Create Direct Sale Listing (User Seller)"
$listingBody = '{
  "ad_type": "ad",
  "buy_now": true,
  "title": "Hyundai Tucson 2023",
  "category": "Cars",
  "section": "SUV",
  "city": "Jeddah",
  "description": "Excellent condition, full option",
  "price": 95000,
  "condition": "used",
  "model": "Tucson",
  "serial_number": "HYTUC2023SN1",
  "fuel_type": "Petrol",
  "transmission": "Automatic",
  "color": "Gray"
}'
$listingResponse = Invoke-PostRequest -url "$baseUrl/listings" -body $listingBody -headers $headers
if ($listingResponse) {
    $userListingId = $listingResponse.data.id
    Write-Host "User Listing ID: $userListingId"
} else {
    Write-Host "Listing creation failed"
    exit
}

# 5. Admin Approve Listing (User Seller)
Write-Host "5. Admin Approve Listing (User Seller)"
$adminHeaders = @{"Authorization" = "Bearer $adminToken"}
$approveResponse = Invoke-PostRequest -url "$baseUrl/listings/$userListingId/approve" -body "{}" -headers $adminHeaders
if ($approveResponse) {
    Write-Host "Approve Response: $($approveResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Approve failed"
}

# 6. Buyer Login (User 2)
Write-Host "6. Buyer Login (User 2)"
$buyerLoginBody = '{"email": "salmamohamedsaad228@gmail.com", "password": "123456"}'
$buyerLoginResponse = Invoke-PostRequest -url "$baseUrl/auth/login" -body $buyerLoginBody
if ($buyerLoginResponse) {
    $buyerToken = $buyerLoginResponse.access_token
    Write-Host "Buyer Token: $buyerToken"
} else {
    Write-Host "Buyer login failed"
    exit
}

# 7. Buyer Top-up Wallet
Write-Host "7. Buyer Top-up Wallet"
$buyerHeaders = @{"Authorization" = "Bearer $buyerToken"}
$buyerTopupBody = '{"amount": 100000}'
$buyerTopupResponse = Invoke-PostRequest -url "$baseUrl/wallet/topup" -body $buyerTopupBody -headers $buyerHeaders
if ($buyerTopupResponse) {
    Write-Host "Buyer Top-up Response: $($buyerTopupResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Buyer top-up failed"
}

# 8. Buyer Purchase (Buy Now from User Seller)
Write-Host "8. Buyer Purchase (Buy Now from User Seller)"
$purchaseBody = '{"listing_id": ' + $userListingId + '}'
$purchaseResponse = Invoke-PostRequest -url "$baseUrl/purchase" -body $purchaseBody -headers $buyerHeaders
if ($purchaseResponse) {
    Write-Host "Purchase Response: $($purchaseResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Purchase failed"
}

# 9. Buyer Transactions After Purchase (User Seller)
Write-Host "9. Buyer Transactions After Purchase (User Seller)"
$buyerTransactionsResponse = Invoke-GetRequest -url "$baseUrl/wallet/transactions" -headers $buyerHeaders
if ($buyerTransactionsResponse) {
    Write-Host "Buyer Transactions: $($buyerTransactionsResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Buyer transactions failed"
}

# 10. Seller Transactions (Confirm Received)
Write-Host "10. Seller Transactions (Confirm Received)"
$sellerTransactionsResponse = Invoke-GetRequest -url "$baseUrl/wallet/transactions" -headers $headers
if ($sellerTransactionsResponse) {
    Write-Host "Seller Transactions: $($sellerTransactionsResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Seller transactions failed"
}

# 11. Admin Creates Direct Sale Listing (Company Sale)
Write-Host "11. Admin Creates Direct Sale Listing (Company Sale)"
$adminListingBody = '{
  "ad_type": "ad",
  "buy_now": true,
  "title": "Toyota Land Cruiser 2024",
  "category": "Cars",
  "section": "SUV",
  "city": "Riyadh",
  "description": "Brand new car from company stock",
  "price": 180000,
  "condition": "new",
  "model": "Land Cruiser",
  "serial_number": "TLC2024SN88",
  "fuel_type": "Petrol",
  "transmission": "Automatic",
  "color": "Black"
}'
$adminListingResponse = Invoke-PostRequest -url "$baseUrl/listings" -body $adminListingBody -headers $adminHeaders
if ($adminListingResponse) {
    $adminListingId = $adminListingResponse.data.id
    Write-Host "Admin Listing ID: $adminListingId"
} else {
    Write-Host "Admin listing creation failed"
    exit
}

# 12. Buyer Purchase (Buy Now from Admin Listing)
Write-Host "12. Buyer Purchase (Buy Now from Admin Listing)"
$adminPurchaseBody = '{"listing_id": ' + $adminListingId + '}'
$adminPurchaseResponse = Invoke-PostRequest -url "$baseUrl/purchase" -body $adminPurchaseBody -headers $buyerHeaders
if ($adminPurchaseResponse) {
    Write-Host "Admin Purchase Response: $($adminPurchaseResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Admin purchase failed"
}

# 13. Buyer Transactions After Company Purchase
Write-Host "13. Buyer Transactions After Company Purchase"
$buyerTransactionsAfterResponse = Invoke-GetRequest -url "$baseUrl/wallet/transactions" -headers $buyerHeaders
if ($buyerTransactionsAfterResponse) {
    Write-Host "Buyer Transactions After: $($buyerTransactionsAfterResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Buyer transactions after failed"
}

# 14. Company Wallet Transactions (Admin)
Write-Host "14. Company Wallet Transactions (Admin)"
$adminTransactionsResponse = Invoke-GetRequest -url "$baseUrl/wallet/transactions" -headers $adminHeaders
if ($adminTransactionsResponse) {
    Write-Host "Admin Transactions: $($adminTransactionsResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Admin transactions failed"
}

Write-Host "Test completed"
