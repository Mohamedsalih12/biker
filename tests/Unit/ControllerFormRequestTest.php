<?php

namespace Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

class ControllerFormRequestTest extends TestCase
{
    #[DataProvider('resourceControllers')]
    public function test_store_and_update_share_one_request(string $controller): void
    {
        $store = new ReflectionMethod($controller, 'store');
        $update = new ReflectionMethod($controller, 'update');

        $this->assertSame($store->getParameters()[0]->getType()->getName(), $update->getParameters()[0]->getType()->getName());
    }

    /** @return iterable<string, array{class-string}> */
    public static function resourceControllers(): iterable
    {
        $actions = iterator_to_array(self::validatedActions());

        foreach ($actions as [$controller, $action]) {
            if ($action === 'store' && in_array([$controller, 'update'], $actions, true)) {
                yield $controller => [$controller];
            }
        }
    }

    /**
     * Validation must be resolved at the request boundary, before these actions run.
     *
     * @param  class-string  $controller
     */
    #[DataProvider('validatedActions')]
    public function test_validated_actions_receive_a_form_request(string $controller, string $action): void
    {
        $method = new ReflectionMethod($controller, $action);
        $parameters = array_filter($method->getParameters(), fn ($parameter) => $parameter->getName() === 'request');
        $type = array_values($parameters)[0]->getType();

        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertTrue(is_subclass_of($type->getName(), FormRequest::class), "$controller::$action must receive a Form Request.");
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function validatedActions(): iterable
    {
        $controllers = [
            'TwoFactor' => ['enable', 'disable'],
            'Admin\\Setting' => ['store'],
            'Admin\\User' => ['store', 'update'],
            'Auth\\ForcedPasswordChange' => ['update'],
            'Auth\\Login' => ['login'],
            'Frontend\\User' => ['updateProfile', 'updateProfileAdmin', 'changePassword'],
        ];

        foreach ($controllers as $controller => $actions) {
            foreach ($actions as $action) {
                yield "$controller::$action" => ['App\\Http\\Controllers\\'.$controller.'Controller', $action];
            }
        }
    }
}
