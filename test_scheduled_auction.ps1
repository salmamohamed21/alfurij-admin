# Harag Scheduled Auction Flow Test Script

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

Write-Host "Starting Harag Scheduled Auction Flow Test"

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

# 2. User1 Login
Write-Host "2. User1 Login"
$user1LoginBody = '{"email": "salmamohamed0266@gmail.com", "password": "123456"}'
$user1LoginResponse = Invoke-PostRequest -url "$baseUrl/auth/login" -body $user1LoginBody
if ($user1LoginResponse) {
    $user1Token = $user1LoginResponse.access_token
    Write-Host "User1 Token: $user1Token"
} else {
    Write-Host "User1 login failed"
    exit
}

# 3. User2 Login
Write-Host "3. User2 Login"
$user2LoginBody = '{"email": "salmamohamedsaad228@gmail.com", "password": "123456"}'
$user2LoginResponse = Invoke-PostRequest -url "$baseUrl/auth/login" -body $user2LoginBody
if ($user2LoginResponse) {
    $user2Token = $user2LoginResponse.access_token
    Write-Host "User2 Token: $user2Token"
} else {
    Write-Host "User2 login failed"
    exit
}

# 4. Admin Create Listing
Write-Host "4. Admin Create Listing"
$listingBody = '{
  "ad_type": "auction",
  "title": "Toyota Hilux 2022",
  "category": "Cars",
  "section": "Pickup",
  "city": "Jeddah",
  "description": "Hilux in perfect condition",
  "price": 90000,
  "condition": "used",
  "model": "Hilux",
  "serial_number": "THX23456",
  "fuel_type": "Diesel",
  "transmission": "Manual",
  "color": "White"
}'
$adminHeaders = @{"Authorization" = "Bearer $adminToken"}
$listingResponse = Invoke-PostRequest -url "$baseUrl/listings" -body $listingBody -headers $adminHeaders
if ($listingResponse) {
    $listingId = $listingResponse.data.id
    Write-Host "Listing ID: $listingId"
} else {
    Write-Host "Listing creation failed"
    exit
}

# 5. Admin Create Scheduled Auction
Write-Host "5. Admin Create Scheduled Auction"
$auctionBody = '{
  "listing_id": ' + $listingId + ',
  "type": "scheduled",
  "start_time": "2024-11-09T10:00:00Z",
  "end_time": "2024-11-10T10:00:00Z",
  "starting_price": 70000,
  "reserve_price": 85000,
  "min_increment": 500
}'
$auctionResponse = Invoke-PostRequest -url "$baseUrl/auctions" -body $auctionBody -headers $adminHeaders
if ($auctionResponse) {
    $auctionId = $auctionResponse.data.id
    Write-Host "Auction ID: $auctionId"
} else {
    Write-Host "Auction creation failed"
    exit
}

# Note: For testing, you may need to adjust start_time to a past time or run the update command to set status to 'opening'

# 6. User1 Wallet Topup
Write-Host "6. User1 Wallet Topup"
$user1Headers = @{"Authorization" = "Bearer $user1Token"}
$topup1Body = '{"amount": 100000}'
$topup1Response = Invoke-PostRequest -url "$baseUrl/wallet/topup" -body $topup1Body -headers $user1Headers
if ($topup1Response) {
    Write-Host "User1 Top-up Response: $($topup1Response | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "User1 top-up failed"
}

# 7. User2 Wallet Topup
Write-Host "7. User2 Wallet Topup"
$user2Headers = @{"Authorization" = "Bearer $user2Token"}
$topup2Body = '{"amount": 80000}'
$topup2Response = Invoke-PostRequest -url "$baseUrl/wallet/topup" -body $topup2Body -headers $user2Headers
if ($topup2Response) {
    Write-Host "User2 Top-up Response: $($topup2Response | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "User2 top-up failed"
}

# 8. User1 Place Bid 1
Write-Host "8. User1 Place Bid 1"
$bid1Body = '{"amount": 72000}'
$bid1Response = Invoke-PostRequest -url "$baseUrl/auctions/$auctionId/bid" -body $bid1Body -headers $user1Headers
if ($bid1Response) {
    Write-Host "User1 Bid 1 Response: $($bid1Response | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "User1 Bid 1 failed"
}

# 9. User2 Place Bid 2
Write-Host "9. User2 Place Bid 2"
$bid2Body = '{"amount": 74000}'
$bid2Response = Invoke-PostRequest -url "$baseUrl/auctions/$auctionId/bid" -body $bid2Body -headers $user2Headers
if ($bid2Response) {
    Write-Host "User2 Bid 2 Response: $($bid2Response | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "User2 Bid 2 failed"
}

# 10. User1 Place Bid 3 (Higher)
Write-Host "10. User1 Place Bid 3 (Higher)"
$bid3Body = '{"amount": 76000}'
$bid3Response = Invoke-PostRequest -url "$baseUrl/auctions/$auctionId/bid" -body $bid3Body -headers $user1Headers
if ($bid3Response) {
    Write-Host "User1 Bid 3 Response: $($bid3Response | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "User1 Bid 3 failed"
}

# 11. Admin Finish Auction
Write-Host "11. Admin Finish Auction"
$finishResponse = Invoke-PostRequest -url "$baseUrl/auctions/$auctionId/finish" -body "{}" -headers $adminHeaders
if ($finishResponse) {
    Write-Host "Finish Auction Response: $($finishResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "Finish auction failed"
}

# 12. User1 Transactions (Check Refund)
Write-Host "12. User1 Transactions (Check Refund)"
$user1TransactionsResponse = Invoke-GetRequest -url "$baseUrl/wallet/transactions" -headers $user1Headers
if ($user1TransactionsResponse) {
    Write-Host "User1 Transactions: $($user1TransactionsResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "User1 transactions failed"
}

# 13. User2 Transactions (Check Refund)
Write-Host "13. User2 Transactions (Check Refund)"
$user2TransactionsResponse = Invoke-GetRequest -url "$baseUrl/wallet/transactions" -headers $user2Headers
if ($user2TransactionsResponse) {
    Write-Host "User2 Transactions: $($user2TransactionsResponse | ConvertTo-Json -Depth 10)"
} else {
    Write-Host "User2 transactions failed"
}

Write-Host "Scheduled Auction Flow Test completed"
