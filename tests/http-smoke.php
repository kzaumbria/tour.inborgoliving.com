<?php
/** CLI only. Read-only HTTP checks against your running HTTPS staging deployment. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=rtrim($argv[1]??'','/');
if(!preg_match('~^https?://~',$base))exit("Usage: php tests/http-smoke.php https://tour.example.com\nRequires PHP curl.\n");
if(!extension_loaded('curl'))exit("Enable PHP curl to run this optional HTTP test.\n");
$failed=0;
function get_url(string $path): array {global $base;$ch=curl_init($base.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);$raw=curl_exec($ch);if($raw===false)throw new RuntimeException(curl_error($ch));$size=curl_getinfo($ch,CURLINFO_HEADER_SIZE);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$status,substr($raw,0,$size),substr($raw,$size)];}
function ok($value,string $label): void {global $failed;echo ($value?'PASS ':'FAIL ').$label.PHP_EOL;if(!$value)$failed++;}
try{
 foreach(['/','/admin/','/manifest.json','/sw.js','/assets/vendor/leaflet.js','/api/languages','/api/routes?lang=en'] as $path){[$status,$headers,$body]=get_url($path);ok($status===200,'GET '.$path);if(strpos($path,'/api/')===0)ok(is_array(json_decode($body,true)),'JSON '.$path);if($path==='/admin/')ok(stripos($headers,'no-store')!==false&&strpos($body,'Staff sign in')!==false,'Admin requires sign-in and is not cacheable');}
 [$status,$headers,$body]=get_url('/api/routes?lang=en');$routes=json_decode($body,true);foreach($routes??[] as $route){if(!isset($route['slug']))continue;[$status,$headers,$body]=get_url('/api/routes/'.rawurlencode($route['slug']).'?lang=en');$detail=json_decode($body,true);ok($status===200&&isset($detail['stops']),'Route detail '.$route['slug']);ok(strpos($body,'password_hash')===false&&strpos($body,'general_notes')===false,'No private fields in route response');}
 foreach(['/config.php','/SQL/schema.sql','/src/bootstrap.php','/.git/config','/uploads/example.php','/api/routes/nonexistent-'.bin2hex(random_bytes(4))] as $path){[$status]=get_url($path);ok(in_array($status,[403,404],true),'Private/missing path blocked: '.$path);}
}catch(Throwable $e){ok(false,$e->getMessage());}
echo $failed?"$failed check(s) failed.\n":"All HTTP smoke checks passed.\n";exit($failed?1:0);
