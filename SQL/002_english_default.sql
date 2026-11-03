-- Existing installations only: import after the original schema/seed.
-- Safe to rerun. Preserves language IDs, translations, routes and locations.
START TRANSACTION;
INSERT INTO languages (code,name,native_name,is_default,is_active,sort_order)
VALUES ('en','English','English',0,1,0)
ON DUPLICATE KEY UPDATE is_active=1,sort_order=0;
UPDATE languages SET is_default=CASE WHEN code='en' THEN 1 ELSE 0 END;
UPDATE languages SET sort_order=1 WHERE code='it';
COMMIT;
SELECT code,native_name,is_default,is_active FROM languages ORDER BY is_default DESC,sort_order,id;
