-- Visa posts lead to a real application (2026-11-09). Run once after visa-offers.sql.
--  - each visa post links to a visa destination (and optionally a visa type, e.g. work)
--  - applications remember which post they came from
--  - destinations for every country on Fanel's flyers

ALTER TABLE visa_offers
  ADD COLUMN `visa_type` varchar(20) DEFAULT NULL AFTER `destination_id`;

ALTER TABLE visa_applications
  ADD COLUMN `offer_id` int(10) unsigned DEFAULT NULL AFTER `destination_id`,
  ADD KEY `fk_va_offer` (`offer_id`),
  ADD CONSTRAINT `fk_va_offer` FOREIGN KEY (`offer_id`) REFERENCES `visa_offers` (`id`) ON DELETE SET NULL;

-- Countries from Fanel's flyers. The full requirement lists weren't supplied, so each starts with the
-- passport page and photo; staff complete the requirements and checklist in Admin → Visa destinations.
-- Fee 0 = "confirmed after review" until staff set one.
INSERT INTO visa_destinations (slug, country_name, country_code, visa_types, summary, requirements, checklist, processing_time, service_fee_kobo, sort_order) VALUES
('qatar', 'Qatar', 'QA', 'tourist,visit,work', 'Tourist, family visit and work visas for Qatar.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list for your visa type after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 90),
('kenya', 'Kenya', 'KE', 'tourist', 'Kenya visa for safaris, holidays and visits.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 100),
('east-africa', 'East Africa tourist visa', 'EA', 'tourist', 'One tourist visa for travel across the East African countries that accept it.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 110),
('uganda', 'Uganda', 'UG', 'tourist', 'Uganda visa for holidays and adventure trips.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 120),
('indonesia', 'Indonesia', 'ID', 'tourist,residence', 'Indonesia tourist visa and 2-year residence permit.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list for your visa type after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 130),
('mexico', 'Mexico', 'MX', 'tourist', 'Mexico tourist visa.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 140),
('kuwait', 'Kuwait', 'KW', 'work', 'Work visa for Kuwait, for women and men.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 150),
('oman', 'Oman', 'OM', 'work', 'Work visa for Oman, for women and men.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 160),
('morocco', 'Morocco', 'MA', 'tourist', 'Morocco visa for holidays and visits.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 170),
('china', 'China', 'CN', 'tourist,business', 'China tourist and business visas.',
 'Valid international passport\nRecent passport photograph\nWe confirm the full list for your visa type after reviewing your application',
 '*Passport photo (bio-data) page\n*Passport photograph\nOther supporting documents', 'Confirmed after review', 0, 180)
ON DUPLICATE KEY UPDATE slug = slug;

-- Link the first visa posts to their countries and visa types
UPDATE visa_offers o JOIN visa_destinations d ON d.slug = CASE o.image
    WHEN 'fanel/dubai-multiple-entry' THEN 'united-arab-emirates'
    WHEN 'fanel/dubai-evisa' THEN 'united-arab-emirates'
    WHEN 'fanel/thailand-visa' THEN 'thailand'
    WHEN 'fanel/malaysia-visa' THEN 'malaysia'
    WHEN 'fanel/qatar-visa' THEN 'qatar'
    WHEN 'fanel/qatar-work-visa' THEN 'qatar'
    WHEN 'fanel/kenya-visa' THEN 'kenya'
    WHEN 'fanel/indonesia-visa' THEN 'indonesia'
    WHEN 'fanel/indonesia-residence' THEN 'indonesia'
    WHEN 'fanel/mexico-visa' THEN 'mexico'
    WHEN 'fanel/uganda-visa' THEN 'uganda'
    WHEN 'fanel/east-africa-visa' THEN 'east-africa'
    WHEN 'fanel/kuwait-oman-work-visa' THEN 'kuwait'
  END
SET o.destination_id = d.id,
    o.visa_type = CASE o.image
      WHEN 'fanel/qatar-work-visa' THEN 'work'
      WHEN 'fanel/kuwait-oman-work-visa' THEN 'work'
      WHEN 'fanel/indonesia-residence' THEN 'residence'
      ELSE 'tourist' END
WHERE o.kind = 'visa' AND o.destination_id IS NULL OR o.image IN ('fanel/dubai-multiple-entry', 'fanel/dubai-evisa', 'fanel/thailand-visa', 'fanel/malaysia-visa');

-- The Kuwait and Oman flyer covers two countries: post a second one for Oman
INSERT INTO visa_offers (kind, title, image, destination_id, visa_type, sort_order)
SELECT 'visa', 'Oman work visa', 'fanel/kuwait-oman-work-visa', (SELECT id FROM visa_destinations WHERE slug = 'oman'), 'work', 105
WHERE NOT EXISTS (SELECT 1 FROM visa_offers WHERE title = 'Oman work visa');
UPDATE visa_offers SET title = 'Kuwait work visa' WHERE image = 'fanel/kuwait-oman-work-visa' AND title = 'Kuwait and Oman work visas';
UPDATE visa_offers SET title = 'Visas for more countries', summary = 'Pick your country to apply: Qatar, South Africa, Morocco, Indonesia, Uganda, China, Kenya, East Africa, Dubai, Oman and more.'
WHERE image = 'fanel/visa-countries';
