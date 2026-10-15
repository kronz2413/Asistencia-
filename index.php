<?php
session_start(); require __DIR__.'/config.php';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function go($u){header("Location: $u");exit;}
function msg($s,$type='ok'){$_SESSION['msg']=['s'=>$s,'t'=>$type];}
function token(){if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return $_SESSION['csrf'];}
function csrf(){echo '<input type="hidden" name="csrf" value="'.h(token()).'">';}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Formulario expirado. Vuelve atrás e inténtalo otra vez.');}
 $a=$_POST['action']??'';
 if($a==='meeting'){
  $v=[];foreach(['actividad','monitor','lugar','fecha','hora'] as $k)$v[$k]=trim($_POST[$k]??'');
  $d=filter_var($_POST['duracion']??'',FILTER_VALIDATE_INT);
  if(in_array('',array_values($v),true)||!$d||$d<1){msg('Completa todos los campos y coloca una duración válida.','err');go('?p=create');}
  $code=bin2hex(random_bytes(16));
  $q=$pdo->prepare('INSERT INTO reuniones(codigo,actividad,monitor,lugar,fecha,hora,duracion) VALUES(?,?,?,?,?,?,?)');
  $q->execute([$code,$v['actividad'],$v['monitor'],$v['lugar'],$v['fecha'],$v['hora'],$d]);
  go('?p=created&id='.$pdo->lastInsertId());
 }
 if($a==='person'){
  try{$q=$pdo->prepare('INSERT INTO personas(cedula,nombre,sexo,cargo,institucion) VALUES(?,?,?,?,?)');
   $q->execute([trim($_POST['cedula']),trim($_POST['nombre']),$_POST['sexo'],trim($_POST['cargo']),trim($_POST['institucion']?:'INAP')]);msg('Persona registrada.');}
  catch(Throwable $e){msg('No se pudo guardar. Comprueba que la cédula no esté repetida.','err');} go('?p=people');
 }
 if($a==='deleteperson'){
  try{$q=$pdo->prepare('DELETE FROM personas WHERE id=?');$q->execute([(int)$_POST['id']]);msg('Persona eliminada.');}
  catch(Throwable $e){msg('No se puede eliminar: la persona tiene asistencias registradas.','err');}go('?p=people');
 }
 if($a==='attendance'){
  $q=$pdo->prepare('SELECT * FROM reuniones WHERE id=? AND codigo=?');$q->execute([(int)$_POST['rid'],$_POST['code']??'']);$r=$q->fetch();
  $ced=trim($_POST['cedula']??'');$sig=$_POST['signature']??'';
  if(!$r){msg('Enlace de reunión inválido.','err');go('./');}
  if(strpos($sig,'data:image/png;base64,')!==0||strlen($sig)<100){msg('Debes firmar antes de registrar la asistencia.','err');go('?p=attend&code='.urlencode($r['codigo']));}
  $q=$pdo->prepare('SELECT id FROM personas WHERE cedula=?');$q->execute([$ced]);$person=$q->fetch();
  if(!$person){msg('Cédula no encontrada. Contacta al personal encargado para registrar tus datos.','err');go('?p=attend&code='.urlencode($r['codigo']));}
  try{$q=$pdo->prepare('INSERT INTO asistencias(reunion_id,persona_id,firma) VALUES(?,?,?)');$q->execute([$r['id'],$person['id'],$sig]);msg('¡Asistencia registrada correctamente!');}
  catch(Throwable $e){msg('Esta persona ya registró asistencia a esta reunión.','err');}
  go('?p=attend&code='.urlencode($r['codigo']).'&done=1');
 }
}
$p=$_GET['p']??'home'; $id=(int)($_GET['id']??0);$code=$_GET['code']??'';
if($p==='lookup'){header('Content-Type: application/json; charset=utf-8');$q=$pdo->prepare('SELECT nombre,sexo,cargo,institucion FROM personas WHERE cedula=?');$q->execute([trim($_GET['cedula']??'')]);echo json_encode($q->fetch()?:['error'=>'Cédula no encontrada en el registro.'],JSON_UNESCAPED_UNICODE);exit;}
$r=null;
if(in_array($p,['created','report'])){$q=$pdo->prepare('SELECT * FROM reuniones WHERE id=?');$q->execute([$id]);$r=$q->fetch();}
if($p==='attend'){$q=$pdo->prepare('SELECT * FROM reuniones WHERE codigo=?');$q->execute([$code]);$r=$q->fetch();if(!$r)$p='notfound';}
$meetings=$pdo->query('SELECT r.*,(SELECT COUNT(*) FROM asistencias a WHERE a.reunion_id=r.id) n FROM reuniones r ORDER BY r.fecha DESC,r.hora DESC')->fetchAll();
$people=$pdo->query('SELECT * FROM personas ORDER BY nombre')->fetchAll();
$stats=[(int)$pdo->query('SELECT COUNT(*) FROM reuniones')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM personas')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM asistencias')->fetchColumn()];
$flash=$_SESSION['msg']??null;unset($_SESSION['msg']);
$public=$p==='attend';
function headerhtml($title,$active='home',$public=false){global $flash;?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?> | INAP</title><link rel="stylesheet" href="assets/style.css"></head><body>
<?php if(!$public):?><div class="layout"><aside class="side"><a class="brand" href="./"><b>♜ INAP</b><small>Sistema de Reuniones</small></a><nav>
<a class="<?=$active==='home'?'active':''?>" href="./">⌂ &nbsp; Inicio</a><a class="<?=$active==='create'?'active':''?>" href="?p=create">▣ &nbsp; Crear Reunión</a><a class="<?=$active==='meetings'?'active':''?>" href="?p=meetings">▦ &nbsp; Mis Reuniones</a><a class="<?=$active==='people'?'active':''?>" href="?p=people">♙ &nbsp; Personas</a><a class="<?=$active==='reports'?'active':''?>" href="?p=reports">▥ &nbsp; Reportes</a></nav><div class="sidebottom">INAP<br><small>Sistema de Reuniones</small></div></aside><main class="main"><header class="top">Sistema de Reuniones y Asistencia <span>INAP</span></header>
<?php else:?><main class="public"><header class="publictop"><b>♜ INAP</b> &nbsp; Registro de Asistencia</header><?php endif;?>
<?php if($flash):?><div class="flash <?=$flash['t']?>"><?=h($flash['s'])?></div><?php endif;?>
<?php }
function footerhtml($public=false){echo $public?'</main>':'</main></div>';echo '<footer>INAP · Instituto Nacional de Administración Pública</footer><script src="assets/app.js"></script></body></html>';}
?>
<?php if($p==='home'):headerhtml('Inicio','home');?>
<section class="content"><h1>Bienvenido al Sistema de Reuniones<br>INAP</h1><p class="muted">Gestiona, crea y controla las reuniones de forma fácil y segura.</p>
<div class="sectiontitle"><h2>Accesos principales</h2><span>Panel de inicio</span></div><div class="cards">
<article><div class="ico">▣</div><h3>Crear Reunión</h3><p>Registra una nueva reunión y genera su enlace y código QR.</p><a class="btn" href="?p=create">＋ Crear Reunión</a></article>
<article><div class="ico">▦</div><h3>Mis Reuniones</h3><p>Consulta las reuniones creadas y el número de asistentes.</p><a class="btn light" href="?p=meetings">Ver reuniones →</a></article>
<article><div class="ico">♙</div><h3>Personas</h3><p>Registra los datos necesarios para verificar las cédulas.</p><a class="btn light" href="?p=people">Ver personas →</a></article>
<article><div class="ico">▥</div><h3>Reportes</h3><p>Consulta, imprime o guarda como PDF los listados.</p><a class="btn light" href="?p=reports">Ver reportes →</a></article></div>
<div class="stats"><div><small>Reuniones</small><strong><?=$stats[0]?></strong></div><div><small>Personas</small><strong><?=$stats[1]?></strong></div><div><small>Asistencias</small><strong><?=$stats[2]?></strong></div></div>
<div class="notice"><b>ⓘ</b><span><strong>Sistema de Reuniones INAP</strong><br>Esta versión permite crear reuniones sin iniciar sesión. Los roles y permisos quedan fuera por ahora.</span></div>
<div class="sectiontitle"><h2>Reuniones recientes</h2><a href="?p=meetings">Ver todas</a></div><?php if(!$meetings):?><div class="empty">Todavía no hay reuniones. Pulsa “Crear Reunión” para comenzar.</div><?php else:?><div class="tablewrap"><table><tr><th>Actividad</th><th>Fecha</th><th>Hora</th><th>Asistentes</th><th></th></tr><?php foreach(array_slice($meetings,0,5) as $m):?><tr><td><?=h($m['actividad'])?></td><td><?=h($m['fecha'])?></td><td><?=h(substr($m['hora'],0,5))?></td><td><?=$m['n']?></td><td><a href="?p=created&id=<?=$m['id']?>">Abrir</a></td></tr><?php endforeach;?></table></div><?php endif;?></section><?php footerhtml();?>

