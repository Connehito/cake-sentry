<?php
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
namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Utility\Inflector;

/**
 * Static content controller
 *
 * This controller will render views from Template/Pages/
 *
 * @link https://book.cakephp.org/3.0/en/controllers/pages-controller.html
 */
class PagesController extends AppController
{

    /**
     * Render specify error
     *
     * like: http://127.0.0.1:8080/error/warning/411?message=something-wrong
     *
     * @param string $errorName
     * @param int $errorCode
     */
    public function error(string $errorName, int $errorCode=400)
    {
        $gottenError = (function (string $errorName) : ?int {
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
     * @param int $errorCode
     */
    public function exception(string $exceptionName, int $errorCode=400)
    {
        $gottenException = (function (string $exceptionName) : ?string {
            $baseName = Inflector::classify($exceptionName);
            $fqcn = "\\Cake\\Http\\Exception\\{$baseName}Exception";

            return class_exists($fqcn) ? $fqcn : BadRequestException::class;
        })($exceptionName);

        $message = $this->getRequest()->getQuery('message', 'exception!');

        throw new $gottenException($message, $errorCode);
    }
}
