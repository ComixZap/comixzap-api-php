<?php

namespace ComixZap\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;
use ComixZap\Controller\FileListController;
use ComixZap\Controller\ComicController;

class MainApplication extends Application
{
    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var ParameterBag
     */
    private $config;

    /**
     * @param $path string
     */
    public function setRootPath($path)
    {
        $this->rootPath = $path;
    }

    public function initialize()
    {
        $this->configure();
        $this->registerProviders();
        $this->registerControllers();
        $this->defineRoutes();
    }

    protected function configure()
    {
        $this->config = new ParameterBag(parse_ini_file($this->rootPath . '/config/config.ini'));

        $this['debug'] = $this->config->get('debug', false);
    }

    protected function registerProviders()
    {
        $this->register(new ServiceControllerServiceProvider());
    }

    protected function registerControllers()
    {
        $this['Coverslide.FileListController'] = $this->share(
            \Closure::bind(
                function () {
                    return (new FileListController())
                        ->setRootPath($this->config->get('cbz.root', $this->rootPath));
                },
                $this
            )
        );

        $this['Coverslide.ComicController'] = $this->share(
            \Closure::bind(
                function () {
                    return (new ComicController())
                        ->setRootPath($this->config->get('cbz.root', $this->rootPath))
                        ->setDecompress($this->config->get('decompress', false));
                },
                $this
            )
        );
    }

    protected function defineRoutes()
    {
        $this->before(array($this, "beforeHandler"));
        $this->get('/file-list', "Coverslide.FileListController:filesAction");
        $this->get('/comic/list', "Coverslide.ComicController:listAction");
        $this->get('/comic/image', "Coverslide.ComicController:imageAction");
        $this->after(array($this, "afterHandler"));
    }

    protected function beforeHandler (Request $request) {
        $origin = $request->headers->get('origin');
        if (!$origin) {
            return;
        }
        if (!$this->originMatches($origin)) {
            $response = new Response();
            $response->setStatusCode(403);
            return $response;
        }
    }

    private function originMatches ($origin) {
        $allowedOrigins = $this->config->get('origin.allow');
        if (!isset($allowedOrigins)) {
            return true;
        }
        $originParts = parse_url($origin);
        foreach ($allowedOrigins as $allowed) {
            if ($allowed === '*') {
                return true;
            }
            $allowedParts = parse_url($allowed);
            if (isset($allowedParts['scheme']) && $originParts['scheme'] !== $allowedParts['scheme']) {
                continue;
            } else if (!isset($allowedParts['scheme'])) {
                $allowedParts['host'] = $allowedParts['path'];
            }
            if ($allowedParts['host'] === $originParts['host']) {
                return true;
            }
        }
        return false;
    }

    protected function afterHandler (Request $request, Response $response) {
        $origin = $request->headers->get('origin');
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
        return $response;
    }
}
