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

## HotelBookingCheckoutOccupancyTest

Maps to checkout stay-bar occupancy helpers in `includes/itm_hotel_booking.php` and `booking/apply-occupancy.php`:

| Test | Helpers / contract |
|------|-------------------|
| `testBuildRoomsRestartUrlIncludesStayAndOccupancy` | `itm_hotel_booking_portal_build_rooms_restart_url` |
| `testApplyCheckoutOccupancyChangeRejectsExpiredDraft` | `itm_hotel_booking_portal_apply_checkout_occupancy_change` (invalid draft) |
| `testMergeOccupancyIntoUrlReplacesAdultsQueryParam` | `itm_hotel_booking_portal_merge_occupancy_into_url` |
| `testCheckoutRedirectUrlAllowedAcceptsHttpsWhenAppUrlIsHttp` | `itm_hotel_booking_portal_checkout_redirect_url_allowed` |
| `testApplyCheckoutOccupancyChangeSuccessMergesOccupancyIntoRedirect` | `itm_hotel_booking_portal_apply_checkout_occupancy_change` (adults bump + redirect merge; DB) |
| `testApplyCheckoutOccupancyChangeRoomCountRestartsAtStepOne` | `itm_hotel_booking_portal_apply_checkout_occupancy_change` (room-count bump → Step 1; DB) |

Run: `php scripts/run_tests.php --filter HotelBookingRoomTypePortalRules` or `--filter HotelBookingCheckoutOccupancy`
