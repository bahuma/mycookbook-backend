<?php

namespace App\Controller;

use App\Entity\Cookbook;
use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Service\RecipeExtractor;
use Exception;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class RecipeController extends ApiController {

    /**
     * @var UrlHelper
     */
    protected $urlHelper;
    /**
     * @var CacheManager
     */
    protected $imagineCacheManager;

    public function __construct(UrlHelper $urlHelper, CacheManager $imagineCacheManager)
    {
        $this->urlHelper = $urlHelper;
        $this->imagineCacheManager = $imagineCacheManager;
    }

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

        $data = array_map(function ($recipe) {
            return $this->massageRecipe($recipe);
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


        // Store in DB
        $recipe = new Recipe();
        $recipe->setCookbook($cookbook);
        $recipe->setSchemaorg($recipeJson);
        $recipe->setTitle($recipeJson['name']);

        // Download image
        if (isset($recipeJson['image']) && $recipeJson['image']) {
            $url = $recipeJson['image'];

            $fileSystem = new Filesystem();

            $uuid = Uuid::v4();

            $imageData = file_get_contents($url);

            $tempFile = sys_get_temp_dir() . '/recipe_' . $uuid;

            file_put_contents(sys_get_temp_dir() . '/recipe_' . $uuid, $imageData);


            $mimeTypes = new MimeTypes();
            $mimeType = $mimeTypes->guessMimeType($tempFile);

            $extension = false;

            if ($mimeType == 'image/jpeg') {
                $extension = '.jpg';
            }

            if ($mimeType == 'image/png') {
                $extension = '.png';
            }


            $destination = $this->getParameter('kernel.project_dir') . '/public/media/recipes';
            $fileSystem->mkdir($destination);

            if ($extension) {
                file_put_contents($destination . '/' . $uuid . $extension, $imageData);
                $recipe->setImage($uuid . $extension);
            }

            $fileSystem->remove([$tempFile]);
        }


        $em = $this->getDoctrine()->getManager();
        $em->persist($recipe);
        $em->flush();

        $data = $this->massageRecipe($recipe);

        return $this->respondCreated($data);
    }

    protected function massageRecipe($recipe) {
        $fields = $recipe->getApiFields();

        if (!$fields['image'] || empty($fields['image'])) {
            unset($fields['image']);
            return $fields;
        }

        // Add thumbnail
        $originalPath = '/media/recipes/' . $fields['image'];

        $thumbnail = $this->imagineCacheManager->getBrowserPath($originalPath, 'recipe_thumbnail');

        $fields['image'] = [
            'original' => $this->urlHelper->getAbsoluteUrl($originalPath),
            'thumbnail' => $thumbnail,
        ];

        return $fields;
    }
}
