-- Optional: set default TripAdvisor reviews link for existing hotel_booking_settings rows.
UPDATE `hotel_booking_settings`
SET `reviews_url` = 'https://www.tripadvisor.pt/Hotel_Review-g262054-d2142716-Reviews-Conrad_Algarve-Almancil_Loule_Faro_District_Algarve.html#REVIEWS'
WHERE (`reviews_url` IS NULL OR TRIM(`reviews_url`) = '' OR `reviews_url` = 'https://www.tripadvisor.com/')
  AND `deleted_at` IS NULL;
