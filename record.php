<?php
/**
* 第一引数で指定したセットに第二引数で指定のコミットの差分ファイルの情報を結合
*/
if(substr($_SERVER['SERVER_PROTOCOL']??'',0,4)==='HTTP'){
	die("Execute this PHP with CLI !");
}
ini_set("error_log","php://stdout");
include __DIR__.'/inc/functions.php';

if(empty($set=$argv[1]??null)){die("Require preset name as first parameter\n");}

$f=__DIR__.'/preset/'.$set.'.txt';
if(substr($set,0,1)==='#'){
	$files=get_files_for_issue($set);
}
else{
	$files=file_exists($f)?explode("\n",file_get_contents($f)):[];
	$files_to_add=array_filter(array_map(function($file){
		if(str_starts_with($file,ABSPATH)){$file=substr($file,strlen(ABSPATH)+1);}
		if(!file_exists(ABSPATH.'/'.$file)){echo "File {$file} does not exists\n";return null;}
		return $file;
	},array_slice($argv,2)));
	$files=array_unique(array_merge($files,$files_to_add));
}
file_put_contents($f,implode("\n",$files));
echo "Following files was append to preset {$set}\n";
echo implode("\n",$files_to_add)."\n";
