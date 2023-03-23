Catpow Uploader
===

<img src="https://img.shields.io/badge/PHP-8.0-45A?logo=php"> 

Upload files via ftp|sfpt by static|dynamic fileset.


Install
--

 ```command
git clone --recursive https://github.com/synchrovision/catpow-uploader.git _uploader
 ```

or

 ```command
git submodule add https://github.com/synchrovision/catpow-uploader.git _uploader
 ```

Setup
--

create .env file in _uploader directory or directory that was installed catpow-uploader.  
Then write ftp host, user, password on it, like bellow.

```env
FTP_HOST="YOUR FTP HOST"
FTP_USER="YOUR USER NAME"
FTP_PASSWORD="YOUR PASSWORD"
FTP_ROOT_PATH="YOUR ROOT PATH"
```
Or, if you use SFTP and pem, write like bellow.

```env
SFTP_HOST="YOUR FTP HOST"
SFTP_USER="YOUR USER NAME"
SFTP_PASSWORD="YOUR PASSWORD"
SFTP_ROOT_PATH="YOUR ROOT PATH"
SFTP_PEM="PATH TO YOUR PEM FILE"
```

Usage
--

### Create Fileset

There's 5 way to create fileset

#### 1. Via CLI, with files.

```command
php record.php filesetname path/to/file1.php
```
You can register multiple file at once.

```command
php record.php filesetname path/to/file1.php path/to/file2.php path/to/file3.php ....
```

#### 2. Via CLI, with GitHub issue.

Catpow-uploader will search git repository from its ancestors, get it from nearest.

```command
php record.php filesetname #1
```

You can omit filesetname. If you do so, issue number (eg:#1) would be used as filesetname.

```command
php record.php #1
```

If you want to register files from nested git repository,
give path to the repository as argument 3.
If you do so, you cannot omit filesetname.


```command
php record.php filesetname #1 path/to/repository
```


#### 3. Via CLI, with GitHub commit.

You can also create fileset from commit.

```command
php record.php filesetname commitID
```

Same as creating from issue, you can extract commit from nested git repository.

```command
php record.php filesetname commitID path/to/repository
```

#### 4. Create static fileset in fileset directory.

Creating fileset via cli only means generate list of file with plain text file in fileset directory.
So that, you can create it directly.

```text
mypage/index.html
mypage/image/image1.jpg
mypage/css/style.css
mypage/js/script.css
```


#### 5. Create dynamic fileset in fileset directory.

If Catpow-uploader found php file with filesetname in fileset directory, Catpow-uploader use its return value as fileset.

```php 
return glob(ABSPATH.'/news/*.json');
```

### Upload Fileset

```command
php upload.php filesetname
```

Enviroment
--

Require PHP 8.0 CLI
