<?php
require 'src/MyRoute.php';
require 'application/controller/IndexController.php';
require 'application/controller/ArticleController.php';

MyRoute::get('kiss/:id/:name', 'IndexController@index');

MyRoute::get('', function(){
    echo "hello! first step succuss!";
});

MyRoute::controller('article', 'ArticleController');

MyRoute::get(':id/:name', 'IndexController');

MyRoute::dispatch();

