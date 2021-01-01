<?php

namespace App\Controller;

use App\Repository\CookbookRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CookbookController extends ApiController {

    /**
     * Get all own cookbooks.
     *
     * @Route("/api/cookbooks", name="cookbooks_list", methods={"GET"})
     *
     * @param CookbookRepository $cookbookRepository
     * @return JsonResponse
     */
    public function getCookbooks(CookbookRepository $cookbookRepository): JsonResponse
    {
        $cookbooks = $cookbookRepository->findBy([
            'owner' => $this->getUser()
        ]);

        $data = array_map(function($cookbook) {
            return $cookbook->getApiFields();
        }, $cookbooks);

        return $this->response($data);
    }
}
