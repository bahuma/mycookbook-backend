<?php

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;

class RecipeExtractor {
    private $root;
    private $user_id;
    private $db;
    private $config;
    private $il10n;
    private $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Checks the fields of a recipe and standardises the format
     *
     * @param array $json
     *
     * @return array
     *
     * @throws Exception
     */
    public function checkRecipe(array $json): array {
        if (!$json) {
            throw new Exception('Recipe array was null');
        }

        if (empty($json['name'])) {
            throw new Exception('Field "name" is required');
        }

        // Make sure the schema.org fields are present
        $json['@context'] = 'http://schema.org';
        $json['@type'] = 'Recipe';

        // Make sure that "name" doesn't have any funky characters in it
        $json['name'] = $this->cleanUpString($json['name'], false, true);

        // Make sure that "image" is a string of the highest resolution image available
        if (isset($json['image']) && $json['image']) {
            if (is_array($json['image'])) {
                // Get the image from a subproperty "url"
                if (isset($json['image']['url'])) {
                    $json['image'] = $json['image']['url'];

                    // Try to get the image with the highest resolution by adding together all numbers in the url
                } else {
                    $images = $json['image'];
                    $image_size = 0;

                    foreach ($images as $img) {
                        if (is_array($img) && isset($img['url'])) {
                            $img = $img['url'];
                        }

                        if (empty($img)) {
                            continue;
                        }

                        $image_matches = [];

                        preg_match_all('!\d+!', $img, $image_matches);

                        $this_image_size = 0;

                        foreach ($image_matches as $image_match) {
                            $this_image_size += (int)$image_match;
                        }

                        if ($image_size === 0 || $this_image_size > $image_size) {
                            $json['image'] = $img;
                        }
                    }
                }
            } elseif (!is_string($json['image'])) {
                $json['image'] = '';
            }
        } else {
            $json['image'] = '';
        }

        // The image is a URL without a scheme, fix it
        if (strpos($json['image'], '//') === 0) {
            if (isset($json['url']) && strpos($json['url'], 'https') === 0) {
                $json['image'] = 'https:' . $json['image'];
            } else {
                $json['image'] = 'http:' . $json['image'];
            }
        }

        // Clean up the image URL string
        $json['image'] = stripslashes($json['image']);

        // Last sanity check for URL
        if (!empty($json['image']) && (substr($json['image'], 0, 2) === '//' || $json['image'][0] !== '/')) {
            $image_url = parse_url($json['image']);

            if (!isset($image_url['scheme'])) {
                $image_url['scheme'] = 'http';
            }

            $json['image'] = $image_url['scheme'] . '://' . $image_url['host'] . $image_url['path'];

            if (isset($image_url['query'])) {
                $json['image'] .= '?' . $image_url['query'];
            }
        }


        // Make sure that "recipeCategory" is a string
        if (isset($json['recipeCategory'])) {
            if (is_array($json['recipeCategory'])) {
                $json['recipeCategory'] = reset($json['recipeCategory']);
            } elseif (!is_string($json['recipeCategory'])) {
                $json['recipeCategory'] = '';
            }
        } else {
            $json['recipeCategory'] = '';
        }

        $json['recipeCategory'] = $this->cleanUpString($json['recipeCategory'], false, true);


        // Make sure that "recipeYield" is an integer which is at least 1
        if (isset($json['recipeYield']) && $json['recipeYield']) {
            $regex_matches = [];
            preg_match('/(\d*)/', $json['recipeYield'], $regex_matches);
            if (count($regex_matches) >= 1) {
                $yield = filter_var($regex_matches[0], FILTER_SANITIZE_NUMBER_INT);
            }

            if ($yield && $yield > 0) {
                $json['recipeYield'] = (int) $yield;
            } else {
                $json['recipeYield'] = 1;
            }
        } else {
            $json['recipeYield'] = 1;
        }

        // Make sure that "keywords" is an array of unique strings
        if (isset($json['keywords']) && is_string($json['keywords'])) {
            $keywords = trim($json['keywords'], " \0\t\n\x0B\r,");
            $keywords = strip_tags($keywords);
            $keywords = preg_replace('/\s+/', ' ', $keywords); // Collapse whitespace
            $keywords = preg_replace('/(, | ,|,)+/', ',', $keywords); // Clean up separators
            $keywords = explode(',', $keywords);
            $keywords = array_unique($keywords);

            foreach ($keywords as $i => $keyword) {
                $keywords[$i] = $this->cleanUpString($keywords[$i]);
            }

            $keywords = implode(',', $keywords);
            $json['keywords'] = $keywords;
        } else {
            $json['keywords'] = '';
        }

        // Make sure that "tool" is an array of strings
        if (isset($json['tool']) && is_array($json['tool'])) {
            $tools = [];

            foreach ($json['tool'] as $i => $tool) {
                $tool = $this->cleanUpString($tool);

                if (!$tool) {
                    continue;
                }

                array_push($tools, $tool);
            }
            $json['tool'] = $tools;
        } else {
            $json['tool'] = [];
        }

        $json['tool'] = array_filter($json['tool']);

        // Make sure that "recipeIngredient" is an array of strings
        if (isset($json['recipeIngredient']) && is_array($json['recipeIngredient'])) {
            $ingredients = [];

            foreach ($json['recipeIngredient'] as $i => $ingredient) {
                $ingredient = $this->cleanUpString($ingredient, false);

                if (!$ingredient) {
                    continue;
                }

                array_push($ingredients, $ingredient);
            }

            $json['recipeIngredient'] = $ingredients;
        } else {
            $json['recipeIngredient'] = [];
        }

        $json['recipeIngredient'] = array_filter(array_values($json['recipeIngredient']));

        // Make sure that "recipeInstructions" is an array of strings
        if (isset($json['recipeInstructions'])) {
            if (is_array($json['recipeInstructions'])) {
                // Workaround for https://www.colruyt.be/fr/en-cuisine/meli-melo-de-legumes-oublies-au-chevre
                if (isset($json['recipeInstructions']['itemListElement'])) {
                    $json['recipeInstructions'] = $json['recipeInstructions']['itemListElement'];
                }

                foreach ($json['recipeInstructions'] as $i => $step) {
                    if (is_string($step)) {
                        $json['recipeInstructions'][$i] = $this->cleanUpString($step, true);
                    } elseif (is_array($step) && isset($step['text'])) {
                        $json['recipeInstructions'][$i] = $this->cleanUpString($step['text'], true);
                    } else {
                        $json['recipeInstructions'][$i] = '';
                    }
                }
            } elseif (is_string($json['recipeInstructions'])) {
                $json['recipeInstructions'] = html_entity_decode($json['recipeInstructions']);

                $regex_matches = [];
                preg_match_all('/<(p|li)>(.*?)<\/(p|li)>/', $json['recipeInstructions'], $regex_matches, PREG_SET_ORDER);

                $instructions = [];

                foreach ($regex_matches as $regex_match) {
                    if (!$regex_match || !isset($regex_match[2])) {
                        continue;
                    }

                    $step = $this->cleanUpString($regex_match[2]);

                    if (!$step) {
                        continue;
                    }

                    array_push($instructions, $step);
                }

                if (sizeof($instructions) > 0) {
                    $json['recipeInstructions'] = $instructions;
                } else {
                    $json['recipeInstructions'] = explode(PHP_EOL, $json['recipeInstructions']);
                }
            } else {
                $json['recipeInstructions'] = [];
            }
        } else {
            $json['recipeInstructions'] = [];
        }

        $json['recipeInstructions'] = array_filter(array_values($json['recipeInstructions']), function ($v) {
            return !empty($v) && $v !== "\n" && $v !== "\r";
        });

        // Make sure the 'description' is a string
        if (isset($json['description']) && is_string($json['description'])) {
            $json['description'] = $this->cleanUpString($json['description'], true);
        } else {
            $json['description'] = "";
        }

        // Make sure the 'url' is a URL, or blank
        if (isset($json['url']) && $json['url']) {
            $url = filter_var($json['url'], FILTER_SANITIZE_URL);
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $url = "";
            }
            $json['url'] = $url;
        } else {
            $json['url'] = "";
        }

