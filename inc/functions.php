<?php
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

define('APP_PATH',dirname(__DIR__));
define('INC_PATH',__DIR__);
if(file_exists(APP_PATH.'/settings.php')){include APP_PATH.'/settings.php';}
if(!defined('ABSPATH')){define('ABSPATH',dirname(__DIR__,2));}
if(!defined('ENV_FILE_NAME')){define('ENV_FILE_NAME',null);}

chdir(ABSPATH);

require_once __DIR__.'/vendor/autoload.php';
$dotenv=Dotenv\Dotenv::createImmutable(APP_PATH,ENV_FILE_NAME);
$dotenv->safeLoad();
/* upload */
function upload_files($files){
	$files=extract_dir_to_files($files);
	if(isset($_ENV['SFTP_HOST'])){
		upload_files_with_sftp($files);
	}
	elseif(isset($_ENV['FTP_HOST'])){
		upload_files_with_ftp($files);
	}
	elseif(isset($_ENV['DIR'])){
		reflect_files($files);
	}
}
function extract_dir_to_files($files){
	for($i=0,$l=count($files);$i<$l;$i++){
		if(is_dir($files[$i])){
			$dir=trim(array_splice($files,$i,1)[0],'/');
			foreach(scandir($dir) as $file){
				if(in_array($file,['.','..','.DS_Store','_notes','.git'],true)){continue;}
				array_push($files,$dir.'/'.$file);
			}
			$i--;
			$l=count($files);
		}
	}
	return $files;
}
function upload_files_with_sftp($files){
	assert(isset($_ENV['SFTP_HOST']),'require SFTP_HOST');
	assert(isset($_ENV['SFTP_USER']),'require SFTP_USER');
	assert(isset($_ENV['SFTP_PEM']) || isset($_ENV['SFTP_PASSWORD']),'require SFTP_PEM or SFTP_PASSWORD');
	$sftp=new SFTP($_ENV['SFTP_HOST'],$_ENV['SFTP_PORT']??22);
	if(isset($_ENV['SFTP_PEM'])){
		$key=PublicKeyLoader::load(file_get_contents(APP_PATH.'/'.$_ENV['SFTP_PEM']),$_ENV['SFTP_PEM_PASSWORD']??false);
		if(!$sftp->login($_ENV['SFTP_USER'],$key)){
			echo "sftp failed to login with identical file {$_ENV['SFTP_PEM']}\n";
			return false;
		}
	}
	else if(isset($_ENV['SFTP_PASSWORD'])){
		if(!$sftp->login($_ENV['SFTP_USER'],$_ENV['SFTP_PASSWORD'])){
			echo "sftp failed to login with password\n";
			return false;
		}
	}
	echo "sftp connection start\n";
	if(isset($_ENV['SFTP_ROOT_PATH'])){
		if(!$sftp->chdir($_ENV['SFTP_ROOT_PATH'])){
			echo "sftp failed to change directory to {$_ENV['SFTP_ROOT_PATH']}\n";
			return false;
		}
	}
	foreach($files as $file){
		$dir=dirname($file);
		if(!$sftp->is_dir($dir)){$sftp->mkdir($dir,0755,true);}
		if(substr($file,0,2)==='- '){
			$file=substr($file,2);
			if($sftp->delete($file)){
				echo "delete {$file}\n";
			}
			continue;
		}
		if($sftp->put($file,ABSPATH.'/'.$file,SFTP::SOURCE_LOCAL_FILE)){
			echo "upload {$file}\n";
		}
		else{
			echo "failed to upload {$file}\n";
		}
	}
	if(!empty($sftp->getSFTPErrors())){
		echo "sftp error occurred\n";
		echo implode("\n",$sftp->getSFTPErrors());
	}
	echo "sftp connection end\n";
	
}
function upload_files_with_ftp($files){
	assert(isset($_ENV['FTP_HOST']),'require FTP_HOST');
	assert(isset($_ENV['FTP_USER']),'require FTP_USER');
	assert(isset($_ENV['FTP_PASSWORD']),'require FTP_PASSWORD');
	if(isset($_ENV['FTP_SSL']) && in_array(strtolower($_ENV['FTP_SSL']),['off','0'])){
		$con=ftp_connect($_ENV['FTP_HOST'],(int)($_ENV['FTP_PORT']??21));
	}
	else{
		$con=ftp_ssl_connect($_ENV['FTP_HOST'],(int)($_ENV['FTP_PORT']??21));
	}
	if(!empty($con) && ftp_login($con,$_ENV['FTP_USER'],$_ENV['FTP_PASSWORD'])){
		echo "ftp connection start\n";
		ftp_pasv($con,true);
		if(!empty($_ENV['FTP_ROOT_PATH'])){
			ftp_mkdir_recursive($con,$_ENV['FTP_ROOT_PATH']);
			ftp_chdir($con,$_ENV['FTP_ROOT_PATH']);
		}
		$dir=ABSPATH;
		foreach($files as $file){
			if(substr($file,0,2)==='- '){
				$file=substr($file,2);
				if(ftp_delete($con,$file)){
					echo "delete {$file}\n";
				}
				continue;
			}
			if($fp=fopen($dir.'/'.$file,'r')){
				ftp_mkdir_recursive($con,dirname($file));
				if(ftp_fput($con,$file,$fp,is_ascii_maybe($file)?FTP_ASCII:FTP_BINARY)){
					echo "upload {$file}\n";
				}
				else{
					echo "failed to upload {$file}\n";
				}
				fclose($fp);
			}
			else{
				echo "file {$file} not found\n";
			}
		}
		ftp_close($con);
		echo "ftp connection end\n";
	}
	else{
		echo "ftp connection failed\n";
	}
}
function ftp_mkdir_recursive($con,$dir){
	$org=ftp_pwd($con);
	$dir=rtrim($dir,'/');
	if(!empty($dir)){
		$path=explode('/',$dir);
		foreach($path as $dirname){
			if(!@ftp_chdir($con,$dirname)){
				ftp_mkdir($con,$dirname);
				ftp_chdir($con,$dirname);
			}
		}
	}
	@ftp_chdir($con,$org);
	return true;
}
function is_ascii_maybe($file){
	switch(strrchr($file,'.')){
		case '.json':
		case '.js':
		case '.jsx':
		case '.tsx':
		case '.csv':
		case '.html':
		case '.htm':
		case '.php':
		case '.css':
		case '.scss':
		case '.less':
		case '.md':
		case '.txt':
		case '.rtf':
			return true;
	}
	return false;
}
/*reflect*/
function reflect_files($files){
	assert(isset($_ENV['DIR']),'require DIR');
	$dir=rtrim($_ENV['DIR'],'/');
	foreach($files as $file){
		if(substr($file,0,2)==='- '){
			$file=substr($file,2);
			if(file_exists($dir.'/'.$file)){
				unlink($dir.'/'.$file);
				echo "delete {$file}\n";
			}
			continue;
		}
		if(file_exists(ABSPATH.'/'.$file)){
			if(!is_dir($d=$dir.'/'.dirname($file))){
				mkdir($d,0755,true);
			}
			if(copy(ABSPATH.'/'.$file,$dir.'/'.$file)){
				echo "copy {$file}\n";
			}
			else{
				echo "failed to copy {$file}\n";
			}
		}
		else{
			echo "file {$file} not found\n";
		}
	}
}
/* download */
function download_files($files){
	if(isset($_ENV['SFTP_HOST'])){
		download_files_with_sftp($files);
	}
	elseif(isset($_ENV['FTP_HOST'])){
		download_files_with_ftp($files);
	}
}
function download_files_with_sftp($files){
	assert(isset($_ENV['SFTP_HOST']),'require SFTP_HOST');
	assert(isset($_ENV['SFTP_USER']),'require SFTP_USER');
	assert(isset($_ENV['SFTP_PEM']) || isset($_ENV['SFTP_PASSWORD']),'require SFTP_PEM or SFTP_PASSWORD');
	$sftp=new SFTP($_ENV['SFTP_HOST'],$_ENV['SFTP_PORT']??22);
	if(isset($_ENV['SFTP_PEM'])){
		$key=PublicKeyLoader::load(file_get_contents(APP_PATH.'/'.$_ENV['SFTP_PEM']));
		if(!$sftp->login($_ENV['SFTP_USER'],$key)){
			echo "sftp failed to login with identical file {$_ENV['SFTP_PEM']}\n";
			return false;
		}
	}
	else if(isset($_ENV['SFTP_PASSWORD'])){
		if(!$sftp->login($_ENV['SFTP_USER'],$_ENV['SFTP_PASSWORD'])){
			echo "sftp failed to login with password\n";
			return false;
		}
	}
	echo "sftp connection start\n";
	if(isset($_ENV['SFTP_ROOT_PATH'])){
		if(!$sftp->chdir($_ENV['SFTP_ROOT_PATH'])){
			echo "sftp failed to change directory to {$_ENV['SFTP_ROOT_PATH']}\n";
			return false;
		}
	}
	foreach($files as $file){
		$dir=dirname(ABSPATH.'/'.$file);
		if(!is_dir($dir)){mkdir($dir,0755,true);}
		if($sftp->get($file,ABSPATH.'/'.$file)){
			echo "download {$file}\n";
		}
		else{
			echo "failed to download {$file}\n";
		}
	}
	if(!empty($sftp->getSFTPErrors())){
		echo "sftp error occurred\n";
		echo implode("\n",$sftp->getSFTPErrors());
	}
	echo "sftp connection end\n";
	
}
function download_files_with_ftp($files){
	assert(isset($_ENV['FTP_HOST']),'require FTP_HOST');
	assert(isset($_ENV['FTP_USER']),'require FTP_USER');
	assert(isset($_ENV['FTP_PASSWORD']),'require FTP_PASSWORD');
	$con=ftp_connect($_ENV['FTP_HOST'],$_ENV['FTP_PORT']??21);
	if(!empty($con) && ftp_login($con,$_ENV['FTP_USER'],$_ENV['FTP_PASSWORD'])){
		echo "ftp connection start\n";
		ftp_pasv($con,true);
		ftp_mkdir_recursive($con,$_ENV['FTP_ROOT_PATH']);
		ftp_chdir($con,$_ENV['FTP_ROOT_PATH']);
		$dir=ABSPATH;
		foreach($files as $file){
			$f=$dir.'/'.$file;
			if(!is_dir(dirname($f))){mkdir(dirname($f),0755,true);}
			if(ftp_get($con,$f,$file,is_ascii_maybe($file)?FTP_ASCII:FTP_BINARY)){
				echo "download {$file}\n";
			}
			else{
				echo "failed to download {$file}\n";
			}
		}
		ftp_close($con);
		echo "ftp connection end\n";
	}
	else{
		echo "ftp connection failed\n";
	}
}
/*package*/
function package_files($set,$files){
	$set_dir=APP_PATH.'/package/'.$set.'/';
	foreach($files as $file){
		if(!is_dir($dir=dirname($set_dir.$file))){mkdir($dir,0755,true);}
		passthru('cp -r '.ABSPATH.'/'.$file.' '.$set_dir.$file);
	}
}
function zip_files($set,$files){
	$zip=new ZipArchive();
	$zip->open(APP_PATH.'/package/'.$set.'.zip', ZipArchive::CREATE|ZipArchive::OVERWRITE);
	foreach($files as $file){
		$zip->addFile(ABSPATH.'/'.$file,$set.'/'.$file);
	}
	$zip->close();
}
/* ftpignore */
function get_rel_path($from,$to){
	$from_path=explode('/',$from);
	$to_path=explode('/',$to);
	while(!empty($from_path) && !empty($to_path) && $from_path[0]===$to_path[0]){
		array_shift($from_path);
		array_shift($to_path);
	}
	return str_repeat('../',count($from_path)).implode('/',$to_path);
}
function get_ftpignore($dir){
	static $cache=[];
	if(isset($cache[$dir])){return $cache[$dir];}
	$rtn=[];
	if(file_exists($f=ABSPATH.'/'.$dir.'/.ftpignore')){
		foreach(file($f) as $line){
			$line=trim($line);
			if(empty($line)){continue;}
			if(substr($line,0,1)==='!'){
				$rtn[$dir]['keep'][]=substr($line,1);
			}
			else{
				$rtn[$dir]['ignore'][]=$line;
			}
		}
	}
	if(!empty($dir)){
		$rtn=array_merge(
			$rtn,
			get_ftpignore(
				(strpos($dir,'/')!==false)?dirname($dir):''
			)
		);
	}
	return $rtn;
}
function filter_ignore_files($files){
	return array_filter($files,function($file){
		foreach(get_ftpignore(dirname($file)) as $dir=>$ftpignore){
			$f=ltrim(substr($file,strlen($dir)),'/');
			if(isset($ftpignore['keep'])){
				foreach($ftpignore['keep'] as $pattern){
					if(fnmatch($pattern,$f)){continue 2;}
				}
			}
			foreach($ftpignore['ignore'] as $pattern){
				if(fnmatch($pattern,$f)){return false;}
			}
		}
		return true;
	});
}
/* ftpinclude */
function extract_include_files($dir='',$inherit_rules=[]){
	$files=[];
	$rules=$inherit_rules;
	if(file_exists($f=ABSPATH.$dir.'/.ftpinclude')){
		foreach(file($f) as $line){
			$line=trim($line);
			if(empty($line)){continue;}
			if(!preg_match('/^!?\//',$line)){$inherit_rules[]=$line;}
			$rules[]=$line;
		}
	}
	foreach($rules as $rule){
		if(substr($rule,0,1)==='!'){
			$files=array_diff($files,glob(ABSPATH.$dir.'/'.ltrim(substr($rule,1),'/')));
		}
		else{
			$files=array_unique(array_merge($files,glob(ABSPATH.$dir.'/'.ltrim($rule,'/'))));
		}
	}
	foreach($files as $i=>$file){
		$files[$i]=get_rel_path(ABSPATH,$file);
	}
	foreach(scandir(ABSPATH.$dir) as $fname){
		if($fname==='.' || $fname==='..'){continue;}
		if(is_dir($d=ABSPATH.$dir.'/'.$fname)){
			$files=array_merge($files,extract_include_files($dir.'/'.$fname,$inherit_rules));
		}
	}
	sort($files);
	return $files;
}
/* ignored */
function extract_ignored_files($dir=''){
	$files=[];
	$dirs=[$dir];
	$in_git_flags=array_flip(get_all_files_in_git(ltrim($dir,'/')));
	for($i=0;$i<count($dirs);$i++){
		$cd=$dirs[$i];
		foreach(scandir(ABSPATH.$cd) as $fname){
			if(in_array($fname,['.','..','.git','.DS_Store','_notes','node_modules'],true)){continue;}
			if($fname==='vendor' && file_exists(ABSPATH.$cd.'/composer.json')){continue;}
			if(is_dir($d=ABSPATH.$cd.'/'.$fname)){
				if(file_exists($d.'/.git')){
					$files=array_merge($files,extract_ignored_files($cd.'/'.$fname));
				}
				else{
					$dirs[]=$cd.'/'.$fname;
				}
			}
			else{
				$file=get_rel_path(ABSPATH,ABSPATH.$cd.'/'.$fname);
				if(!isset($in_git_flags[$file])){$files[]=$file;}
			}
		}
	}
	sort($files);
	return $files;
}
/* git */
function get_git_dir_info($dir=''){
	static $cache=[];
	$dir=realpath($dir);
	if(isset($cache[$dir])){return $cache[$dir];}
	while(!file_exists($dir.'/.git')){
		$dir=dirname($dir);
	}
	$rel_path=get_rel_path(ABSPATH,$dir);
	return $cache[$dir]=compact('dir','rel_path');
}
function do_git_command($command,$dir=''){
	$git_dir_info=get_git_dir_info($dir);
	chdir($git_dir_info['dir']);
	exec($command,$output);
	chdir(ABSPATH);
	return $output;
}
function get_all_files_in_git($dir=''){
	$rel_path=get_git_dir_info($dir)['rel_path'];
	$files=do_git_command('git ls-files',$dir);
	if(!empty($rel_path)){
		foreach($files as $i=>$file){
			$files[$i]=get_rel_path(ABSPATH,realpath($rel_path.'/'.$file));
		}
		$files=array_filter($files,function($file){return substr($file,0,3)!=='../';});
	}
	sort($files);
	return $files;
}
function get_files_for_commit($commit,$dir=''){
	$rel_path=get_git_dir_info($dir)['rel_path'];
	$files=do_git_command('git diff-tree -r --name-only --no-commit-id --diff-filter=d '.$commit,$dir);
	$removed_files=do_git_command('git diff-tree -r --name-only --no-commit-id --diff-filter=D '.$commit,$dir);
	if(!empty($rel_path)){
		foreach($files as $i=>$file){
			$files[$i]=get_rel_path(ABSPATH,realpath($rel_path.'/'.$file));
		}
		$files=array_filter($files,function($file){return substr($file,0,3)!=='../';});
		foreach($removed_files as $i=>$removed_file){
			$removed_files[$i]=get_rel_path(ABSPATH,realpath($rel_path.'/'.$removed_file));
		}
		$removed_files=array_filter($removed_files,function($removed_file){return substr($removed_file,0,3)!=='../';});
	}
	$files=array_merge($files,array_map(function($removed_file){return '- '.$removed_file;},$removed_files));
	sort($files);
	return $files;
}
function get_files_for_issue($issue,$dir=''){
	$files=[];
	$commits=do_git_command('git log --grep "'.$issue.'" --format="format:%H"',$dir);
	foreach($commits as $commit){
		$files=array_merge($files,get_files_for_commit($commit));
	}
	$files=array_unique($files);
	sort($files);
	return $files;
}
/* files */
function get_all_files_in_dir($dir=null){
	$files=[];
	foreach(scandir($dir??'./') as $fname){
		if(in_array($fname[0],['.','_'])){continue;}
		$f=$dir.$fname;
		if(is_dir($f)){
			$files=array_merge($files,get_all_files_in_dir($f.'/'));
			continue;
		}
		else{
			$files[]=$f;
		}
	}
	return $files;
}