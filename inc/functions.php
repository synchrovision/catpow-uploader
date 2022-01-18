<?php
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

define('ABSPATH',dirname(__DIR__,2));
define('APP_PATH',dirname(__DIR__));
define('INC_PATH',__DIR__);
require_once __DIR__.'/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(APP_PATH);
$dotenv->safeLoad();
/* ファイルアップロード */
function upload_images(){
	$items=array_map('fill_item_data',json_decode(file_get_contents(__DIR__.'/data/items.json'),1));
	$files=[];
	foreach($items as $item){
		$files[trim(parse_url($item['image'],PHP_URL_PATH),'/')]=true;
	}
	upload_files(array_keys($files));
	echo "end upload\n";
	return true;
}
function upload_files($files){
	if(isset($_ENV['SFTP_HOST'])){
		upload_files_with_sftp($files);
	}
	elseif(isset($_ENV['FTP_HOST'])){
		upload_files_with_ftp($fils);
	}
}
function upload_files_with_sftp($files){
	assert(isset($_ENV['SFTP_HOST']),'require SFTP_HOST');
	assert(isset($_ENV['SFTP_USER']),'require SFTP_USER');
	assert(isset($_ENV['SFTP_PEM']) || isset($_ENV['SFTP_PASSWORD']),'require SFTP_PEM or SFTP_PASSWORD');
	$sftp=new SFTP($_ENV['SFTP_HOST'],$_ENV['SFTP_PORT']??22);
	if(isset($_ENV['SFTP_PEM'])){
		ob_start();
		passthru("cat {$_ENV['SFTP_PEM']}");
		$key=PublicKeyLoader::load(ob_get_clean());
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
		$sftp->put($file,ABSPATH.'/'.$file,SFTP::SOURCE_LOCAL_FILE);
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
	$con=ftp_connect($_ENV['FTP_HOST'],$_ENV['FTP_PORT']??21);
	if(!empty($con) && ftp_login($con,$_ENV['FTP_USER'],$_ENV['FTP_PASSWORD'])){
		echo "ftp connection start\n";
		ftp_mkdir_recursive($con,$_ENV['FTP_ROOT_PATH']);
		ftp_chdir($con,$_ENV['FTP_ROOT_PATH']);
		$dir=ABSPATH;
		foreach($files as $file){
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
	$path=explode('/',$dir);
	foreach($path as $dirname){
		if(!@ftp_chdir($con,$dirname)){
			ftp_mkdir($con,$dirname);
			ftp_chdir($con,$dirname);
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

/* git */
function get_git_dir_info(){
	static $dir_info;
	if(isset($dir_info)){return $dir_info;}
	$dir=ABSPATH;
	$rel_path='';
	while(!file_exists($dir.'/.git')){
		$rel_path=basename($dir).'/'.$rel_path;
		$dir=dirname($dir);
	}
	return compact('dir','rel_path');
}
function do_git_command($command){
	$git_dir_info=get_git_dir_info();
	chdir($git_dir_info['dir']);
	exec($command,$output);
	chdir(ABSPATH);
	return $output;
}
function get_files_for_issue($issue){
	$files=[];
	$commits=do_git_command('git log --grep "'.$issue.'" --format="format:%H"');
	foreach($commits as $commit){
		$files=array_merge($files,do_git_command('git diff-tree -r --name-only --no-commit-id '.$commit));
	}
	return array_unique($files);
}