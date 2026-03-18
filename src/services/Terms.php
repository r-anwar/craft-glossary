<?php

namespace codemonauts\glossary\services;

use codemonauts\glossary\elements\Glossary;
use codemonauts\glossary\elements\Term;
use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use Exception;
use Twig\Environment as TwigEnvironment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use yii\base\Component;
use function Symfony\Component\String\s;

class Terms extends Component
{
    protected string $renderedTerms = '';

    protected array $usedTerms = [];

    /**
     * Returns all terms to search for.
     *
     * @param Term $term
     *
     * @return array
     */
    public function parseTerms(Term $term): array
    {
        $terms = [
            $term->term,
        ];

        if (!empty($term->synonyms)) {
            $synonyms = explode(',', $term->synonyms);
            $terms = array_merge($terms, $synonyms);
        }

        return ArrayHelper::filterEmptyStringsFromArray($terms);
    }

    /**
     * Search and replace the terms in a text based on a glossary.
     *
     * @param string $text The text so search and replace.
     * @param Glossary $glossary The glossary to use.
     *
     * @return string
     */
    public function renderTerms(string $text, Glossary $glossary): string
    {
        return $text."<div class='glossary-helper'></div>";
    }

    /**
     * Returns the rendered terms
     *
     * @return string
     */
    public function getRenderedTerms(): string
    {
        return $this->renderedTerms;
    }
}