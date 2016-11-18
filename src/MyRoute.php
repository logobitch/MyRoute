<?php

Class MyRoute{
    public static $_route = '';

    private static $allow_methods = array('GET', 'POST', 'PUT', 'DELETE', 'CONTROLLER');

    public static $routes = array();        //规则路由
    public static $methods = array();       //请求方法
    public static $callbacks = array();     //处理路由方法
    public static $limits = array();        //路由中限制的参数
    private static $error_callback = '';

    private $storage_dir = 'storage';
    private $route_cache_dir = 'route';
    private $route_cache_file = 'route_list';
    private $route_cache_file_dir = '';

    public $cache_route_init = false;
    
    public function __construct() {
        $this->route_cache_file_dir = __DIR__ . '/../'.$this->storage_dir . '/'. $this->route_cache_dir . '/'. $this->route_cache_file;
    }

    public static function __callstatic($method, $params) {
        if(empty(self::$_route)) {
            self::$_route = new MyRoute;
        }
        if(file_exists(self::$_route->route_cache_file_dir) && self::$_route->cache_route_init) {
            $route_data = file_get_contents(self::$_route->route_cache_file_dir);
            $route_data = json_decode(base64_decode($route_data));

            self::$callbacks = $route_data->callbacks;
            self::$routes = $route_data->routes;
            self::$methods = $route_data->methods;
            self::$limits = $route_data->limits;

            return;
        }

        self::$_route->_validMethod($method);

        $uri = '/' . $params[0];
        $callback = $params[1];

        if($method == 'controller') {
            $route_data = self::$_route->_getControllerData($uri, $callback);

            for($i=0; $i<7; $i++) {
                array_push(self::$routes, $route_data['uri_arr'][$i]);
                array_push(self::$methods, $route_data['method_arr'][$i]);
                array_push(self::$callbacks, $route_data['callback_arr'][$i]);
                array_push(self::$limits, array());
            }
        } else {
            //单一方法申请
            $rule = array();
            if(isset($params[2]) && !empty($params[2])) {
                $rule = $params[2];
            }
            array_push(self::$routes, $uri);
            array_push(self::$methods, strtoupper($method));
            array_push(self::$callbacks, $callback);
            array_push(self::$limits, $rule);
        }
    }

    private function _validMethod($method) {
        $method = strtoupper($method);
        if(! in_array($method, self::$allow_methods)) {
            exit('route method not allowed!');
        }
    }

    public static function dispatch() {
        if(self::$_route->cache_route_init && !file_exists(self::$_route->route_cache_file_dir)) {
            //加入到缓存中
            ob_start();
            $route_data = array(
                'routes'        => self::$routes,
                'methods'       => self::$methods,
                'callbacks'     => self::$callbacks,
                'limits'        => self::$limits,
            );
            $a = base64_encode(json_encode($route_data));
            echo $a;
            $v = ob_get_contents();
            ob_end_clean();

            $file_path = self::$_route->route_cache_file_dir;
            $handle = fopen($file_path, 'w+');
            fwrite($handle, $v);
            fclose($handle);
        } elseif(!self::$_route->cache_route_init && file_exists(self::$_route->route_cache_file_dir)) {
            unlink(self::$_route->route_cache_file_dir);
        }


        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        self::$routes = preg_replace('/\/+/', '/', self::$routes);

        $found_uri = false;
        if(in_array($uri, self::$routes)) {
            //直接匹配到的路由
            $key_pos = array_keys(self::$routes, $uri);
            foreach($key_pos as $key) {
                if(self::$methods[$key] == strtoupper($method) || self::$methods[$key] == 'ANY') {
                    $found_uri = true;

                    if(!is_object(self::$callbacks[$key])) {
                        //将路径和控制器分割
                        $parts = explode('/', self::$callbacks[$key]);

                        //获取最后内容，控制器和方法
                        $last = end($parts);
                       
                        $segment = explode('@', $last);

                        $controller = new $segment[0]();
                        if(count($segment) >= 2) {
                            //call method
                            $controller->$segment[1]();
                            return;
                        }
                        //no target method,call index method
                        $controller->index();
                        return;
                    } else {
                        //直接调用对应的方法
                        call_user_func(self::$callbacks[$key]);
                        return;
                    }
                }
            } 
        } else {
           $pos = 0;

           foreach(self::$routes as $route) {
                if(preg_match('/\:(\w+)\\??/', $route, $route_mached)) {
                    if(array_key_exists($route_mached[1], self::$limits[$pos])) {
                        $target = self::$limits[$pos][$route_mached[1]];
                        $route = preg_replace('/\:(\w+)\\??/', '('.$target.')', $route);
                    } elseif(empty(self::$limits[$pos])) {
                        $route = preg_replace('/\:(\w+)\\??/', '(.*)', $route);
                    }
                }

                if(preg_match('#^' . $route . '$#', $uri, $mached)) {
                    if(self::$methods[$pos] == $method || $method == 'ANY') {
                        $found_uri = true;

                        //remove the first parameter
                        array_shift($mached);

                        if(!is_object(self::$callbacks[$pos])) {
                            //delimit method with path
                            $parts = explode('/', self::$callbacks[$pos]);

                            $last = end($parts);

                            $segments = explode('@', $last);

                            $controller = new $segments[0]();

                            if(count($segments) == 1) {
                                call_user_func_array(array($controller, 'index'), $mached);
                                return;
                            }

                            if(! method_exists($controller, $segments[1])) {
                                echo "Method not exists!";
                                return;
                            } 
                            call_user_func_array(array($controller, $segments[1]), $mached);
                            return;
                        } else {
                            call_user_func_array(self::$callbacks[$pos], $mached);
                            return;
                        }
                    }
                }
                $pos ++;
            }
        }
        //Run the error callback if the target route not found
        if($found_uri === false) {
            if(! self::$error_callback) {
                self::$error_callback = function(){
                    header($_SERVER['SERVER_PROTOCOL'] . '404 Not Found');
                    echo "404 Not Found";
                };
            } else {
                if(is_string(self::$error_callback)) {
                    self::get($_SERVER['REQUEST_URI'], self::$error_callback);
                    self::$error_callback = null;
                    self::dispatch();
                    return;
                }
            }
            call_user_func(self::$error_callback);
        }
    }

    private function _getControllerData($uri, $callback) {
        //组合方法申请
        $uri_arr = [
            $uri,               //index uri ; get
            $uri . '/create',   //create uri ; get
            $uri,               //store uri ; post
            $uri.'/:id/edit',   //edit uri ; get
            $uri.'/:id',        //update uri ; put
            $uri.'/:id',        //delete uri ; delete
            $uri.'/:id'         //show uri ; get
        ];
        $method_arr = [
            'GET',
            'GET',
            'POST',
            'GET',
            'PUT',
            'DELETE',
            'GET'
        ];
        $callback_arr = [
            $callback.'@index',
            $callback.'@create',
            $callback.'@store',
            $callback.'@edit',
            $callback.'@update',
            $callback.'@delete',
            $callback.'@show',
        ];

        return array(
            'uri_arr'       => $uri_arr,
            'method_arr'    => $method_arr,
            'callback_arr'  => $callback_arr
        );
    }
    
    public function error($callback) {
        self::$error_callback = $callback;
    }
}
