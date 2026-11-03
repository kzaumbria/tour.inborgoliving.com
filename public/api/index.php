<?php
require dirname(__DIR__,2).'/src/content.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); header('Allow: GET'); echo json_encode(['error'=>'GET required']); exit; }
$path=trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
$lang=language((string)($_GET['lang']??'')); $lid=(int)$lang['id'];
if($path==='api/languages') $result=languages();
elseif($path==='api/routes') $result=array_map(function($r)use($lid){return route_content($r,$lid);},rows('SELECT * FROM routes WHERE is_active=1 ORDER BY id'));
elseif(preg_match('~^api/routes/([a-z0-9-]+)(/stops)?$~',$path,$m)) {
 $r=rows('SELECT * FROM routes WHERE slug=? AND is_active=1',[$m[1]]);
 $result=$r?route_content($r[0],$lid,true):null;
 if($result && isset($m[2])) $result=$result['stops'];
} elseif(preg_match('~^api/locations/(\d+)$~',$path,$m)) $result=location_content((int)$m[1],$lid);
else $result=null;
if($result===null){http_response_code(404);$result=['error'=>'Not found'];}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
