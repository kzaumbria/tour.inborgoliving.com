<?php
require_once __DIR__.'/bootstrap.php';
function admin_header(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · InBorgo</title><link rel="stylesheet" href="/assets/css/style.css"><link rel="stylesheet" href="/assets/vendor/leaflet.css"><script defer src="/assets/vendor/leaflet.js"></script><script defer src="/assets/js/admin.js"></script></head><body class="admin-body"><a class="skip-link" href="#admin-content">Skip to main content</a><header class="topbar"><a class="brand" href="/admin/"><img src="/assets/images/inborgo-logo.png" alt="InBorgo Living" width="92" height="73"> <small>TOUR ADMIN</small></a><a href="/">View visitor site ↗</a></header><main class="admin" id="admin-content"><nav class="toolbar admin-nav" aria-label="Administration"><a href="/admin/" <?=empty($_GET['entity'])?'aria-current="page"':''?>>Overview</a><?php foreach(['locations'=>'Locations','routes'=>'Routes','languages'=>'Languages'] as $key=>$label): ?><a href="?entity=<?=e($key)?>" <?=($_GET['entity']??'')===$key?'aria-current="page"':''?>><?=e($label)?></a><?php endforeach ?><?php if(!empty($_SESSION['uid'])): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><button class="secondary" name="action" value="logout">Sign out</button></form><?php endif ?></nav><div class="admin-heading"><p class="eyebrow">InBorgo · Content workspace</p><h1><?=e($title)?></h1></div>
<?php }
function admin_footer(): void {echo '</main><footer class="admin-footer">InBorgo Tour · Content administration</footer></body></html>';}
function field(string $name,string $label,$value='',string $type='text',string $extra=''): void {
 $id='field-'.preg_replace('/[^a-zA-Z0-9_-]/','-',$name);
 $help=['slug'=>'Lowercase letters and numbers, separated by hyphens. Used in the page URL.','trigger_radius'=>'5–500 metres. The distance at which a visitor can trigger this location.','featured_image'=>'Use a local image path. For locations, upload a photo below, then copy its path here.','opening_hours'=>'Displayed as text; this does not calculate availability.','closed_days'=>'Enter weekday numbers separated by commas: 1 Monday through 7 Sunday.','accessibility_notes'=>'Internal staff notes. Visitor-facing accessibility information belongs in the translations below.','general_notes'=>'Internal staff notes, not visitor-facing content.','is_active'=>'Active content is available to visitors. Uncheck to deactivate.','is_demo'=>'Marks unverified coordinates or content. DEMO does not make an active location private.','code'=>'For example en or it. Existing codes must remain unique.','is_default'=>'The default language must be active. Choose a different default before disabling this one.','sort_order'=>'Whole number, 0–100000. Lower numbers appear first.','estimated_duration_minutes'=>'Whole minutes, 0–100000. Verify before publishing.','estimated_distance_m'=>'Metres, 0–100000. Verify before publishing.'];
 $hint=$help[$name]??'';
 if(preg_match('/\[title\]$/',$name))$hint='A blank title removes this translation when saved; visitors see a labelled fallback language.';
 $attrs=' id="'.e($id).'"';if($hint)$attrs.=' aria-describedby="'.e($id).'-help"';
 $required=in_array($name,['internal_name','slug','latitude','longitude','trigger_radius','estimated_duration_minutes','estimated_distance_m','code','name','native_name','sort_order']);
 if($required)$attrs.=' required';
 $max=['internal_name'=>190,'slug'=>160,'code'=>16,'name'=>80,'native_name'=>80,'difficulty'=>80,'closed_days'=>30,'caption'=>255,'featured_image'=>255,'opening_hours'=>255];
 if(isset($max[$name]))$attrs.=' maxlength="'.$max[$name].'"';
 if(preg_match('/\[title\]$/',$name))$attrs.=' maxlength="190"';
 $ranges=['latitude'=>[-90,90],'longitude'=>[-180,180],'trigger_radius'=>[5,500],'sort_order'=>[0,100000],'estimated_duration_minutes'=>[0,100000],'estimated_distance_m'=>[0,100000]];
 if(isset($ranges[$name]))$attrs.=' min="'.$ranges[$name][0].'" max="'.$ranges[$name][1].'"';
 if(in_array($name,['trigger_radius','sort_order','estimated_duration_minutes','estimated_distance_m']))$extra=str_replace('step="any"','step="1"',$extra);
 echo '<div class="admin-field'.($type==='checkbox'?' admin-check':'').'"><label for="'.e($id).'">'.e($label).($required?' <span class="required-note">(required)</span>':'');
 if($type==='textarea') echo '<textarea name="'.e($name).'"'.$attrs.' '.$extra.'>'.e($value).'</textarea>';
 elseif($type==='checkbox') echo '<input type="checkbox" name="'.e($name).'" value="1" '.($value?'checked':'').$attrs.' '.$extra.'>';
 else echo '<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'"'.$attrs.' '.$extra.'>';
 echo '</label>';if($hint)echo '<p class="field-help" id="'.e($id).'-help">'.e($hint).'</p>';echo '</div>';
}
function admin_field_groups(string $entity): array {
 if($entity==='locations')return ['Identity'=>['internal_name','slug'],'Coordinates & arrival'=>['latitude','longitude','trigger_radius'],'Visitor information'=>['featured_image','opening_hours','closed_days','guided_only'],'Internal notes'=>['accessibility_notes','general_notes'],'Visibility & verification'=>['is_demo','is_active']];
 if($entity==='routes')return ['Identity & cover'=>['internal_name','slug','featured_image'],'Route information'=>['estimated_duration_minutes','estimated_distance_m','difficulty'],'Visibility'=>['is_active']];
 return ['Language identity'=>['code','name','native_name'],'Order & availability'=>['sort_order','is_default','is_active']];
}
function entities(): array {return [
 'languages'=>['code'=>'Language code','name'=>'Name','native_name'=>'Native name','sort_order'=>'Sort order','is_default'=>'Default language','is_active'=>'Active'],
 'locations'=>['internal_name'=>'Internal name','slug'=>'Slug','latitude'=>'Latitude','longitude'=>'Longitude','trigger_radius'=>'Trigger radius (metres)','featured_image'=>'Featured image path (upload below)','opening_hours'=>'Opening hours (display only)','closed_days'=>'Closed weekdays (ISO: 1=Mon, comma-separated)','guided_only'=>'Guided visit only','accessibility_notes'=>'Internal accessibility notes','general_notes'=>'Internal notes','is_demo'=>'DEMO coordinates / content','is_active'=>'Active'],
 'routes'=>['internal_name'=>'Internal name','slug'=>'Slug','featured_image'=>'Cover image path','estimated_duration_minutes'=>'Duration (minutes)','estimated_distance_m'=>'Distance (metres)','difficulty'=>'Difficulty (optional)','is_active'=>'Active']
];}
function translation_fields(string $entity): array {return $entity==='locations'?['title','short_description','narration_text','full_description','opening_information','accessibility_information']:['title','short_description','full_description','starting_instructions'];}
function int_value($v,int $min,int $max,string $label): int { if(filter_var($v,FILTER_VALIDATE_INT)===false || (int)$v<$min || (int)$v>$max) throw new InvalidArgumentException("Invalid $label ($min–$max)."); return (int)$v; }
function media_upload(int $id): void {
 $f=$_FILES['media']??null; if(!$f || $f['error']===UPLOAD_ERR_NO_FILE)return;
 if($f['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Upload failed. Check the server upload limit.');
 if($f['size']>25*1024*1024)throw new InvalidArgumentException('Maximum file size is 25 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
 $types=['image/jpeg'=>['jpg','image'],'image/png'=>['png','image'],'image/webp'=>['webp','image'],'audio/mpeg'=>['mp3','audio']];
 if(!isset($types[$mime]))throw new InvalidArgumentException('Only JPEG, PNG, WebP and MP3 are accepted.');
 [$ext,$type]=$types[$mime];
 if($type==='image') { $size=getimagesize($f['tmp_name']); if(!$size || $size[0]*$size[1]>24000000)throw new InvalidArgumentException('Image is invalid or exceeds 24 megapixels.'); }
 $lid=empty($_POST['media_language'])?null:int_value($_POST['media_language'],1,100000,'media language');
 if($type==='audio'&&!$lid)throw new InvalidArgumentException('Choose a language for narration audio.');
 if($lid&&!rows('SELECT id FROM languages WHERE id=?',[$lid]))throw new InvalidArgumentException('Unknown language.');
 $path='/uploads/'.bin2hex(random_bytes(16)).'.'.$ext;
 if(!move_uploaded_file($f['tmp_name'],dirname(__DIR__).'/public'.$path))throw new RuntimeException('Upload directory is not writable.');
 try { run('INSERT INTO location_media (location_id,language_id,type,file_path,caption) VALUES (?,?,?,?,?)',[$id,$lid,$type,$path,substr(trim($_POST['caption']??''),0,255)]); }
 catch(Throwable $ex){unlink(dirname(__DIR__).'/public'.$path);throw $ex;}
}
function save_entity(string $entity,int $id): int {
 $values=[];
 foreach(entities()[$entity] as $key=>$label){
  $v=trim((string)($_POST[$key]??''));
  if(in_array($key,['is_active','is_default','is_demo','guided_only']))$v=isset($_POST[$key])?1:0;
  elseif(in_array($key,['sort_order','trigger_radius','estimated_duration_minutes','estimated_distance_m']))$v=int_value($v,$key==='trigger_radius'?5:0,$key==='trigger_radius'?500:100000,$label);
  elseif(in_array($key,['latitude','longitude'])){ $limit=$key==='latitude'?90:180;if(!is_numeric($v)||abs((float)$v)>$limit)throw new InvalidArgumentException('Invalid '.$label); }
  elseif($key==='slug'&&!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$v))throw new InvalidArgumentException('Use a lowercase hyphenated slug.');
  elseif($key==='code'&&!preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/D',$v))throw new InvalidArgumentException('Invalid language code.');
  elseif($key==='closed_days'&&$v!==''&&!preg_match('/^[1-7](?:,[1-7])*$/D',$v))throw new InvalidArgumentException('Closed days must be numbers 1–7 separated by commas.');
  elseif($key==='featured_image'&&$v!==''&&!preg_match('~^/(?:uploads/[a-f0-9]{32}\.(?:jpg|png|webp)|assets/images/[a-z0-9.-]+)$~D',$v))throw new InvalidArgumentException('Choose a local uploaded image path.');
  if(in_array($key,['internal_name','code','name','native_name'])&&$v==='')throw new InvalidArgumentException($label.' is required.');
  $max=['slug'=>160,'code'=>16,'name'=>80,'native_name'=>80,'difficulty'=>80,'closed_days'=>30,'internal_name'=>190][$key]??255;
  if(is_string($v)&&strlen($v)>$max&&!in_array($key,['general_notes','accessibility_notes']))throw new InvalidArgumentException($label.' is too long.');
  $values[$key]=$v;
 }
 db()->beginTransaction();
 try {
  if($entity==='languages') {
   // Serialize changes so staff cannot accidentally remove the last active/default language.
   $all=rows('SELECT * FROM languages ORDER BY id FOR UPDATE');
   if($values['is_default']&&!$values['is_active'])throw new InvalidArgumentException('The default language must be active.');
   $others=array_filter($all,function($l)use($id){return (int)$l['id']!==$id && $l['is_active'] && $l['is_default'];});
   if(!$values['is_default']&&!$others)throw new InvalidArgumentException('Choose another default language first.');
   if($values['is_default'])run('UPDATE languages SET is_default=0');
  }
  $keys=array_keys($values);
  if($id){if(!rows("SELECT id FROM $entity WHERE id=?",[$id]))throw new InvalidArgumentException('Record no longer exists.');run("UPDATE $entity SET ".implode(',',array_map(function($k){return "$k=?";},$keys)).' WHERE id=?',array_merge(array_values($values),[$id]));}
  else {run("INSERT INTO $entity (".implode(',',$keys).') VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($values));$id=(int)db()->lastInsertId();}
  if($entity!=='languages'){
   $table=$entity==='locations'?'location_translations':'route_translations'; $fk=$entity==='locations'?'location_id':'route_id'; $fields=translation_fields($entity);
   foreach(rows('SELECT * FROM languages') as $lang){
    $tr=$_POST['translations'][$lang['id']]??[];
    if(trim($tr['title']??'')===''){run("DELETE FROM $table WHERE $fk=? AND language_id=?",[$id,$lang['id']]);continue;}
    if(strlen($tr['title'])>190)throw new InvalidArgumentException('Translation title is too long.');
    $vals=[]; foreach($fields as $k){$v=trim((string)($tr[$k]??''));if(strlen($v)>50000)throw new InvalidArgumentException('Translation is too long.');$vals[]=$v;}
    run("INSERT INTO $table ($fk,language_id,".implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($fields)+2,'?')).') ON DUPLICATE KEY UPDATE '.implode(',',array_map(function($k){return "$k=VALUES($k)";},$fields)),array_merge([$id,$lang['id']],$vals));
   }
  }
  db()->commit();
 }catch(Throwable $ex){db()->rollBack();throw $ex;}
 return $id;
}
