yii2 extension - blockcity api client

deponds on linslin\yii2\curl 

*代码由[星际大陆](https://www.ipfsmain.cn "星际大陆")贡献*

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist ipfsmainofficial/yii2-blockcity-api "*"
```

or add

```
"ipfsmainofficial/yii2-blockcity-api": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
<?php 
$client = new ipfsmainofficial\blockcity\BlockcityClient(); 
?>
```


