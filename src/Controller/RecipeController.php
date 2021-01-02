<?php

namespace App\Controller;

use App\Entity\Cookbook;
use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Service\RecipeExtractor;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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


    /**
     * Extract json+ld from an url and save it as a new recipe in a cookbook.
     *
     * @Route("/api/cookbooks/{cookbook}/recipes", name="recipes_add_url", methods={"POST"})
     *
     * @param Cookbook $cookbook
     * @param Request $request
     * @param LoggerInterface $logger
     * @return JsonResponse
     */
    public function addRecipeByUrl(Cookbook $cookbook, Request $request, LoggerInterface $logger): JsonResponse
    {
        $request = $this->transformJsonBody($request);

        $url = $request->get('url');

        if (empty($url)) {
            return $this->respondValidationError('The parameter "url" is missing');
        }


        // Download recipe
        $extractor = new RecipeExtractor($logger);

        try {
            $recipeJson = $extractor->downloadRecipe($request->get('url'));
        } catch (Exception $e) {
            return $this->setStatusCode(422)->respondWithErrors('The recipe could not be extracted');
        }

        try {
            $recipeJson = $extractor->checkRecipe($recipeJson);
        } catch (Exception $e) {
            return $this->setStatusCode(422)->respondWithErrors('The recipe could not be parsed');
        }


        // TODO: Download image


        // Store in DB
        $recipe = new Recipe();
        $recipe->setCookbook($cookbook);
        $recipe->setSchemaorg($recipeJson);
        $recipe->setTitle($recipeJson['name']);

        $em = $this->getDoctrine()->getManager();
        $em->persist($recipe);
        $em->flush();

        $data = $recipe->getApiFields();
        $data['schemaorg'] = $recipe->getSchemaorg();

        return $this->respondCreated($data);
    }
}
