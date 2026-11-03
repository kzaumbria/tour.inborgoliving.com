<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(strpos($path,'..')!==false || preg_match('~/(?:\.|config|src|SQL|bin)~',$path)){http_response_code(404);exit;}
if(strpos($path,'/api/')===0){require __DIR__.'/api/index.php';return true;}
if(strpos($path,'/admin')===0){require __DIR__.'/admin/index.php';return true;}
if($path==='/' || preg_match('~^/route/[a-z0-9-]+$~',$path)){require __DIR__.'/index.php';return true;}
if(is_file(__DIR__.$path)) return false;
http_response_code(404);echo 'Not found';
