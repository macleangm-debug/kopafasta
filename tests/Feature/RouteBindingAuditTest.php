<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class RouteBindingAuditTest extends TestCase
{
    public function test_api_route_parameter_names_match_controller_method_parameters(): void
    {
        $mismatches = [];

        /** @var RouteInstance $route */
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action);
            if (! class_exists($controller) || ! method_exists($controller, $method)) {
                continue;
            }

            $reflection = new ReflectionMethod($controller, $method);
            $methodParamNames = [];
            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type === null || $type->isBuiltin()) {
                    continue;
                }
                $typeName = method_exists($type, 'getName') ? $type->getName() : (string) $type;
                if (! class_exists($typeName)) {
                    continue;
                }
                if (is_subclass_of($typeName, Model::class)) {
                    $methodParamNames[] = $parameter->getName();
                }
            }

            foreach ($route->parameterNames() as $uriParam) {
                if (empty($methodParamNames)) {
                    continue;
                }
                if (! in_array($uriParam, $methodParamNames, true)) {
                    $mismatches[] = sprintf(
                        '%s %s -> %s@%s: URI param {%s} not in method params [%s]',
                        implode('|', $route->methods()),
                        $uri,
                        class_basename($controller),
                        $method,
                        $uriParam,
                        implode(', ', $methodParamNames),
                    );
                }
            }

            $uriParams = $route->parameterNames();
            foreach ($methodParamNames as $methodParam) {
                if (! in_array($methodParam, $uriParams, true)) {
                    $mismatches[] = sprintf(
                        '%s %s -> %s@%s: method param $%s has no matching URI param (URI params: [%s])',
                        implode('|', $route->methods()),
                        $uri,
                        class_basename($controller),
                        $method,
                        $methodParam,
                        implode(', ', $uriParams),
                    );
                }
            }
        }

        $this->assertEmpty(
            $mismatches,
            "Route parameter / controller binding mismatches found:\n".implode("\n", $mismatches),
        );
    }
}