<?php elseif($p==='create'):headerhtml('Crear reunión','create');?>
<section class="content narrow"><a class="back" href="./">← Volver al inicio</a><h1>Crear Nueva Reunión</h1><p class="muted">Completa los datos. Al guardar se generará un enlace y un código QR.</p><form class="panel formgrid" method="post"><?php csrf();?><input type="hidden" name="action" value="meeting">
<label class="wide">Actividad *<input required name="actividad" placeholder="Ej. Levantamiento de Procesos a Automatizar"></label>
<label class="wide">Monitor *<input required name="monitor" placeholder="Ej. Tecnología de la Información"></label>
<label class="wide">Lugar *<input required name="lugar" placeholder="Ej. Departamento de Comunicaciones"></label>
<label>Fecha *<input type="date" name="fecha" required></label><label>Hora *<input type="time" name="hora" required></label><label class="wide">Duración (minutos) *<input type="number" name="duracion" min="1" required placeholder="Ej. 45"></label>
<div class="wide right"><button class="btn">▣ Crear Reunión</button></div></form></section><?php footerhtml();?>

<?php elseif($p==='created'&&$r):headerhtml('Reunión creada','meetings');?>
<section class="content narrow"><div class="success"><span>✓</span><h1>¡Reunión creada correctamente!</h1><p class="muted">Comparte el enlace o QR con los participantes.</p></div><div class="panel"><dl class="details"><?php foreach(['Actividad'=>'actividad','Monitor'=>'monitor','Lugar'=>'lugar','Fecha'=>'fecha','Hora'=>'hora','Duración'=>'duracion'] as $label=>$key):?><dt><?=$label?>:</dt><dd><?=h($key==='hora'?substr($r[$key],0,5):$r[$key])?><?=$key==='duracion'?' minutos':''?></dd><?php endforeach;?></dl>
<label class="label">Enlace de asistencia</label><div class="copyrow"><input id="attendanceUrl" readonly value="<?=h('http://'.($_SERVER['HTTP_HOST']??'localhost').dirname($_SERVER['SCRIPT_NAME']).'/index.php?p=attend&code='.$r['codigo'])?>"><button class="btn" onclick="copyLink()">Copiar</button></div><div class="qrrow"><div id="qrcode" data-url="<?=h('http://'.($_SERVER['HTTP_HOST']??'localhost').dirname($_SERVER['SCRIPT_NAME']).'/index.php?p=attend&code='.$r['codigo'])?>"></div><button class="btn" onclick="downloadQR()">↓ Descargar QR</button></div><p class="muted tiny">La generación del QR requiere conexión a internet.</p></div><p><a class="btn light" href="?p=meetings">Ver reuniones</a> <a class="btn" href="?p=create">＋ Crear otra reunión</a></p></section><?php footerhtml();?>

