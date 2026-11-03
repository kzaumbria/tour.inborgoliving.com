-- OPTIONAL READ-ONLY checks after schema.sql and seed.sql; safe in phpMyAdmin.
SELECT VERSION() AS database_version;
SELECT 'languages' AS entity, COUNT(*) AS total FROM languages
UNION ALL SELECT 'locations', COUNT(*) FROM locations
UNION ALL SELECT 'routes', COUNT(*) FROM routes
UNION ALL SELECT 'route_stops', COUNT(*) FROM route_stops;
-- Seed totals: 2 languages, 6 locations, 3 routes, 13 route stops.
SELECT code, name, is_default, is_active FROM languages ORDER BY sort_order;
-- Exactly one row should be returned here.
SELECT code FROM languages WHERE is_active=1 AND is_default=1;
-- Shared physical places, not duplicated records.
SELECT l.id, l.internal_name, COUNT(s.route_id) AS routes_using_location
FROM locations l JOIN route_stops s ON s.location_id=l.id
GROUP BY l.id,l.internal_name HAVING COUNT(s.route_id)>1;
-- Review ordering and effective radius.
SELECT r.slug, s.id AS stop_id, s.stop_order, l.internal_name,
COALESCE(s.custom_trigger_radius,l.trigger_radius) AS effective_radius
FROM route_stops s JOIN routes r ON r.id=s.route_id JOIN locations l ON l.id=s.location_id
ORDER BY r.id,s.stop_order;
-- Expect zero rows in these validation queries.
SELECT id FROM locations WHERE latitude NOT BETWEEN -90 AND 90 OR longitude NOT BETWEEN -180 AND 180 OR trigger_radius NOT BETWEEN 5 AND 500;
SELECT route_id,stop_order,COUNT(*) FROM route_stops GROUP BY route_id,stop_order HAVING COUNT(*)>1;
SELECT id FROM route_stops WHERE stop_order<=0 OR (custom_trigger_radius IS NOT NULL AND custom_trigger_radius NOT BETWEEN 5 AND 500);
-- Demo flags must be reviewed before production.
SELECT id,internal_name,is_demo FROM locations WHERE is_demo=1;
