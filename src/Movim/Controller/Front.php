<?php
namespace Movim\Controller;

use Monolog\Logger;
use Monolog\Handler\SyslogHandler;
use Movim\Route;
use Movim\Cookie;
use Movim\RPC;

class Front extends Base
{
    public function handle()
    {
        $r = new Route;
        $page = $r->find();

        if ($page === null) {
            $this->redirect($r->getRedirect());
        } else {
            $this->runRequest($page);
        }
    }

    public function loadController($request)
    {
        $className = ucfirst($request).'Controller';
        if (file_exists(APP_PATH . 'controllers/'.$className.'.php')) {
            $controllerPath = APP_PATH . 'controllers/'.$className.'.php';
        } else {
            \Utils::error("Requested controller $className doesn't exist");
            exit;
        }

        require_once $controllerPath;
        return new $className();
    }

    /**
     * Here we load, instanciate and execute the correct controller
     */
    public function runRequest($request)
    {
        // Simple ajax request to a Widget
        if ($request === 'ajax') {
            $payload = json_decode(file_get_contents('php://input'));

            if ($payload) {
                $rpc = new RPC;
                $rpc->handleJSON($payload->b);
                $rpc->writeJSON();
            }
            return;
        }

        // Ajax request that is going to the daemon
        if ($request === 'ajaxd') {
            requestAPI('ajax', 2, [
                'sid' => SESSION_ID,
                'json' => rawurlencode(file_get_contents('php://input'))
            ]);
            return;
        }

        $c = $this->loadController($request);

        Cookie::refresh();

        if (is_callable([$c, 'load'])) {
            $c->name = $request;
            $c->load();
            $c->checkSession();
            $c->dispatch();

            // If the controller ask to display a different page
            if ($request != $c->name) {
                $new_name = $c->name;
                $c = $this->loadController($new_name);
                $c->name = $new_name;
                $c->load();
                $c->dispatch();
            }

            // We display the page !
            $c->display();
        } else {
            \Utils::info('Could not call the load method on the current controller');
        }
    }
}
