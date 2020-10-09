<?php 

namespace App\Traits;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

trait JsonExceptionInterceptor
{
    public function reformatJsonExceptionMessage($exception)
    {
        $e = $this->prepareException($exception); 
        
        $format = [];

        $format['status'] = $this->getCorrectStatusCode($e);
        $format['errors'] = [];
        $format['data'] = [];

        if($e instanceof ValidationException) { 
            $format['message'] = $e->getMessage();
            $format['errors'] = $exception->errors();
        }
        else if($e instanceof AuthenticationException) {
            $format['message'] =  method_exists($e, 'getMessage') 
                ? $e->getMessage() : 'Unauthenticated';
        }
        else if($e instanceof HttpResponseException) {
            $format['message'] = method_exists($e, 'getMessage') ? $e->getMessage() : 'Request terminated. HTTP Response Exception';
        }
        else {
            $format['message'] = $this->convertExceptionToArray($e)['message'];
            $format['errors'] = method_exists($e, 'errors') ? $e->errors() : []; 
            // The following keys can be removed. Only necessary for debug purposes in the case of a rasie exception
            // But not something you would want your API users or consumers to see or use
            // Preferably, you can place them under a conditional statement to check if debug option in .env is true and 
            // only display if debug mode is on 
            
            if(config('app.debug')) {
                $format['exception'] = get_class($e);
                $format['file'] = $e->getFile();
                $format['line'] = $e->getLine();
                $format['trace'] = collect($e->getTrace())->map(function ($trace) {
                    return Arr::except($trace, ['args']);
                })->all(); 
            }
        }

        return new JsonResponse(
            $format,
            $this->getCorrectStatusCode($e),
            $this->isHttpException($e) ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );   
    }

    protected function getCorrectStatusCode($e): int 
    {
        
        if($e instanceof ValidationException) { 
            return $e->status;
        }
        else if($e instanceof AuthenticationException) {
           return 401;
        }
        else if($e instanceof HttpResponseException) {
            if(method_exists($e, 'getCode')) return $e->getCode();
        }
        else if($this->isHttpException($e)) return $e->getStatusCode();

        return 500;
    }
}