<?php elseif($p==='meetings'):headerhtml('Mis reuniones','meetings');?>
<section class="content"><div class="titleline"><div><h1>Mis Reuniones</h1><p class="muted">Consulta reuniones y abre sus listados de asistencia.</p></div><a class="btn" href="?p=create">＋ Crear Reunión</a></div><?php if(!$meetings):?><div class="empty">No hay reuniones registradas todavía.</div><?php else:?><div class="tablewrap"><table><tr><th>Actividad</th><th>Monitor</th><th>Fecha</th><th>Hora</th><th>Asistentes</th><th>Acciones</th></tr><?php foreach($meetings as $m):?><tr><td><?=h($m['actividad'])?></td><td><?=h($m['monitor'])?></td><td><?=h($m['fecha'])?></td><td><?=h(substr($m['hora'],0,5))?></td><td><?=$m['n']?></td><td><a href="?p=created&id=<?=$m['id']?>">Enlace/QR</a> · <a href="?p=report&id=<?=$m['id']?>">Asistencia</a></td></tr><?php endforeach;?></table></div><?php endif;?></section><?php footerhtml();?>

<?php elseif($p==='people'):headerhtml('Personas','people');?>
<section class="content"><h1>Personas</h1><p class="muted">Registra los datos que se completarán al verificar una cédula.</p><form class="panel formgrid" method="post"><?php csrf();?><input type="hidden" name="action" value="person">
<label>Cédula *<input name="cedula" required placeholder="001-0000000-0"></label><label>Nombre y apellido *<input name="nombre" required></label><label>Sexo *<select name="sexo"><option value="F">Femenino (F)</option><option value="M">Masculino (M)</option><option value="Otro">Otro</option></select></label><label>Cargo *<input name="cargo" required></label><label class="wide">Institución<input name="institucion" value="INAP" required></label><div class="wide right"><button class="btn">Guardar persona</button></div></form>
<div class="sectiontitle"><h2>Personas registradas</h2><span><?=count($people)?> en total</span></div><div class="tablewrap"><table><tr><th>Cédula</th><th>Nombre y apellido</th><th>Sexo</th><th>Cargo</th><th>Institución</th><th></th></tr><?php foreach($people as $person):?><tr><td><?=h($person['cedula'])?></td><td><?=h($person['nombre'])?></td><td><?=h($person['sexo'])?></td><td><?=h($person['cargo'])?></td><td><?=h($person['institucion'])?></td><td><form method="post" onsubmit="return confirm('¿Eliminar persona?')"><?php csrf();?><input type="hidden" name="action" value="deleteperson"><input type="hidden" name="id" value="<?=$person['id']?>"><button class="linkbtn danger">Eliminar</button></form></td></tr><?php endforeach;?></table></div></section><?php footerhtml();?>

