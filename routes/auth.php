<?php
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//                                                                                                                                                                //
// IAM Preset for DynamoPHP                                                                                                                                       //
//                                                                                                                                                                //
// IAM is an alias for Identity and Access Manager, which manages user's authentication, permissions, access profiles and teams within an application.            //
// Many apps use this kind of functionality and this is a complete ready-to-work preset, that you can import into your DynamoPHP application.                     //
//                                                                                                                                                                //
// See more info about it at: https://github.com/gabriel-guelfi/IAM                                                                                               //
//                                                                                                                                                                //
// MIT License                                                                                                                                                    //
//                                                                                                                                                                //
// Copyright (c) 2021 Dynamo PHP Community                                                                                                                        //
//                                                                                                                                                                //
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to          //
// deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or         //
// sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:                            //
//                                                                                                                                                                //
// The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.                                 //
//                                                                                                                                                                //
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS     //
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY           //
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.     //
//                                                                                                                                                                //
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

namespace Iam\Routes;

use SplitPHP\Request;
use SplitPHP\WebService;

class Auth extends WebService
{
  public function init(): void
  {
    $this->setAntiXsrfValidation(false);

    // SESSION ENDPOINTS:
    $this->addEndpoint('GET', '/v1/logged-user', function () {
      $user = $this->getService('iam/session')->getLoggedUser();

      if (empty($user)) return $this->response->withStatus(401);

      return $this->response->withData($user);
    }, true);

    $this->addEndpoint('POST', '/v1/login-sso/?credentials?', function (Request $r) {
      $credentials = $r->getRoute()->params['credentials'] ?? null;

      $data = $this->getService('iam/session')->loginSSO($credentials);
      $data->xsrfToken = $this->xsrfToken();

      return $this->response
        ->withStatus(201)
        ->withData($data);
    });

    $this->addEndpoint('POST', '/v1/login-token/?authtoken?', function (Request $r) {
      $token = $r->getRoute()->params['authtoken'] ?? null;

      if ($this->getService('iam/authtoken')->authenticate($token) == false)
        return $this->response->withStatus(401);

      $data = $this->getService('iam/session')->loginByAuthToken($token);
      $data->xsrfToken = $this->xsrfToken();

      return $this->response
        ->withStatus(201)
        ->withData($data);
    });

    $this->addEndpoint('POST', '/v1/login', function (Request $r) {
      $body = $r->getBody();

      $data = $this->getService('iam/session')->login($body);
      $data->xsrfToken = $this->xsrfToken();

      return $this->response
        ->withStatus(201)
        ->withData($data);
    });

    $this->addEndpoint('DELETE', '/v1/logout', function (Request $r) {
      $token = $r->getBody('token') ?? null;
      $this->getService('iam/session')->logout($token);

      return $this->response
        ->withStatus(204);
    });

    // AUTH TOKEN ENDPOINTS:
    $this->addEndpoint('GET', '/v1/validate-token/?authtoken?', function (Request $r) {
      $tkn = $r->getRoute()->params['authtoken'];

      if ($this->getService('iam/authtoken')->authenticate($tkn) == false)
        return $this->response->withStatus(401);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('POST', '/v1/renew-token', function () {
      $user = $this->getService('iam/session')->getLoggedUser();

      if (empty($user)) return $this->response->withStatus(401);

      $tkn = $this->getService('iam/authtoken')->create($user->ds_key, 7 * 24 * 60 * 60); // Token valid for 7 days.

      return $this->response
        ->withStatus(201)
        ->withData($tkn);
    }, true);
  }
}
