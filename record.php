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
	$files=array_filter(array_map(function($file){
		if(!file_exists(ABSPATH.'/'.$file)){return null;}
		return $file;
	},get_files_for_issue($set)));
}
else{
	$files=file_exists($f)?explode("\n",file_get_contents($f)):[];
	if(substr($argv[2],0,1)==='#'){
		if(!empty($files_to_add=get_files_for_issue($argv[2],$argv[3]??''))){
			$files=array_merge($files,$files_to_add);
		}
	}
	elseif(strpos($argv[2],'/')===false && (strlen($argv[2])===7 || strlen($argv[2])===40)){
		if(!empty($files_to_add=get_files_for_commit($argv[2],$argv[3]??''))){
			$files=array_merge($files,$files_to_add);
		}
	}
	else{
		foreach(array_slice($argv,2) as $item){
			if(str_starts_with($item,ABSPATH)){$item=substr($item,strlen(ABSPATH)+1);}
			if(!file_exists(ABSPATH.'/'.$item)){echo "File {$item} does not exists\n";continue;}
			$files[]=$item;
		}
	}
	$files=array_unique($files);
	$files=array_filter($files,function($file){
		return file_exists(ABSPATH.'/'.$file);
	});
}
if(!is_dir($d=dirname($f))){mkdir($d);}
file_put_contents($f,implode("\n",$files));
echo "Preset {$set} was updated\n";
echo implode("\n",$files)."\n";