<?php elseif($p==='attend'&&$r):headerhtml('Registro de asistencia','',true);?>
<section class="publiccontent"><?php if(isset($_GET['done'])):?><div class="panel center"><div class="successmark">✓</div><h1>¡Gracias!</h1><p class="muted">El sistema procesó tu registro. Si ya habías firmado, no se crea una segunda asistencia.</p></div><?php else:?><div class="panel"><div class="personicon">♟</div><h1 class="center">Registro de Asistencia</h1><p class="muted center">Introduce tu cédula, verifica tus datos y firma.</p><div class="meetinginfo"><div><b>Actividad</b><?=h($r['actividad'])?></div><div><b>Monitor</b><?=h($r['monitor'])?></div><div><b>Lugar</b><?=h($r['lugar'])?></div><div><b>Fecha</b><?=h($r['fecha'])?></div><div><b>Hora</b><?=h(substr($r['hora'],0,5))?></div><div><b>Duración</b><?=h($r['duracion'])?> minutos</div></div>
<form id="attendanceForm" method="post"><?php csrf();?><input type="hidden" name="action" value="attendance"><input type="hidden" name="rid" value="<?=$r['id']?>"><input type="hidden" name="code" value="<?=h($r['codigo'])?>"><input type="hidden" name="signature" id="signature">
<label>Cédula *<div class="copyrow"><input id="cedula" name="cedula" required placeholder="001-0000000-0"><button class="btn" type="button" onclick="lookupPerson()">Verificar</button></div></label><div id="lookupMessage" class="lookupmsg"></div>
<div class="autofill"><label>Nombre y apellido<input id="personName" readonly></label><label>Sexo<input id="personSex" readonly></label><label>Cargo<input id="personCargo" readonly></label><label>Institución<input id="personInstitution" readonly></label></div>
<label class="label">Firma del participante *</label><canvas id="signatureCanvas" width="900" height="220"></canvas><div class="between"><button class="btn light" type="button" onclick="clearSignature()">Limpiar</button><button class="btn">✓ Registrar Asistencia</button></div></form></div><?php endif;?></section><?php footerhtml(true);?>

