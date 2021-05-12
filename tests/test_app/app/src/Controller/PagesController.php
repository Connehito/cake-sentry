<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace TestApp\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Utility\Inflector;
use Cake\View\Exception\MissingTemplateException;

/**
 * Static content controller
 *
 * This controller will render views from templates/Pages/
 *
 * @link https://book.cakephp.org/4/en/controllers/pages-controller.html
 */
class PagesController extends AppController
{
    /**
     * Render specify error
     *
     * like: http://127.0.0.1:8080/error/warning/411?message=something-wrong
     *
     * @param string $errorName
     * @param string $errorCode
     */
    public function error(string $errorName, $errorCode = '400')
    {
        if (is_numeric($errorCode)) {
            $errorCode = (int)$errorCode;
        }
        $gottenError = (function (string $errorName): ?int {
            $key = strtoupper($errorName);
            $constName = "E_USER_{$key}";

            return constant($constName) ?: E_USER_NOTICE;
        })($errorName);
        $message = $this->getRequest()->getQuery('message', 'error!');
        trigger_error($message, $gottenError);

        $this->set(compact('gottenError', 'errorName', 'errorCode', 'message'));

        $this->setResponse(
            $this->getResponse()->withStatus($errorCode)
        );
        $this->render('dump_vars');
    }

    /**
     * Throw specify exception
     *
     * like: http://127.0.0.1:8080/exception/not-found/404?message=page-not-found
     *
     * @param string $exceptionName
     * @param string $errorCode
     */
    public function exception(string $exceptionName, $errorCode = '400')
    {
        if (is_numeric($errorCode)) {
            $errorCode = (int)$errorCode;
        }
        $gottenException = (function (string $exceptionName): ?string {
            $baseName = Inflector::classify($exceptionName);
            $fqcn = "\\Cake\\Http\\Exception\\{$baseName}Exception";

            return class_exists($fqcn) ? $fqcn : BadRequestException::class;
        })($exceptionName);

        $message = $this->getRequest()->getQuery('message', 'exception!');

        throw new $gottenException($message, $errorCode);
    }

    public function logging(string $errorName)
    {
        $message = $this->getRequest()->getQuery('message', 'some error');
        $this->doLog($errorName, $message);

        $this->render('dump_vars');
    }

    /**
     * Displays a view
     *
     * @param array ...$path Path segments.
     * @return Response|null
     * @throws ForbiddenException When a directory traversal attempt.
     * @throws MissingTemplateException When the view file could not
     *   be found and in debug mode.
     * @throws NotFoundException When the view file could not
     *   be found and not in debug mode.
     * @throws MissingTemplateException In debug mode.
     */
    public function display(...$path): ?Response
    {
        if (!$path) {
            return $this->redirect('/');
        }
        if (in_array('..', $path, true) || in_array('.', $path, true)) {
            throw new ForbiddenException();
        }
        $page = $subpage = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }
        $this->set(compact('page', 'subpage'));

        try {
            return $this->render(implode('/', $path));
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }
    }

    private function doLog($errorName, string $message)
    {
        $this->log($message, $errorName, ['contextual data' => 'with hogehoge']);
        Log::{$errorName}($message, ['contextual data' => 'with fugafuga']);
    }
}
