<?php
/**
 * Created by PhpStorm.
 * User: vietpn
 * Date: 9/6/14
 * Time: 1:24 PM
 */
App::uses('Component', 'Controller');
class HelloWorldComponent extends Component{

    public function __construct(){

    }

    public function showMessage(){
        echo 'hello world heree';
    }
}