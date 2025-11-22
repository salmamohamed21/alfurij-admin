# TODO: Implement Points-Based Currency System

## Steps to Complete

- [x] Create app/Helpers/CurrencyHelper.php with conversion functions
- [x] Update composer.json to autoload the helper file
- [x] Run composer dump-autoload
- [x] Modify WalletController.php: Change topup to accept 'points', update show to return balance in SAR and points, update notifications
- [x] Modify AuctionController.php: Change bid to accept 'points', update join and finish notifications to show points
- [x] Modify ListingResource.php: Add price_in_sar and price_in_points fields
