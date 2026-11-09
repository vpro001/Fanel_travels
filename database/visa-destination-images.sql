-- Photos on the visa country cards (2026-11-09). Run once after visa-offers-apply.sql.
-- A country card shows: this image if set (Admin → Visa destinations), otherwise the country's
-- first visa post (flyer), otherwise a plain placeholder.
ALTER TABLE visa_destinations
  ADD COLUMN `image` varchar(100) DEFAULT NULL AFTER `checklist`;

-- Countries without a flyer get one of the site's existing city photos
UPDATE visa_destinations SET image = CASE slug
    WHEN 'united-kingdom' THEN 'london'
    WHEN 'united-states' THEN 'new-york'
    WHEN 'canada' THEN 'toronto'
    WHEN 'south-africa' THEN 'cape-town'
  END
WHERE slug IN ('united-kingdom', 'united-states', 'canada', 'south-africa') AND image IS NULL;
