<?php
/**
 * File: ArticleController.php
 * User: zhoucong@yongche.com
 * Date: 16/11/11
 * Time: 上午10:10
 */ 
Class ArticleController{
    public function index() {
        echo "index method <br />";
    }
    
    public function show($id) {
        var_dump('showing is '. $id);
    }
    
    public function edit($id) {
        var_dump('editing is '. $id );
    }
    
    public function update($id) {
        echo "now is editing!";
        var_dump($_REQUEST);
    }
}