<?php elseif($p==='reports'):headerhtml('Reportes','reports');?>
<section class="content"><h1>Reportes de asistencia</h1><p class="muted">Selecciona una reunión para consultar, imprimir o guardar su listado como PDF.</p><?php if(!$meetings):?><div class="empty">Todavía no hay reuniones.</div><?php else:?><div class="tablewrap"><table><tr><th>Actividad</th><th>Fecha</th><th>Hora</th><th>Asistentes</th><th></th></tr><?php foreach($meetings as $m):?><tr><td><?=h($m['actividad'])?></td><td><?=h($m['fecha'])?></td><td><?=h(substr($m['hora'],0,5))?></td><td><?=$m['n']?></td><td><a href="?p=report&id=<?=$m['id']?>">Ver listado</a></td></tr><?php endforeach;?></table></div><?php endif;?></section><?php footerhtml();?>

<?php elseif($p==='report'&&$r):$q=$pdo->prepare('SELECT p.nombre,p.sexo,p.cargo,p.institucion,a.firma,a.registrado_en FROM asistencias a JOIN personas p ON p.id=a.persona_id WHERE a.reunion_id=? ORDER BY a.registrado_en');$q->execute([$id]);$att=$q->fetchAll();headerhtml('Listado de asistencia','reports');?>
<section class="content printable"><div class="titleline noprint"><div><a class="back" href="?p=reports">← Volver a reportes</a><h1>Listado de Asistencia</h1></div><button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button></div><div class="printhead"><h2>INAP · Listado de Asistencia</h2><p><b>Actividad:</b> <?=h($r['actividad'])?> &nbsp; <b>Monitor:</b> <?=h($r['monitor'])?></p><p><b>Lugar:</b> <?=h($r['lugar'])?> &nbsp; <b>Fecha:</b> <?=h($r['fecha'])?> &nbsp; <b>Horario:</b> <?=h(substr($r['hora'],0,5))?> &nbsp; <b>Duración:</b> <?=h($r['duracion'])?> minutos</p></div><div class="tablewrap"><table><tr><th>No.</th><th>Nombre y Apellido</th><th>Sexo</th><th>Cargo</th><th>Institución</th><th>Firma</th><th>Hora</th></tr><?php if(!$att):?><tr><td colspan="7">Todavía no hay asistencias registradas.</td></tr><?php else:foreach($att as $i=>$a):?><tr><td><?=$i+1?></td><td><?=h($a['nombre'])?></td><td><?=h($a['sexo'])?></td><td><?=h($a['cargo'])?></td><td><?=h($a['institucion'])?></td><td><img class="signaturemini" src="<?=h($a['firma'])?>" alt="Firma"></td><td><?=h(date('H:i',strtotime($a['registrado_en'])))?></td></tr><?php endforeach;endif;?></table></div></section><?php footerhtml();?>

<?php else:headerhtml('No encontrado','home');?><section class="content"><h1>No encontramos esa página</h1><a class="btn" href="./">Volver al inicio</a></section><?php footerhtml();endif;?>
