<?php

namespace App\Controller;

use App\Entity\Cookbook;
use App\Repository\RecipeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RecipeController extends ApiController {

    /**
     * List all recipes of a cookbook.
     *
     * @Route("/api/cookbooks/{cookbook}/recipes", name="recipes_list", methods={"GET"})
     *
     * @param Cookbook $cookbook
     * @param RecipeRepository $recipeRepository
     * @return JsonResponse
     */
    public function getRecipes(Cookbook $cookbook, RecipeRepository $recipeRepository): JsonResponse
    {
        $recipes = $recipeRepository->findBy([
            'cookbook' => $cookbook,
        ]);

        $data = array_map(function($recipe) {
            return $recipe->getApiFields();
        }, $recipes);

        return $this->response($data);
    }
}
