<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends ApiController {

    /**
     * Creates a new user.
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @return JsonResponse
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder, ValidatorInterface $validator): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();
        $request = $this->transformJsonBody($request);

        $email = $request->get('email');
        $password = $request->get('password');

        if (empty($email) || empty($password)) {
            return $this->respondValidationError('E-Mail or Password is missing');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($encoder->encodePassword($user, $password));

        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            return $this->respondValidationError($errors->get(0)->getMessage());
        }

        try {
            $em->persist($user);
            $em->flush();

            return $this->respondWithSuccess(sprintf('User %s successfully created', $user->getUsername()));
        } catch (UniqueConstraintViolationException $e) {
            return $this->setStatusCode(400)
                ->respondWithErrors(sprintf('A user with the e-mail address %s already exits', $user->getUsername()));
        }
    }

    /**
     * @param UserInterface $user
     * @param JWTTokenManagerInterface $JWTManager
     * @return JsonResponse
     */
    public function getTokenUser(UserInterface $user, JWTTokenManagerInterface $JWTManager): JsonResponse
    {
        return new JsonResponse([
            'token' => $JWTManager->create($user),
        ]);
    }
}
