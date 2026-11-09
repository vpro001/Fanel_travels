-- Flights through Galileo (Travelport Universal API) (2026-11-09). Run once.
--  supplier          where the fare came from: 'galileo' or 'duffel'
--  gds_state         booking → booked → ticketed (booking = a booking request got no clear answer)
--  gds_record        Universal Record locator
--  gds_pnr           Galileo (1G) PNR
--  gds_reservation   uAPI air reservation locator (used to issue the ticket)
--  gds_pricing_keys  the fares to ticket
--  charged_amount / charged_currency   what the airline actually charged when booked (Galileo and Duffel)

ALTER TABLE flight_bookings
  ADD COLUMN `supplier` varchar(10) NOT NULL DEFAULT 'duffel' AFTER `offer_id`,
  ADD COLUMN `gds_state` varchar(10) DEFAULT NULL AFTER `duffel_order_id`,
  ADD COLUMN `gds_record` varchar(20) DEFAULT NULL AFTER `gds_state`,
  ADD COLUMN `gds_pnr` varchar(20) DEFAULT NULL AFTER `gds_record`,
  ADD COLUMN `gds_reservation` varchar(20) DEFAULT NULL AFTER `gds_pnr`,
  ADD COLUMN `gds_pricing_keys` text DEFAULT NULL AFTER `gds_reservation`,
  ADD COLUMN `charged_amount` decimal(12,2) DEFAULT NULL AFTER `supplier_amount`,
  ADD COLUMN `charged_currency` char(3) DEFAULT NULL AFTER `charged_amount`,
  ADD KEY `ix_fb_gds_pnr` (`gds_pnr`);
