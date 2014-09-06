<?php
/**
 * Created by PhpStorm.
 * User: vietpn
 * Date: 9/6/14
 * Time: 11:12 AM
 */
App::uses('HelloWorldComponent', 'Controller/Component');

class HelloWorldShell extends AppShell {
    private $_helloWorld = NULL;

    public function startup(){
        $this->_helloWorld = new HelloWorldComponent();
    }

    /**
     * main business process
     */
    public function main() {
        $this->_helloWorld->showMessage();
    }
}
