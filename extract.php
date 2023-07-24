<?php
/**
* ignored_files.txtにGitに含まれていないファイルをリストアップ
* include_files.txtに.ftpincludeのルールに一致するファイルをリストアップ
*/
if(substr($_SERVER['SERVER_PROTOCOL']??'',0,4)==='HTTP'){
	die("Execute this PHP with CLI !");
}
ini_set("error_log","php://stdout");
include __DIR__.'/inc/functions.php';

$d=__DIR__.'/fileset';
if(!is_dir($d)){mkdir($d,0755);}
file_put_contents($d.'/ignored_files.txt',implode("\n",extract_ignored_files()));
file_put_contents($d.'/include_files.txt',implode("\n",extract_include_files()));