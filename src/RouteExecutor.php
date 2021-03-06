<?php

namespace Pux;

use Exception;
use LogicException;
use ReflectionClass;
use Closure;
use Pux\Controller\Controller;

class RouteExecutor
{
    /**
     * When creating the controller instance, we don't care about the environment.
     *
     * The returned object should be a PHPSGI app, so that we can always
     * execute "call" on the returned object.
     *
     * @return Closure|PHPSGI\App|Controller
     */
    public static function callback($handler)
    {
        if ($handler instanceof Closure) {
            return $handler;
        }
        if (is_object($handler[0])) {
            return $handler;
        }

        // If the first argument is a class name string,
        // then create the controller object.
        if (is_string($handler[0])) {

            // If users define the constructor arguments in options array.
            $constructArgs = [];
            $rc = new ReflectionClass($handler[0]);
            if (isset($options['constructor_args'])) {
                $con = $handler[0] = $rc->newInstanceArgs($constructArgs);
            } else {
                $con = $handler[0] = $rc->newInstance();
            }

            return $handler;
        }

        throw new LogicException('Unsupported handler type');
    }


    /**
     * Execute the matched route.
     *
     * This method currently do two things:
     *
     * 1. create the controller from the route arguments.
     * 2. executet the controller method by the config defined in route arguments.
     *
     * $route: {pcre flag}, {pattern}, {callback}, {options}
     *
     * *callback*:
     *
     * The callback argument can be an array that contains a class name and a method name to be called.
     * a closure object or a function name.
     *
     * *options*:
     *
     * 'constructor_args': arguments for constructing controller object.
     *
     * @return string the response
     */
    public static function execute(array $route, array $environment = array(), array $response = array())
    {
        list($pcre, $pattern, $callbackArg, $options) = $route;

        $callback = self::callback($callbackArg);

        $environment['pux.route'] = $route;
        if (is_array($callback) && $callback[0] instanceof Controller) {
            $environment['pux.controller'] = $callback[0];
            $environment['pux.controller_action'] = $callback[1];
        }

        if ($callback instanceof Closure) {
            $return = $callback($environment, $response);
        } else if ($callback[0] instanceof \PHPSGI\App) {
            $return = $callback[0]->call($environment, $response);
        } else if (is_callable($callback)) {
            $return = call_user_func($callback, $environment, $response);
        } else {
            throw new \LogicException("Invalid callback type.");
        }

        // Response fix
        if (is_string($return)) {
            if (!isset($response[0])) {
                $response[0] = 200;
            }
            if (!isset($response[1])) {
                $response[1] = [];
            }
            if (!isset($response[2])) {
                $response[2] = $return;
            }
            return $response;
        }

        return $return;
    }
}
