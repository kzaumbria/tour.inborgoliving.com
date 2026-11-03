<?php
require_once __DIR__.'/bootstrap.php';
function languages(): array { return rows('SELECT id,code,name,native_name,is_default,sort_order FROM languages WHERE is_active=1 ORDER BY is_default DESC,sort_order,id'); }
function language(string $code): array { $all=languages(); foreach($all as $l) if($l['code']===$code) return $l; if(!$all) throw new RuntimeException('No active language'); return $all[0]; }
function translated(string $table, string $key, int $id, int $lang): array {
    // Table/key identifiers are internal constants, never request input.
    $r=rows("SELECT t.*,l.code AS content_language FROM $table t JOIN languages l ON l.id=t.language_id WHERE t.$key=? AND l.is_active=1 ORDER BY (t.language_id=?) DESC,l.is_default DESC,l.sort_order,l.id LIMIT 1",[$id,$lang]);
    return $r[0] ?? [];
}
function location_content(int $id,int $lang): ?array {
    $r=rows('SELECT id,slug,internal_name,latitude,longitude,trigger_radius,featured_image,opening_hours,closed_days,guided_only,is_demo FROM locations WHERE id=? AND is_active=1',[$id]);
    if(!$r) return null;
    $l=array_merge($r[0],translated('location_translations','location_id',$id,$lang)); $l['id']=$id;
    $l['title']=$l['title']??$l['internal_name']; unset($l['internal_name']);
    $l['media']=rows('SELECT id,type,file_path,caption,sort_order FROM location_media WHERE location_id=? AND (language_id=? OR (language_id IS NULL AND type=\'image\')) ORDER BY sort_order,id',[$id,$lang]);
    $l['audio']=null;
    foreach($l['media'] as $m) if($m['type']==='audio' && !$l['audio']) $l['audio']=$m['file_path'];
    return $l;
}
function route_content(array $r,int $lang,bool $stops=false): array {
    $id=(int)$r['id']; $r=array_merge($r,translated('route_translations','route_id',$id,$lang)); $r['id']=$id;
    $r['title']=$r['title']??$r['internal_name']; unset($r['internal_name']);
    $rs=rows('SELECT s.* FROM route_stops s JOIN locations l ON l.id=s.location_id WHERE s.route_id=? AND l.is_active=1 ORDER BY s.stop_order',[$id]);
    $r['stop_count']=count($rs);
    if($stops) { $r['stops']=[]; foreach($rs as $s) {
        $sid=(int)$s['id']; $s=array_merge($s,translated('route_stop_translations','route_stop_id',$sid,$lang)); $s['id']=$sid;
        $s['location']=location_content((int)$s['location_id'],$lang);
        $s['radius']=(int)($s['custom_trigger_radius']??$s['location']['trigger_radius']); $r['stops'][]=$s;
    }}
    return $r;
}
