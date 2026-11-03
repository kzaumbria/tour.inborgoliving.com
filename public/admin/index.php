<?php
require dirname(__DIR__,2).'/src/admin.php';
session_start_secure(); header('Cache-Control: no-store');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST')check_csrf();
if(($_POST['action']??'')==='logout'){$_SESSION=[];session_regenerate_id(true);redirect('/admin/');}
if(empty($_SESSION['uid'])) {
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $username=substr(trim($_POST['username']??''),0,190);
  $user=rows('SELECT * FROM users WHERE username=?',[$username])[0]??null;
  $key=hash('sha256',$user?'account:'.$user['id']:'unknown:'.strtolower($username));
  run('DELETE FROM login_attempts WHERE window_started<DATE_SUB(NOW(),INTERVAL 1 DAY)');
  run('INSERT IGNORE INTO login_attempts (identifier,attempts,window_started) VALUES (?,0,NOW())',[$key]);
  run('UPDATE login_attempts SET attempts=0,window_started=NOW() WHERE identifier=? AND window_started<DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$key]);
  run('UPDATE login_attempts SET attempts=attempts+1 WHERE identifier=?',[$key]);
  $attempt=rows('SELECT attempts FROM login_attempts WHERE identifier=?',[$key])[0];
  $ok=password_verify((string)($_POST['password']??''),$user['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
  if((int)$attempt['attempts']<=10 && $user && $ok && $user['role']==='admin'){
   session_regenerate_id(true);$_SESSION['uid']=$user['id'];$_SESSION['last_activity']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));run('DELETE FROM login_attempts WHERE identifier=?',[$key]);redirect('/admin/');
  }
  $error='Unable to sign in. Check your credentials, or wait 15 minutes after repeated attempts.';
 }
 admin_header('Staff sign in');echo ($error?'<p class="error" role="alert">'.e($error).'</p>':'').'<form method="post" class="card narrow"><h2>Welcome back</h2><p>Sign in to manage your tour content.</p><input type="hidden" name="csrf" value="'.e(csrf()).'">';field('username','Username','','text','required autocomplete="username"');field('password','Password','','password','required autocomplete="current-password"');echo '<button>Sign in</button></form>';admin_footer();exit;
}
if(time()-($_SESSION['last_activity']??0)>7200){$_SESSION=[];redirect('/admin/');}
$_SESSION['last_activity']=time();
if(!rows("SELECT id FROM users WHERE id=? AND role='admin'",[$_SESSION['uid']])){$_SESSION=[];redirect('/admin/');}
$entity=$_GET['entity']??'';$id=(int)($_GET['id']??0);$defs=entities();
if($entity && !isset($defs[$entity])){http_response_code(404);admin_header('Page not found');echo '<section class="card empty-state"><h2>Page not found</h2><p>Choose a section from the navigation to continue.</p></section>';admin_footer();exit;}
try {
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'save';
  if($action==='save' && $entity){$id=save_entity($entity,$id);redirect('?entity='.$entity.'&id='.$id.'&saved=1');}
  if($action==='delete' && in_array($entity,['locations','routes'])){
   run("DELETE FROM $entity WHERE id=?",[$id]);redirect('?entity='.$entity);
  }
  if($action==='upload' && $entity==='locations'){if(!rows('SELECT id FROM locations WHERE id=?',[$id]))throw new InvalidArgumentException('Unknown location.');media_upload($id);redirect('?entity=locations&id='.$id.'&saved=1');}
  if($action==='remove_media'&&$entity==='locations'){
   run('DELETE FROM location_media WHERE id=? AND location_id=?',[(int)$_POST['media_id'],$id]);redirect('?entity=locations&id='.$id);
  }
  if($action==='stops'&&$entity==='routes'){
   $posted=$_POST['stops']??[];if(count($posted)>100)throw new InvalidArgumentException('Maximum 100 stops.');
   db()->beginTransaction();
   try {
    rows('SELECT id FROM routes WHERE id=? FOR UPDATE',[$id]);
    // Keep stop IDs stable so visitor progress survives reorder and content edits.
    run('UPDATE route_stops SET stop_order=-id WHERE route_id=?',[$id]);
    $keep=[];$seen=[];$order=0;
    foreach($posted as $stop){
     if(!empty($stop['remove']))continue;
     $loc=int_value($stop['location_id'],1,2147483647,'location');
     if(isset($seen[$loc]))throw new InvalidArgumentException('Each location can occur only once per route.');$seen[$loc]=true;
     $radius=trim($stop['custom_trigger_radius']??'');$radius=$radius===''?null:int_value($radius,5,500,'radius');
     $sid=(int)($stop['id']??0);$order++;
     if($sid){if(!rows('SELECT id FROM route_stops WHERE id=? AND route_id=?',[$sid,$id]))throw new InvalidArgumentException('Invalid route stop.');run('UPDATE route_stops SET location_id=?,stop_order=?,custom_trigger_radius=?,is_optional=? WHERE id=? AND route_id=?',[$loc,$order,$radius,isset($stop['is_optional'])?1:0,$sid,$id]);}
     else {run('INSERT INTO route_stops (route_id,location_id,stop_order,custom_trigger_radius,is_optional) VALUES (?,?,?,?,?)',[$id,$loc,$order,$radius,isset($stop['is_optional'])?1:0]);$sid=(int)db()->lastInsertId();}
     $keep[]=$sid;
     foreach(rows('SELECT id FROM languages') as $l){$tr=$stop['translations'][$l['id']]??[];
      run('INSERT INTO route_stop_translations (route_stop_id,language_id,arrival_instruction,departure_instruction,route_specific_notes) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE arrival_instruction=VALUES(arrival_instruction),departure_instruction=VALUES(departure_instruction),route_specific_notes=VALUES(route_specific_notes)',[$sid,$l['id'],substr(trim($tr['arrival_instruction']??''),0,50000),substr(trim($tr['departure_instruction']??''),0,50000),substr(trim($tr['route_specific_notes']??''),0,50000)]);
     }
    }
    run('DELETE FROM route_stops WHERE route_id=? AND stop_order<0',[$id]);db()->commit();
   }catch(Throwable $ex){db()->rollBack();throw $ex;}
   redirect('?entity=routes&id='.$id.'&saved=1');
  }
 }
}catch(InvalidArgumentException $ex){$error=$ex->getMessage();}
catch(PDOException $ex){error_log((string)$ex);$error='Unable to save. Check for duplicate slugs, codes or locations. A location used in a route cannot be deleted.';}
admin_header($entity?ucfirst($entity):'Tour dashboard');
if($error)echo '<p role="alert" class="error">'.e($error).' Review the form and try again.</p>';
if(isset($_GET['saved']))echo '<p role="status" class="notice">Your submitted changes were saved. Details, media and route stops are saved separately.</p>';
if(!$entity){echo '<div class="cards">';foreach(['locations','routes','languages'] as $t){$n=rows("SELECT COUNT(*) n FROM $t")[0]['n'];echo '<a class="card" href="?entity='.$t.'"><h2>'.e($n).' '.e($t).'</h2><p>Manage '.e($t).' →</p></a>';}echo '<div class="card"><h2>'.e(rows('SELECT COUNT(*) n FROM routes WHERE is_active=1')[0]['n']).' published routes</h2></div></div><p>Manage photos and audio inside each location. Demo data must be verified before publication.</p>';admin_footer();exit;}
$editing=isset($_GET['id'])||isset($_GET['new']);
if(!$editing){$records=rows("SELECT * FROM $entity ORDER BY id");echo '<div class="section-heading"><p>'.count($records).' records · Select a record to edit</p><a class="button" href="?entity='.e($entity).'&new=1">Create '.e(rtrim($entity,'s')).'</a></div><div class="list admin-list">';foreach($records as $r)echo '<a class="card admin-list-row" href="?entity='.e($entity).'&id='.$r['id'].'"><span class="record-name">'.e($r['internal_name']??$r['name']).'</span><span class="record-status">'.($r['is_active']?'Active':'Inactive').(!empty($r['is_demo'])?' · DEMO':'').'</span><span class="record-action">Edit →</span></a>';if(!$records)echo '<div class="card empty-state"><h2>No '.e($entity).' yet</h2><p>Create your first '.e(rtrim($entity,'s')).' using the button above.</p></div>';echo '</div>';admin_footer();exit;}
$r=$id?(rows("SELECT * FROM $entity WHERE id=?",[$id])[0]??null):['is_active'=>1,'is_demo'=>1,'trigger_radius'=>50,'sort_order'=>0,'estimated_duration_minutes'=>60,'estimated_distance_m'=>1000];
if($r===null){http_response_code(404);echo '<section class="card empty-state"><h2>Record not found</h2><p>This record may have been removed.</p><a href="?entity='.e($entity).'">Return to '.e($entity).'</a></section>';admin_footer();exit;}
if($error&&($_POST['action']??'')==='save'){ $r=array_merge($r,$_POST); foreach(['is_active','is_demo','is_default','guided_only'] as $flag)$r[$flag]=isset($_POST[$flag])?1:0; }
$langs=rows('SELECT * FROM languages ORDER BY sort_order,id');
echo '<p><a href="?entity='.e($entity).'">← Back to '.e($entity).'</a></p><div class="editor-summary"><h2>'.e($id?($r['internal_name']??$r['name']):'New '.rtrim($entity,'s')).'</h2><p>'.($r['is_active']?'Active':'Inactive').(!empty($r['is_demo'])?' · DEMO':'').' · Details and translations are saved together below.</p></div>';
echo '<form method="post" class="card admin-details"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="action" value="save">';
foreach(admin_field_groups($entity) as $heading=>$keys){echo '<fieldset class="admin-fieldset"><legend>'.e($heading).'</legend><div class="form-grid">';foreach($keys as $k){$label=$defs[$entity][$k];$type=in_array($k,['is_default','is_active','is_demo','guided_only'])?'checkbox':(in_array($k,['latitude','longitude','trigger_radius','sort_order','estimated_duration_minutes','estimated_distance_m'])?'number':(in_array($k,['general_notes','accessibility_notes'])?'textarea':'text'));field($k,$label,$r[$k]??'',$type,$type==='number'?'step="any"':'');}echo '</div></fieldset>';}
if($entity==='locations')echo '<h2>Pick coordinates on the map</h2><p>Click the map or drag the marker. Verify coordinates before disabling DEMO.</p><div id="coordinate-map" class="map admin-map"></div><button type="button" id="use-location">Use current location</button><p id="coordinate-status" role="status"></p>';
if($entity!=='languages')echo '<div class="translation-heading"><h2>Visitor-facing translations</h2><p>Open each language to review its content. Saving details saves every translation shown here.</p></div>';
if($entity!=='languages')foreach($langs as $l){
 $table=$entity==='locations'?'location_translations':'route_translations';$fk=$entity==='locations'?'location_id':'route_id';
 $tr=$id?(rows("SELECT * FROM $table WHERE $fk=? AND language_id=?",[$id,$l['id']])[0]??[]):[];
 if($error&&isset($_POST['translations'][$l['id']]))$tr=$_POST['translations'][$l['id']];
 echo '<details class="translation"><summary>'.e($l['native_name']).' content</summary><p>A blank title removes this translation; visitors then see a labelled fallback language.</p>';
 foreach(translation_fields($entity) as $key)field('translations['.$l['id'].']['.$key.']',ucfirst(str_replace('_',' ',$key)),$tr[$key]??'',$key==='title'?'text':'textarea');echo '</details>';
}
echo '<div class="admin-save-bar"><button>'.($entity==='languages'?'Save language':'Save details & translations').'</button><p>Only this form is saved. Media and route stops have their own controls.</p></div></form>';
if($entity==='locations'&&$id){ ?>
<section class="card admin-media"><h2>Photos & narration</h2><p class="section-help">Upload files separately from the details form. Uploading does not automatically set the featured image.</p><p>JPEG, PNG, WebP or MP3. Maximum 25 MB. Copy an image path into Featured image above.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="upload"><label>File<input type="file" name="media" accept="image/jpeg,image/png,image/webp,audio/mpeg" required></label><label>Language<select name="media_language"><option value="">All languages (photos only)</option><?php foreach($langs as $l)echo '<option value="'.$l['id'].'">'.e($l['native_name']).'</option>';?></select></label><?php field('caption','Caption / image alternative text');?><button>Upload media</button></form>
<?php $media=rows('SELECT m.*,l.code FROM location_media m LEFT JOIN languages l ON l.id=m.language_id WHERE location_id=? ORDER BY m.sort_order,m.id',[$id]);if(!$media)echo '<p class="empty-state">No photos or narration attached yet. Choose a file above to upload.</p>';foreach($media as $m){echo '<div class="media-row"><a href="'.e($m['file_path']).'">'.e($m['file_path']).'</a> '.e($m['code']??'All languages').' '.e($m['caption']);echo '<form method="post"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="media_id" value="'.$m['id'].'"><button name="action" value="remove_media">Remove from location</button></form></div>';}echo '</section>';}
if($entity==='routes'&&$id){
 $stops=rows('SELECT * FROM route_stops WHERE route_id=? ORDER BY stop_order',[$id]);
 if($error&&($_POST['action']??'')==='stops')$stops=array_values($_POST['stops']??[]);
 $locations=rows('SELECT id,internal_name,latitude,longitude,is_active FROM locations ORDER BY internal_name');
 function stop_form($s,$index,$locations,$langs){
  echo '<article class="stop-editor" draggable="true"><div class="toolbar"><strong class="stop-number"></strong><button type="button" data-move="-1" aria-label="Move stop up">↑</button><button type="button" data-move="1" aria-label="Move stop down">↓</button></div><input type="hidden" name="stops['.$index.'][id]" value="'.e($s['id']??0).'"><label>Location<select class="stop-location" name="stops['.$index.'][location_id]">';
  foreach($locations as $loc)echo '<option data-lat="'.e($loc['latitude']).'" data-lng="'.e($loc['longitude']).'" value="'.$loc['id'].'" '.((int)($s['location_id']??0)===(int)$loc['id']?'selected':'').'>'.e($loc['internal_name']).($loc['is_active']?'':' (inactive)').'</option>';echo '</select></label>';
  field('stops['.$index.'][custom_trigger_radius]','Override radius (blank = location default)',$s['custom_trigger_radius']??'','number','min="5" max="500"');field('stops['.$index.'][is_optional]','Optional',$s['is_optional']??false,'checkbox');field('stops['.$index.'][remove]','Remove this stop',false,'checkbox');
  foreach($langs as $l){$tr=isset($s['translations'])?($s['translations'][$l['id']]??[]):(!empty($s['id'])?(rows('SELECT * FROM route_stop_translations WHERE route_stop_id=? AND language_id=?',[$s['id'],$l['id']])[0]??[]):[]);echo '<details><summary>'.e($l['native_name']).' walking instructions</summary>';foreach(['arrival_instruction','departure_instruction','route_specific_notes'] as $k)field('stops['.$index.'][translations]['.$l['id'].']['.$k.']',ucfirst(str_replace('_',' ',$k)),$tr[$k]??'','textarea');echo '</details>';}
  echo '</article>';
 }
 echo '<section class="card admin-stops"><h2>Route stops</h2><p>Drag to reorder or use the arrow buttons. Save the stop list separately. Lines show stop order, not pedestrian directions.</p><div id="route-map" class="map admin-map"></div><p id="stops-empty" class="empty-state"'.($stops?' hidden':'').'>No stops yet. Add an existing location, then save the stop list.</p><form method="post" id="stops-form"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="action" value="stops"><div id="stop-editors">';foreach($stops as $i=>$s)stop_form($s,$i,$locations,$langs);echo '</div><button type="button" id="add-stop">Add existing location</button> <button>Save stop order & instructions</button></form><template id="stop-template">';stop_form([],999,$locations,$langs);echo '</template><p><a href="/route/'.e($r['slug']).'?debug=1">Preview with GPS simulation →</a></p></section>';
}
if($id&&$entity!=='languages')echo '<form method="post" class="danger-form card"><h2>Delete this record</h2><p>This is permanent. Consider deactivating instead.</p><input type="hidden" name="csrf" value="'.e(csrf()).'"><button class="danger" name="action" value="delete">Delete '.e(rtrim($entity,'s')).'</button><p>Deleting a route removes its stops. Locations used by a route are protected. Deactivate to unpublish safely.</p></form>';
admin_footer();
