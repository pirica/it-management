-- Idempotent: tourist tax €2 per person per night on portal settings (live DBs missing column: apply hotel_booking_settings_tourist_tax.sql first).
UPDATE `hotel_booking_settings`
SET `tourist_tax_per_person_per_night` = 2.00
WHERE `tourist_tax_per_person_per_night` IS NULL OR `tourist_tax_per_person_per_night` = 0;
