<?php

namespace Docker;

use Docker\Context\ContextInterface;
use Docker\Exception\UnexpectedStatusCodeException;
use Docker\Http\DockerClient;
use Docker\Manager\ContainerManager;
use Docker\Manager\ImageManager;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Stream\Stream;

/**
 * Docker
 */
class Docker
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var ContainerManager
     */
    private $containerManager;

    /**
     * @var ImageManager
     */
    private $imageManager;

    /**
     * @param HttpClient $httpClient Http client to use with Docker
     */
    public function __construct(HttpClient $httpClient = null)
    {
        $this->httpClient = $httpClient ?: new DockerClient();
    }

    /**
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @return ContainerManager
     */
    public function getContainerManager()
    {
        if (null === $this->containerManager) {
            $this->containerManager = new ContainerManager($this->httpClient);
        }

        return $this->containerManager;
    }

    /**
     * @return ImageManager
     */
    public function getImageManager()
    {
        if (null === $this->imageManager) {
            $this->imageManager = new ImageManager($this->httpClient);
        }

        return $this->imageManager;
    }

    /**
     * Show the docker components version information
     *
     * @return array json object with version values
     */
    public function getVersion()
    {
        try {
            $response = $this->httpClient->get(['/version', []]);
        } catch (RequestException $e) {
            throw $e;
        }

        return $response->json();
    }

    /**
     * Docker info: Display system-wide information
     * api_v1.16
     * @return array json object with version values
     */
    public function getInfo()
    {
        try {
            $response = $this->httpClient->get(['/info', []]);
        } catch (RequestException $e) {
            throw $e;
        }

        return $response->json();
    }

    /**
     * Build an image with docker
     *
     * @param ContextInterface $context  Context to build
     * @param string           $name     Name of the wanted image
     * @param callable         $callback A callback to be called for having log of build
     * @param boolean          $quiet    Quiet build (does not output commands during build)
     * @param boolean          $cache    Use docker cache
     * @param boolean          $rm       Remove intermediate containers during build
     * @param boolean          $wait     Whether to wait for build to finish
     *
     * @return \GuzzleHttp\Message\ResponseInterface
     */
    public function build(ContextInterface $context, $name, callable $callback = null, $quiet = false, $cache = true, $rm = false, $wait = true)
    {
        if (null === $callback) {
            $callback = function () {};
        }

        $content  = is_resource($context->read()) ? new Stream($context->read()) : $context->read();

        return $this->httpClient->post(['/build{?data*}', ['data' => [
            'q'       => (integer) $quiet,
            't'       => $name,
            'nocache' => (integer) !$cache,
            'rm'      => (integer) $rm,
        ]]], [
            'headers'  => ['Content-Type' => 'application/tar'],
            'body'     => $content,
            'stream'   => true,
            'callback' => $callback,
            'wait'     => $wait
        ]);
    }

    /**
     * Commit a container into an image
     *
     * @param Container $container
     * @param array     $config
     *
     * @throws UnexpectedStatusCodeException
     *
     * @return Image
     *
     * @see http://docs.docker.com/reference/api/docker_remote_api_v1.7/#create-a-new-image-from-a-containers-changes
     */
    public function commit(Container $container, $config = [])
    {
        if (isset($config['run'])) {
            $config['run'] = json_encode($config['run']);
        }

        $config['container'] = $container->getId();

        $response = $this->httpClient->post(['/commit{?config*}', ['config' => $config]]);

        if ($response->getStatusCode() !== "201") {
            throw new UnexpectedStatusCodeException($response->getStatusCode(), (string) $response->getBody());
        }

        $image = new Image();
        $image->setId($response->json()['Id']);

        if (array_key_exists('repo', $config)) {
            $image->setRepository($config['repo']);
        }

        if (array_key_exists('tag', $config)) {
            $image->setTag($config['tag']);
        }

        return $image;
    }
}