        // Parse duration fields
        $durations = ['prepTime', 'cookTime', 'totalTime'];
        $duration_patterns = [
            '/P.*T(\d+H)?(\d+M)?/',   // ISO 8601
            '/(\d+):(\d+)/',        // Clock
        ];

        foreach ($durations as $duration) {
            if (!isset($json[$duration]) || empty($json[$duration])) {
                continue;
            }

            $duration_hours = 0;
            $duration_minutes = 0;
            $duration_value = $json[$duration];

            if (is_array($duration_value) && sizeof($duration_value) === 2) {
                $duration_hours = $duration_value[0] ? $duration_value[0] : 0;
                $duration_minutes = $duration_value[1] ? $duration_value[1] : 0;
            } else {
                foreach ($duration_patterns as $duration_pattern) {
                    $duration_matches = [];
                    preg_match_all($duration_pattern, $duration_value, $duration_matches);

                    if (isset($duration_matches[1][0]) && !empty($duration_matches[1][0])) {
                        $duration_hours = intval($duration_matches[1][0]);
                    }

                    if (isset($duration_matches[2][0]) && !empty($duration_matches[2][0])) {
                        $duration_minutes = intval($duration_matches[2][0]);
                    }
                }
            }

            while ($duration_minutes >= 60) {
                $duration_minutes -= 60;
                $duration_hours++;
            }

            $json[$duration] = 'PT' . $duration_hours . 'H' . $duration_minutes . 'M';
        }

