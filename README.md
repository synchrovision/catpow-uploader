Catpow Uploader
===

<img src="https://img.shields.io/badge/PHP-8.0-45A?logo=php"> 

Upload files via ftp|sfpt by static|dynamic fileset.


install
--

 ```command
git clone --recursive https://github.com/synchrovision/catpow-uploader.git _uploader
 ```

or

 ```command
git submodule add https://github.com/synchrovision/catpow-uploader.git _uploader
 ```


detail
--


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



enviroment
--

Require PHP 8.0 CLI
