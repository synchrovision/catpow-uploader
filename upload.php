<?php
/**
* 第一引数で指定したセットのファイルを.envで設定したファイルにFTPアップロード
*/
if(substr($_SERVER['SERVER_PROTOCOL']??'',0,4)==='HTTP'){
	die("Execute this PHP with CLI !");
}
ini_set("error_log","php://stdout");
include __DIR__.'/inc/functions.php';

if(empty($set=$argv[1]??null)){die('Require preset name or issue number as first parameter');}
if(file_exists($f=__DIR__.'/preset/'.$set.'.txt')){
	$files=explode("\n",file_get_contents($f));
}
elseif(file_exists($f=__DIR__.'/preset/'.$set.'.php')){
	$files=include $f;
}
elseif(substr($set,0,1)==='#'){
	$files=get_files_for_issue($set,$argv[2]??'');
}
if(!empty($files)){
	upload_files(filter_ignore_files($files));
}
	