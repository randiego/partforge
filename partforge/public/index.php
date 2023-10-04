<?php
//ini_set('display_errors', 0);  //TODO: this needs to be removed when going live
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/../error_log.txt');
error_reporting(E_ALL);

define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application/'));
set_include_path(
    APPLICATION_PATH . PATH_SEPARATOR . APPLICATION_PATH . '/../library' . PATH_SEPARATOR . get_include_path()
);

// need to do this to make sure we are not stepping on any other apps on the same server.
ini_set('session.save_path', realpath(dirname(__FILE__) . '/../sessions/'));
ini_set('session.gc_maxlifetime', 36000);

require_once "Zend/Loader/Autoloader.php";
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);


require_once("../application/functions.app.php");
Zend_Session::start();
require_once("../application/init.php");

if (getGlobal('databaseversion') != Zend_Registry::get('config')->databaseversion) {
    LoginStatus::getInstance()->setValidUser(false);
    unset($_SESSION['account']);
}

$frontController = Zend_Controller_Front::getInstance();
$frontController->setControllerDirectory(array( 'default' => APPLICATION_PATH . '/controllers',
                                                'items' => APPLICATION_PATH . '/items/controllers',
                                                'types' => APPLICATION_PATH . '/types/controllers'));
$frontController->throwExceptions(true);

$acl = new CustomAcl('nobody');
Zend_Registry::set('customAcl', $acl);
$frontController->registerPlugin(new CustomControllerAclManager($acl));
$frontController->registerPlugin(new Zend_Controller_Plugin_PutHandler());

if (!isset($_SESSION['account'])) {
    $_SESSION['account'] = new DBTableRowUser();
    $_SESSION['account']->user_type = $acl->defaultRole();
}

/*
 * An entry in this array means that we will actually use the "db" as the controller name
 * Instead of the table name as the controller.  The table names will be passed as a "table"
 * parameter.  We do this when we are lazy and don't feel like adding our own controller that inherits
 * from DBControllerActionAbstract.
 */
$table_w_generic_controllers = array();

$genericTableEditRoute = new Zend_Controller_Router_Route_Regex(
    '('.implode('|', $table_w_generic_controllers).')/([a-z]+)',
    array(
        'controller' => 'db',
    ),
    array(
        1 => 'table',
        2 => 'action'
    )
);

$restRoute = new Zend_Rest_Route($frontController, array('format' => 'json'), array('items','types'));
$frontController->getRouter()->addRoute('rest', $restRoute);
$frontController->getRouter()->addRoute('db', $genericTableEditRoute);

$frontController->getRouter()->addRoute('to', new Zend_Controller_Router_Route('/struct/to/:to', array('controller' => 'struct', 'action' => 'to') ) );
$frontController->getRouter()->addRoute('tonew', new Zend_Controller_Router_Route('/struct/to/:to/new', array('controller' => 'struct', 'action' => 'to', 'link_action' => 'new') ) );

$frontController->getRouter()->addRoute('tv', new Zend_Controller_Router_Route('/struct/tv/:tv', array('controller' => 'struct', 'action' => 'tv') ) );
$frontController->getRouter()->addRoute('io', new Zend_Controller_Router_Route('/struct/io/:io', array('controller' => 'struct', 'action' => 'io') ) );
$frontController->getRouter()->addRoute('iv', new Zend_Controller_Router_Route('/struct/iv/:iv', array('controller' => 'struct', 'action' => 'iv') ) );

$frontController->getRouter()->addRoute('userid', new Zend_Controller_Router_Route('/user/id/:user_id', array('controller' => 'user', 'action' => 'itemview') ) );
$frontController->getRouter()->addRoute('dashboard', new Zend_Controller_Router_Route('/dash/panel/:dashboard_id', array('controller' => 'dash', 'action' => 'panel') ) );
$frontController->getRouter()->addRoute('dotask', new Zend_Controller_Router_Route('/dotask/:assigned_to_task_id/:link_password', array('controller' => 'user', 'action' => 'workflowtaskresponse') ) );

$frontController->getRouter()->addRoute('qrupload', new Zend_Controller_Router_Route('/utils/qrupload/:qrkey', array('controller' => 'utils', 'action' => 'qrupload') ) );

// setup layout processing
Zend_Layout::startMvc(APPLICATION_PATH . '/layouts/scripts');

$frontController->dispatch();