        // Nutrition information
        if (isset($json['nutrition']) && is_array($json['nutrition'])) {
            $json['nutrition'] = array_filter($json['nutrition']);
        } else {
            $json['nutrition'] = [];
        }

        return $json;
    }

    /**
     * @param string $html
     *
     * @return array
     */
    private function parseRecipeHtml($url, $html) {
        if (!$html) {
            return null;
        }

        // Make sure we don't have any encoded entities in the HTML string
        $html = html_entity_decode($html);

        // Start document parser
        $document = new \DOMDocument();

        $libxml_previous_state = libxml_use_internal_errors(true);

        try {
            if (!$document->loadHTML($html)) {
                throw new \Exception('Malformed HTML');
            }
            $errors = libxml_get_errors();
            $this->display_libxml_errors($url, $errors);
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($libxml_previous_state);
        }

        $xpath = new \DOMXPath($document);

        $json_ld_elements = $xpath->query("//*[@type='application/ld+json']");

        foreach ($json_ld_elements as $json_ld_element) {
            if (!$json_ld_element || !$json_ld_element->nodeValue) {
                continue;
            }

            $string = $json_ld_element->nodeValue;

            // Some recipes have newlines inside quotes, which is invalid JSON. Fix this before continuing.
            $string = preg_replace('/\s+/', ' ', $string);

            $json = json_decode($string, true);

            // Look through @graph field for recipe
            if ($json && isset($json['@graph']) && is_array($json['@graph'])) {
                foreach ($json['@graph'] as $graph_item) {
                    if (!isset($graph_item['@type']) || $graph_item['@type'] !== 'Recipe') {
                        continue;
                    }

                    $json = $graph_item;
                    break;
                }
            }

            // Check if json is an array for some reason
            if ($json && isset($json[0])) {
                foreach ($json as $element) {
                    if (!$element || !isset($element['@type']) || $element['@type'] !== 'Recipe') {
                        continue;
                    }
                    return $this->checkRecipe($element);
                }
            }

            if (!$json || !isset($json['@type']) || $json['@type'] !== 'Recipe') {
                continue;
            }

            return $this->checkRecipe($json);
        }

        // Parse HTML if JSON couldn't be found
        $json = [];

        $recipes = $xpath->query("//*[@itemtype='http://schema.org/Recipe']");

        if (!isset($recipes[0])) {
            throw new \Exception('Could not find recipe element');
        }

        $props = [
            'name',
            'image', 'images', 'thumbnail',
            'recipeYield',
            'keywords',
            'recipeIngredient', 'ingredients',
            'recipeInstructions', 'instructions', 'steps', 'guide',
        ];

        foreach ($props as $prop) {
            $prop_elements = $xpath->query("//*[@itemprop='" . $prop . "']");

            foreach ($prop_elements as $prop_element) {
                switch ($prop) {
                    case 'image':
                    case 'images':
                    case 'thumbnail':
                        $prop = 'image';

                        if (!isset($json[$prop]) || !is_array($json[$prop])) {
                            $json[$prop] = [];
                        }

                        if (!empty($prop_element->getAttribute('src'))) {
                            array_push($json[$prop], $prop_element->getAttribute('src'));
                        } elseif (
                            null !== $prop_element->getAttributeNode('content') &&
                            !empty($prop_element->getAttributeNode('content')->value)
                        ) {
                            array_push($json[$prop], $prop_element->getAttributeNode('content')->value);
                        }

                        break;

                    case 'recipeIngredient':
                    case 'ingredients':
                        $prop = 'recipeIngredient';

                        if (!isset($json[$prop]) || !is_array($json[$prop])) {
                            $json[$prop] = [];
                        }

                        if (
                            null !== $prop_element->getAttributeNode('content') &&
                            !empty($prop_element->getAttributeNode('content')->value)
                        ) {
                            array_push($json[$prop], $prop_element->getAttributeNode('content')->value);
                        } else {
                            array_push($json[$prop], $prop_element->nodeValue);
                        }

                        break;

                    case 'recipeInstructions':
                    case 'instructions':
                    case 'steps':
                    case 'guide':
                        $prop = 'recipeInstructions';

                        if (!isset($json[$prop]) || !is_array($json[$prop])) {
                            $json[$prop] = [];
                        }

                        if (
                            null !== $prop_element->getAttributeNode('content') &&
                            !empty($prop_element->getAttributeNode('content')->value)
                        ) {
                            array_push($json[$prop], $prop_element->getAttributeNode('content')->value);
                        } else {
                            array_push($json[$prop], $prop_element->nodeValue);
                        }
                        break;

                    default:
                        if (isset($json[$prop]) && $json[$prop]) {
                            break;
                        }

                        if (
                            null !== $prop_element->getAttributeNode('content') &&
                            !empty($prop_element->getAttributeNode('content')->value)
                        ) {
                            $json[$prop] = $prop_element->getAttributeNode('content')->value;
                        } else {
                            $json[$prop] = $prop_element->nodeValue;
                        }
                        break;
                }
            }
        }

        // Make one final desparate attempt at getting the instructions
        if (!isset($json['recipeInstructions']) || !$json['recipeInstructions'] || sizeof($json['recipeInstructions']) < 1) {
            $json['recipeInstructions'] = [];

            $step_elements = $recipes[0]->getElementsByTagName('p');

            foreach ($step_elements as $step_element) {
                if (!$step_element || !$step_element->nodeValue) {
                    continue;
                }

                array_push($json['recipeInstructions'], $step_element->nodeValue);
            }
        }

        return $this->checkRecipe($json);
    }

    private function display_libxml_errors($url, $errors) {
        $error_counter = [];
        $by_error_code = [];

        foreach ($errors as $error) {
            $count = array_key_exists($error->code, $error_counter) ? $error_counter[$error->code] : 0;
            $error_counter[$error->code] = $count + 1;
            $by_error_code[$error->code] = $error;
        }

        foreach ($error_counter as $code => $count) {
            $error = $by_error_code[$code];

            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $error_message = "libxml: Warning $error->code ";
                    break;
                case LIBXML_ERR_ERROR:
                    $error_message = "libxml: Error $error->code ";
                    break;
                case LIBXML_ERR_FATAL:
                    $error_message = "libxml: Fatal Error $error->code ";
                    break;
                default:
                    $error_message = "Unknown Error ";
            }

            $error_message .= "occurred " . $count . " times while parsing " . $url . ". Last time in line $error->line" .
                " and column $error->column: " . $error->message;

            $this->logger->warning($error_message);
        }
    }


    /**
     * @param string $url
     *
     * @return array
     */
    public function downloadRecipe($url) {
        $host = parse_url($url);

        if (!$host) {
            throw new Exception('Could not parse URL');
        }

        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Nextcloud Cookbook App"
            ]
        ];

        $context = stream_context_create($opts);

        $html = file_get_contents($url, false, $context);

        if (!$html) {
            throw new Exception('Could not fetch site ' . $url);
        }

        $json = $this->parseRecipeHtml($url, $html);

        if (!$json) {
            throw new Exception('No recipe data found');
        }

        $json['url'] = $url;

        return $json;
    }


    /**
     * @param string $str
     *
     * @return string
     */
    protected function cleanUpString($str, $preserve_newlines = false, $remove_slashes = false) {
        if (!$str) {
            return '';
        }

        $str = strip_tags($str);

        if (!$preserve_newlines) {
            $str = str_replace(["\r", "\n"], '', $str);
        }

        // We want to remove forward-slashes for the name of the recipe, to tie it to the directory structure, which cannot have slashes
        if ($remove_slashes) {
            $str = str_replace(["\t", "\\", "/"], '', $str);
        } else {
            $str = str_replace(["\t", "\\"], '', $str);
        }

        $str = html_entity_decode($str);

        return $str;
    }
}
