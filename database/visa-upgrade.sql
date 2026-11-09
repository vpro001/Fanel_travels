-- Visa processing upgrade (2026-11-09). Run once on a database created from an older schema.sql.
-- A fresh install from schema.sql already has all of this.
--
-- Adds: document checklists per application, service-fee payment tracking, appointment and
-- visa-validity details, files staff share with the customer, and work / residence visa types.

-- Documents each destination asks for, one per line. A line starting with * must be uploaded
-- with the application; the others can follow later.
ALTER TABLE visa_destinations
  ADD COLUMN `checklist` text DEFAULT NULL AFTER `requirements`;

ALTER TABLE visa_applications
  MODIFY `visa_type` enum('tourist','business','visit','student','transit','work','residence','other') NOT NULL,
  ADD COLUMN `fee_status` enum('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid' AFTER `service_fee_kobo`,
  ADD COLUMN `fee_paid_at` datetime DEFAULT NULL AFTER `fee_status`,
  ADD COLUMN `fee_method` varchar(30) DEFAULT NULL AFTER `fee_paid_at`,
  ADD COLUMN `appointment_at` datetime DEFAULT NULL AFTER `fee_method`,
  ADD COLUMN `appointment_place` varchar(200) DEFAULT NULL AFTER `appointment_at`,
  ADD COLUMN `visa_valid_until` date DEFAULT NULL AFTER `appointment_place`,
  ADD KEY `ix_va_travel` (`travel_date`);

-- One row per document an application needs. Staff accept or reject each one.
CREATE TABLE IF NOT EXISTS `visa_checklist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `label` varchar(200) NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('missing','received','accepted','rejected') NOT NULL DEFAULT 'missing',
  `note` varchar(500) DEFAULT NULL,
  `added_by` enum('system','staff') NOT NULL DEFAULT 'system',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_vcl_app` (`application_id`,`sort_order`),
  CONSTRAINT `fk_vcl_app` FOREIGN KEY (`application_id`) REFERENCES `visa_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which checklist item a file belongs to, and files staff share with the customer (e.g. the e-visa)
ALTER TABLE visa_documents
  ADD COLUMN `checklist_id` int(10) unsigned DEFAULT NULL AFTER `application_id`,
  ADD COLUMN `shared` tinyint(1) NOT NULL DEFAULT 0 AFTER `uploaded_by`,
  ADD KEY `ix_vdoc_check` (`checklist_id`),
  ADD CONSTRAINT `fk_vdoc_check` FOREIGN KEY (`checklist_id`) REFERENCES `visa_checklist` (`id`) ON DELETE SET NULL;

-- Checklists for the destinations that already exist (staff can edit them in Admin → Visa destinations)
UPDATE visa_destinations SET checklist = CONCAT('*Passport photo (bio-data) page\n*Passport photograph\n',
  CASE slug
    WHEN 'united-kingdom' THEN 'Bank statements (last 6 months)\nEmployer letter or business documents\nTravel itinerary and accommodation details\nInvitation letter (if visiting family or friends)'
    WHEN 'united-states' THEN 'Proof of income\nProof of ties to Nigeria (work, property, family)\nPrevious visas, if any'
    WHEN 'canada' THEN 'Bank statements (last 4 to 6 months)\nProof of employment or business\nTravel itinerary\nInvitation letter (if visiting family)'
    WHEN 'united-arab-emirates' THEN 'Return flight booking\nHotel booking or host address'
    WHEN 'schengen' THEN 'Travel medical insurance\nBank statements (last 6 months)\nFlight and hotel reservations\nEmployer letter or business documents'
    WHEN 'south-africa' THEN 'Bank statements (last 3 months)\nReturn flight booking\nProof of accommodation'
    ELSE 'Supporting documents'
  END)
WHERE checklist IS NULL;

-- Two destinations from Fanel's own flyers (requirements as printed on the flyers).
-- Fee 0 = "confirmed after we review your application"; set the real fee in the admin.
INSERT INTO visa_destinations (slug, country_name, country_code, visa_types, summary, requirements, checklist, processing_time, service_fee_kobo, sort_order) VALUES
('thailand', 'Thailand', 'TH', 'tourist',
 'Thailand e-visa for holidays. We prepare and submit the application for you.',
 'Passport copy, valid for at least 6 months\nRecent passport photograph on a white background\nCompleted Thailand e-visa application form (we fill it with you)\nPOSSAP police clearance (mandatory)\nHotel booking or proof of accommodation\nConfirmed round-trip flight itinerary',
 '*Passport copy (valid 6+ months)\n*Passport photograph (white background)\nPOSSAP police clearance\nHotel booking or proof of accommodation\nConfirmed round-trip flight itinerary',
 'Ask us for current timing', 0, 70),
('malaysia', 'Malaysia', 'MY', 'tourist',
 'Malaysia visa for a one-month stay. Easy visa, memorable journey.',
 'Passport, scanned pages 1 to 10\nYellow fever card\nScanned passport photograph',
 '*Passport, scanned pages 1 to 10\n*Passport photograph (scanned)\nYellow fever card',
 'About 7 days. Visa valid for a 1-month stay', 0, 80)
ON DUPLICATE KEY UPDATE slug = slug;
