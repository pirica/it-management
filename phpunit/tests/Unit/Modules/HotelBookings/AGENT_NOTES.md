# AGENT_NOTES.md — PHPUnit: Hotel Bookings

## HotelBookingRoomTypePortalRulesTest

Maps to `includes/itm_hotel_booking.php` portal rule helpers:

| Test | Helpers |
|------|---------|
| `testMaxTotalGuestsRejectsOverflow` | `itm_hotel_booking_room_type_fits_occupancy` |
| `testMinAdultsPerRoomSlice` | `itm_hotel_booking_room_type_fits_occupancy` |
| `testAdultsOnlyRejectsChildrenAndBabies` | `itm_hotel_booking_room_type_fits_occupancy` |
| `testIncludedAdultsPerRoomAffectsQuote` | `itm_hotel_booking_portal_quote_nightly` |
| `testPricingOverridesAndFirstChildFree` | `itm_hotel_booking_portal_room_type_effective_rules`, `quote_nightly` |
| `testStayValidationMinMaxNightsAndAdvance` | `itm_hotel_booking_portal_room_type_validate_stay` |
| `testClosedToArrivalAndDepartureWeekdays` | `itm_hotel_booking_portal_weekday_closed_list`, `validate_stay` |
| `testComplimentaryRoomCredit` | `itm_hotel_booking_portal_complimentary_room_credit` |
| `testOccupancyLineLabel` | `itm_hotel_booking_portal_occupancy_line_label` |
| `testPortalBookableCardContract` | `itm_hotel_booking_portal_room_type_card_available` |
| `testDraftPetPolicyStrictAllLines` | `draft_pets_allowed`, `draft_pet_policy` (DB) |
| `testDraftRequiresApprovalHelperExists` | `draft_requires_approval` |

Run: `php scripts/run_tests.php --filter HotelBookingRoomTypePortalRules`
