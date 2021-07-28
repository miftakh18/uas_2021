<?php

namespace App\Controllers;

use App\Models\Users;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;
use Exception;
use Firebase\JWT\JWT;
use ReflectionException;

class Auth extends BaseController
{
    /**
     * Register a new user
     * @return Response
     * @throws ReflectionException
     */
    public function register()
    {
        $rules = [
            'name' => 'required',
            'email' => 'required|min_length[6]|max_length[50]|valid_email|is_unique[user.email]',
            'password' => 'required|min_length[8]|max_length[255]'
        ];

        $input = $this->getRequestInput($this->request);
        if (!$this->validateRequest($input, $rules)) {
            return $this
                ->getResponse(
                    $this->validator->getErrors(),
                    ResponseInterface::HTTP_BAD_REQUEST
                );
        }

        $userModel = new Users();
        $userModel->save($input);


        return $this
            ->getJWTForUser(
                $input['email'],
                ResponseInterface::HTTP_CREATED
            );
    }

    /**
     * Authenticate Existing User
     * @return Response
     */
    public function login()
    {
        // session();
        $rules = [
            'email' => 'required|min_length[6]|max_length[50]|valid_email',
            'password' => 'required|min_length[8]|max_length[255]|validateUser[email, password]'
        ];

        $errors = [
            'msg' => [
                'validateUser' => 'Invalid login credentials provided'
            ]
        ];

        $input = $this->getRequestInput($this->request);


        if (!$this->validateRequest($input, $rules, $errors)) {
            return $this
                ->getResponse(
                    $errors = $this->validator->getErrors('password'),
                    ResponseInterface::HTTP_BAD_REQUEST
                );
        }


        // if (!$this->validateRequest($input, $rules, $errors)) {
        //     return redirect()->to(base_url('auth/login'))->with($this
        //         ->getResponse(
        //             $this->validator->getErrors(),
        //             ResponseInterface::HTTP_BAD_REQUEST
        //         ));
        // }

        // if (!$this->validate($errors)) {
        //     return redirect()->to(base_url('auth/login'))->with('error', $this->validator->listErrors());
        // }
        return $this->getJWTForUser($input['email']);
    }

    public function logout()
    {
        session_destroy();
    }

    public function me()
    {
        $key = Services::getSecretKey();

        $authHeader = $this->request->getHeader("Authorization");
        $authHeader = $authHeader->getValue();
        $token = str_replace("Bearer ", "", $authHeader);

        try {
            $decoded = JWT::decode($token, $key, ['HS256']);
            $user = new Users();

            if ($decoded) {

                $response = [
                    'status' => 200,
                    'error' => false,
                    'messages' => 'User details',
                    'data' => [
                        'user' => $user->findUserByEmailAddress($decoded->email)
                    ]
                ];
                return $this->response->setJSON($response);
            }
        } catch (Exception $ex) {

            $response = [
                'status' => 401,
                'error' => true,
                'messages' => 'Access denied',
                'data' => []
            ];
            return $this->response->setJSON($response);
        }
    }

    private function getJWTForUser(
        string $emailAddress,
        int $responseCode = ResponseInterface::HTTP_OK
    ) {
        $session = \Config\Services::session();

        try {
            $model = new Users();
            $user = $model->findUserByEmailAddress($emailAddress);
            unset($user['password']);

            helper('jwt');

            $token = getSignedJWTForUser($emailAddress);

            if ($user) {
                $newdata = [
                    'token'  => $token,
                    'user'     => $user
                ];
                $session->set($newdata);
            }

            return $this
                ->getResponse(
                    [
                        'message' => 'User authenticated successfully',
                        'user' => $user,
                        'access_token' => $token
                    ]
                );
        } catch (Exception $exception) {
            return $this
                ->getResponse(
                    [
                        'error' => $exception->getMessage(),
                    ],
                    $responseCode
                );
        }
    }
}
