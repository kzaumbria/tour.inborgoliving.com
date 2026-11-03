<?php
/** CLI only. Creates temporary rows in one transaction and ALWAYS rolls it back.
 * Auto-increment counters can advance; use a staging database.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/content.php';
function verify($condition,string $message): void {if(!$condition)throw new RuntimeException($message);echo 'PASS '.$message.PHP_EOL;}
$pdo=db();$pdo->beginTransaction();
try{
 $suffix=bin2hex(random_bytes(6));
 $language=languages()[0]??null;if(!$language)throw new RuntimeException('Import the schema and seed first.');$lid=(int)$language['id'];
 run('INSERT INTO locations (slug,internal_name,latitude,longitude,trigger_radius) VALUES (?,?,0,0,40)',['integration-'.$suffix,'Integration fixture']);$loc=(int)$pdo->lastInsertId();
 run('INSERT INTO location_translations (location_id,language_id,title,narration_text) VALUES (?,?,?,?)',[$loc,$lid,'Shared place','Test narration']);
 $routes=[];
 foreach(['a','b'] as $name){run('INSERT INTO routes (slug,internal_name) VALUES (?,?)',['integration-'.$suffix.'-'.$name,'Integration route '.$name]);$routes[]=(int)$pdo->lastInsertId();}
 run('INSERT INTO route_stops (route_id,location_id,stop_order) VALUES (?,?,1)',[$routes[0],$loc]);$sid=(int)$pdo->lastInsertId();
 run('INSERT INTO route_stops (route_id,location_id,stop_order,custom_trigger_radius) VALUES (?,?,1,20)',[$routes[1],$loc]);
 run('UPDATE locations SET latitude=1.25 WHERE id=?',[$loc]);
 foreach($routes as $rid){$r=route_content(rows('SELECT * FROM routes WHERE id=?',[$rid])[0],$lid,true);verify((float)$r['stops'][0]['location']['latitude']===1.25,'Shared coordinates reflected in route '.$rid);verify($r['stops'][0]['location']['title']==='Shared place','Translation is shared in route '.$rid);verify($r['stops'][0]['radius']===($rid===$routes[0]?40:20),'Effective radius priority in route '.$rid);}
 $blocked=false;try{run('DELETE FROM locations WHERE id=?',[$loc]);}catch(PDOException $e){$blocked=true;}verify($blocked,'Referenced location deletion is blocked');
 run('INSERT INTO locations (slug,internal_name,latitude,longitude) VALUES (?,?,0,0)',['integration-second-'.$suffix,'Second fixture']);$loc2=(int)$pdo->lastInsertId();
 run('INSERT INTO route_stops (route_id,location_id,stop_order) VALUES (?,?,2)',[$routes[0],$loc2]);$sid2=(int)$pdo->lastInsertId();
 run('UPDATE route_stops SET stop_order=-id WHERE route_id=?',[$routes[0]]);run('UPDATE route_stops SET stop_order=1 WHERE id=?',[$sid2]);run('UPDATE route_stops SET stop_order=2 WHERE id=?',[$sid]);
 $ordered=rows('SELECT id FROM route_stops WHERE route_id=? ORDER BY stop_order',[$routes[0]]);verify((int)$ordered[0]['id']===$sid2&&(int)$ordered[1]['id']===$sid,'Reorder retains IDs and updates order');
 run('INSERT INTO route_stop_translations (route_stop_id,language_id,departure_instruction) VALUES (?,?,?)',[$sid,$lid,'Turn left']);
 $r=route_content(rows('SELECT * FROM routes WHERE id=?',[$routes[0]])[0],$lid,true);verify($r['stops'][1]['departure_instruction']==='Turn left','Route-specific translated instruction');
 run('UPDATE locations SET is_active=0 WHERE id=?',[$loc2]);$r=route_content(rows('SELECT * FROM routes WHERE id=?',[$routes[0]])[0],$lid,true);verify(count($r['stops'])===1,'Inactive locations omitted from public route');
 verify(password_verify('temporary verification',password_hash('temporary verification',PASSWORD_DEFAULT)),'Password hash/verify');
 echo "All integration checks passed. Rolling back temporary rows.\n";$pdo->rollBack();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,'FAIL '.$e->getMessage().PHP_EOL);exit(1);}
