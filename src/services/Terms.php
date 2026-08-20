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
    /**
     * Zeichen, die als Wortbestandteil gelten. Der Bindestrich ist bewusst enthalten:
     * "Au-Pair" wird damit nicht innerhalb von "Au-Pair-Kraft" ausgezeichnet.
     * `\b` leistet das nicht, weil "-" selbst ein Nicht-Wort-Zeichen ist und
     * daher hinter "Pair" eine Wortgrenze bildet.
     */
    private const WORD_CHARS = '\p{L}\p{N}_-';

    /**
     * Elemente, in deren Inhalt nicht ausgezeichnet wird. <a> und <button> sind
     * interaktiv — ein Glossar-Button darin waere verschachtelter interaktiver
     * Inhalt und damit ungueltiges HTML. <script>/<style> sind kein Fliesstext.
     */
    private const SKIPPED_ELEMENTS = 'a|button|script|style';

    protected string $renderedTerms = '';

    protected array $usedTerms = [];

    /**
     * Returns all terms to search for.
     *
     * @param Term $term
     *
     * @return string[]
     */
    public function parseTerms(Term $term): array
    {
        $terms = [
            trim((string)$term->term),
        ];

        if (!empty($term->synonyms)) {
            $synonyms = array_map('trim', explode(',', $term->synonyms));
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
        $originalText = $text;

        try {
            $termTemplate = !empty($glossary->termTemplate) ? $glossary->termTemplate : '<span>{{ text }}</span>';
            $tooltipTwig = $this->createTooltipRenderer($glossary);

            $replacements = [];
            $templates = [];
            $indexes = [];

            foreach ($this->collectCandidates($glossary) as [$word, $term]) {
                $templates[$term->id] ??= Html::modifyTagAttributes($termTemplate, [
                    'class' => 'glossary',
                    'data-glossary-term' => 'term-' . $term->id,
                ]);
                $indexes[$term->id] ??= 0;

                $template = $templates[$term->id];
                $pattern = $this->buildPattern($word, (bool)$term->matchSubstring, (bool)$term->caseSensitive);

                $text = $this->replaceInTextNodes($text, $pattern, function (array $matches) use ($term, $template, $tooltipTwig, &$replacements, &$indexes): string {
                    $index = $indexes[$term->id];
                    $token = $term->uid . '-' . $index;

                    /**
                     * @deprecated Remove field values with version 2.0 and only use term to access all fields.
                     */
                    $variables = $term->getFieldValues();
                    $variables['term'] = $term;
                    $variables['text'] = $matches[0];
                    $variables['token'] = $term->id . $index;

                    $replacements[$token] = $this->renderTermTag($template, $variables);

                    $tooltip = $this->renderTooltip($tooltipTwig, $variables);
                    if ($tooltip !== null) {
                        $this->usedTerms[$token] = $tooltip;
                    }

                    $indexes[$term->id]++;

                    return '{{%' . $token . '%}}';
                });
            }

            foreach ($replacements as $token => $replacement) {
                $text = s($text)->replace('{{%' . $token . '%}}', $replacement);
            }

            $renderedTerms = '';
            foreach ($this->usedTerms as $usedTerm) {
                $renderedTerms .= Html::tag('div', $usedTerm, [
                    'class' => 'glossary-popover-container',
                ]);
            }

            $this->renderedTerms = Html::tag('div', $renderedTerms, [
                'id' => 'glossary-terms',
            ]);
        } catch (Exception $e) {
            Craft::error('Error when rendering glossary terms: ' . $e->getMessage(), 'glossary');
            $text = $originalText;
        }

        return $text;
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

    /**
     * Returns all [word, term] pairs of a glossary, longest word first.
     *
     * Ohne die Sortierung entscheidet die Anlagereihenfolge der Terms, welcher von
     * zwei ueberlappenden Begriffen zuerst greift — "Au-Pair" wuerde dann
     * "Au-Pair-Kraft" zerschneiden, sobald es aelter ist.
     *
     * @param Glossary $glossary
     *
     * @return array<array{0: string, 1: Term}>
     */
    private function collectCandidates(Glossary $glossary): array
    {
        $candidates = [];

        foreach ($this->findTerms($glossary) as $term) {
            foreach ($this->parseTerms($term) as $word) {
                $candidates[] = [$word, $term];
            }
        }

        usort($candidates, static fn(array $a, array $b): int => mb_strlen($b[0]) <=> mb_strlen($a[0]));

        return $candidates;
    }

    /**
     * Returns the terms of a glossary. Separate Methode, damit sie in Tests
     * ueberschrieben werden kann.
     *
     * @param Glossary $glossary
     *
     * @return Term[]
     */
    protected function findTerms(Glossary $glossary): array
    {
        return Term::find()->glossary($glossary)->all();
    }

    /**
     * Builds the search pattern for a single word of a term.
     *
     * @param string $word
     * @param bool $matchSubstring
     * @param bool $caseSensitive
     *
     * @return string
     */
    private function buildPattern(string $word, bool $matchSubstring, bool $caseSensitive): string
    {
        $quoted = preg_quote($word, '/');

        if ($matchSubstring) {
            $pattern = '/' . $quoted . '/';
        } else {
            $pattern = '/(?<![' . self::WORD_CHARS . '])' . $quoted . '(?![' . self::WORD_CHARS . '])/';
        }

        if (!$caseSensitive) {
            $pattern .= 'i';
        }

        // s()->replaceMatches() haengt intern nochmals ein "u" an — doppelte Modifier
        // sind in PCRE unkritisch. Explizit gesetzt, damit das Pattern auch ohne
        // Symfony\String korrekt arbeitet.
        return $pattern . 'u';
    }

    /**
     * Applies a pattern to the text nodes of an HTML string, leaving tags untouched.
     *
     * Ohne diese Trennung greift das Pattern auch in Attributwerte: ein Begriff
     * "Grundbuch" trifft in <a href="/amtswege/grundbuch" title="Grundbuch-Auszug">
     * dreimal statt einmal und zerstoert beim Ersetzen das Markup.
     *
     * @param string $html
     * @param string $pattern
     * @param callable $callback
     *
     * @return string
     */
    private function replaceInTextNodes(string $html, string $pattern, callable $callback): string
    {
        // Bei PREG_SPLIT_DELIM_CAPTURE stehen die Tags auf den ungeraden Indizes.
        $segments = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($segments === false) {
            Craft::warning('Could not split text into nodes, skipping replacement.', 'glossary');

            return $html;
        }

        $skipDepth = 0;

        foreach ($segments as $i => $segment) {
            if ($i % 2 === 1) {
                if (preg_match('/^<\s*(\/?)\s*(?:' . self::SKIPPED_ELEMENTS . ')\b/i', $segment, $match) === 1) {
                    $skipDepth = $match[1] === '/' ? max(0, $skipDepth - 1) : $skipDepth + 1;
                }

                continue;
            }

            if ($segment === '' || $skipDepth > 0) {
                continue;
            }

            $segments[$i] = (string)s($segment)->replaceMatches($pattern, $callback);
        }

        return implode('', $segments);
    }

    /**
     * Renders the term template for a single occurrence.
     *
     * @warning Kein $view->renderString(): das instanziiert intern Twig\Node\Node,
     * was seit twig/twig 3.15 deprecated ist und in 4.0 abstrakt wird. Stattdessen
     * werden {{ ... }}-Platzhalter direkt ersetzt.
     * @see https://github.com/twigphp/Twig/releases/tag/v3.15.0
     * @see https://github.com/codemonauts/craft-glossary/issues/13
     *
     * @param string $template
     * @param array $variables
     *
     * @return string
     */
    private function renderTermTag(string $template, array $variables): string
    {
        $rendered = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', static function (array $match) use ($variables): string {
            $key = trim($match[1]);

            if ($key === 'text') {
                return htmlspecialchars((string)($variables['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            if ($key === 'term') {
                return htmlspecialchars((string)$variables['term'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            if ($key === 'token') {
                return (string)$variables['token'];
            }

            $parts = explode('.', $key);
            if ($parts[0] !== 'term') {
                return $match[0];
            }

            $value = $variables['term'];
            for ($i = 1, $count = count($parts); $i < $count; $i++) {
                $part = $parts[$i];
                if (is_array($value) && array_key_exists($part, $value)) {
                    $value = $value[$part];
                } elseif (is_object($value) && isset($value->$part)) {
                    $value = $value->$part;
                } else {
                    return $match[0];
                }
            }

            return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $template);

        return $rendered ?? $template;
    }

    /**
     * Creates the Twig environment for the tooltip template, once per render pass.
     *
     * @warning Kein $view->renderTemplate(): siehe renderTermTag(). Das Template wird
     * als String geladen und in einer Twig-Umgebung ohne Craft-NodeVisitors gerendert.
     *
     * @param Glossary $glossary
     *
     * @return TwigEnvironment|null
     */
    private function createTooltipRenderer(Glossary $glossary): ?TwigEnvironment
    {
        $path = Craft::getAlias('@templates/' . $glossary->tooltipTemplate . '.twig');

        if (!is_string($path) || !is_file($path)) {
            Craft::error("Tooltip template not found: {$glossary->tooltipTemplate}", 'glossary');

            return null;
        }

        $templateString = file_get_contents($path);

        if ($templateString === false) {
            Craft::error("Could not read tooltip template: {$path}", 'glossary');

            return null;
        }

        return new TwigEnvironment(new ArrayLoader(['tooltip' => $templateString]), [
            'cache' => false,
            'autoescape' => 'html',
        ]);
    }

    /**
     * @param TwigEnvironment|null $twig
     * @param array $variables
     *
     * @return string|null
     */
    private function renderTooltip(?TwigEnvironment $twig, array $variables): ?string
    {
        if ($twig === null) {
            return null;
        }

        try {
            return $twig->render('tooltip', $variables);
        } catch (SyntaxError $e) {
            Craft::error($e->getMessage(), 'glossary');

            return null;
        }
    }
}
