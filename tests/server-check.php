<?php
/** CLI only. Read-only installation and seed/content consistency checks. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$failures=0;
function check($condition,string $label): void { global $failures; echo ($condition?'PASS':'FAIL').' '.$label.PHP_EOL; if(!$condition)$failures++; }
check(PHP_VERSION_ID>=80000,'PHP 8.x or newer');
foreach(['pdo_mysql','fileinfo','session','json'] as $extension)check(extension_loaded($extension),'PHP extension: '.$extension);
$root=dirname(__DIR__);
check(is_file($root.'/config.php'),'Private config.php exists');
check(is_writable($root.'/public/uploads'),'Uploads directory is writable by this CLI user (also verify the PHP web user)');
foreach(['SQL/schema.sql','SQL/seed.sql','public/manifest.json','public/sw.js','public/assets/vendor/leaflet.js','public/assets/vendor/leaflet.css','public/assets/images/icon-192.png','public/assets/images/icon-512.png'] as $file)check(is_file($root.'/'.$file),'File: '.$file);
if(!is_file($root.'/config.php'))exit(1);
require $root.'/src/content.php';
try {
 $version=rows('SELECT VERSION() version')[0]['version'];echo 'Database version: '.$version.PHP_EOL;
 foreach(['users','languages','locations','location_translations','location_media','routes','route_translations','route_stops','route_stop_translations','login_attempts'] as $table){rows("SELECT COUNT(*) FROM $table");check(true,'Table: '.$table);}
 check((int)rows('SELECT COUNT(*) n FROM languages WHERE is_active=1 AND is_default=1')[0]['n']===1,'Exactly one active default language');
 check((int)rows("SELECT COUNT(*) n FROM users WHERE role='admin'")[0]['n']>0,'At least one administrator exists');
 check(count(rows('SELECT route_id,stop_order FROM route_stops GROUP BY route_id,stop_order HAVING COUNT(*)>1'))===0,'Unique stop order per route');
 check(count(rows('SELECT id FROM locations WHERE latitude NOT BETWEEN -90 AND 90 OR longitude NOT BETWEEN -180 AND 180 OR trigger_radius NOT BETWEEN 5 AND 500'))===0,'Coordinate and radius bounds');
 check(count(rows('SELECT id FROM route_stops WHERE stop_order<=0 OR (custom_trigger_radius IS NOT NULL AND custom_trigger_radius NOT BETWEEN 5 AND 500)'))===0,'Stop order and override bounds');
 $all=languages();check(count($all)>0,'Active languages available');
 foreach(rows('SELECT * FROM routes WHERE is_active=1') as $r){foreach($all as $l){$content=route_content($r,(int)$l['id'],true);check(isset($content['title'],$content['stops']),'Public route loads: '.$r['slug'].' / '.$l['code']);foreach($content['stops'] as $stop)check($stop['location']!==null&&$stop['radius']>=5,'Stop joins shared location: '.$stop['id']);}}
 foreach(rows('SELECT file_path FROM location_media') as $m)check(is_file($root.'/public'.$m['file_path']),'Uploaded media exists: '.$m['file_path']);
 echo 'Demo locations remaining: '.rows('SELECT COUNT(*) n FROM locations WHERE is_demo=1')[0]['n'].PHP_EOL;
 echo 'This read-only script does not test browser permission, speech, uploads, or mobile hardware.'.PHP_EOL;
}catch(Throwable $error){check(false,'Database/content checks: '.$error->getMessage());}
echo $failures ? "$failures check(s) failed.\n" : "All installation checks passed.\n";
exit($failures?1:0);
