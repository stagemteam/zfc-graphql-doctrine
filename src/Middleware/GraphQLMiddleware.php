<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-helpers for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-helpers/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Stagem\ZfcGraphQL\Middleware;

// @todo wait until they will start to use Pst in codebase @see https://github.com/zendframework/zend-mvc/blob/master/src/MiddlewareListener.php#L11
class_alias('Interop\Http\Server\MiddlewareInterface', 'Interop\Http\ServerMiddleware\MiddlewareInterface');

// @todo wait until they will start to use Pst in codebase @see https://github.com/zendframework/zend-mvc/blob/master/src/MiddlewareListener.php#L11
//use Psr\Http\Server\MiddlewareInterface;
//use Psr\Http\Server\RequestHandlerInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Popov\ZfcUser\Action\Admin\LoginTrait;
use Popov\ZfcUser\Form\LoginForm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Zend\Session\SessionManager;

use Popov\ZfcUser\Service\UserService;
use Popov\ZfcForm\FormElementManager;
use Popov\ZfcUser\Auth\Auth;

/**
 * Pipeline middleware for injecting a RequestHelper with a RouteResult.
 */
class GraphQLMiddleware implements MiddlewareInterface
{
    use LoginTrait;

    protected $userService;

    protected $loginForm;

    protected $auth;

    public function __construct(
        UserService $userService,
        FormElementManager $fm,
        Auth $auth
        //\Zend\Session\SessionManager $sessionManager
    ) {
        $this->userService = $userService;
        $this->loginForm = $fm->get(LoginForm::class);
        $this->auth = $auth;
        //$this->auth = $sessionManager;
    }

    /**
     * Inject the RequestHelper instance with a Request, if present as a request attribute.
     * Injects the helper, and then dispatches the next middleware.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // GraphQL authorization.
        // Here we execute login and on Schema level only return user token (session ID).
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = file_get_contents('php://input');
            $bodyParams = json_decode($rawBody ?: '', true);
            if (isset($bodyParams['operationName']) && $bodyParams['operationName'] === 'LoginMutation') {
                $authService = $this->auth->getAuthService();
                if (!$authService->hasIdentity()) {
                    $this->login($request = $request->withParsedBody($bodyParams['variables']));
                }
            }
        }

        return $handler->handle($request);
    }
}
