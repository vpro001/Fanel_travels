-- Fanel Travels LTD: run once on a database created from an older schema.sql.
-- Adds the new enquiry topics and fills in the company's contact details
-- (only where the field is still empty, so nothing set in the admin is overwritten).

ALTER TABLE enquiries
  MODIFY `type` enum('general','flight','hotel','visa','tour','insurance','consultancy','custom_trip') NOT NULL DEFAULT 'general';

UPDATE settings SET value = '0703 049 4537' WHERE name = 'contact_phone' AND value = '';
UPDATE settings SET value = 'fanelmultilink@gmail.com' WHERE name = 'contact_email' AND value = '';
UPDATE settings SET value = 'No. 6 Old Aba Road, off Boundary Market, Rumukurushi, Port Harcourt, Rivers State, Nigeria' WHERE name = 'contact_address' AND value = '';
UPDATE settings SET value = 'Monday – Saturday, 9:00 AM – 6:00 PM' WHERE name = 'contact_hours' AND value = '';
