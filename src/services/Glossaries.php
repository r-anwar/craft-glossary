<?php

namespace codemonauts\glossary\services;

use codemonauts\glossary\elements\Glossary as GlossaryElement;
use yii\base\Component;

class Glossaries extends Component
{
    /**
     * Das Default-Glossar, fuer die Dauer des Requests gemerkt. Der Twig-Filter
     * fragt es einmal pro Textelement ab — auf einer laengeren Seite sind das
     * zehn bis zwanzig identische Queries.
     */
    private ?GlossaryElement $defaultGlossary = null;

    /**
     * Eigenes Flag statt eines Null-Checks: ohne Default-Glossar waere die
     * Abfrage sonst gerade im Fehlerfall bei jedem Aufruf erneut faellig.
     */
    private bool $defaultGlossaryLoaded = false;

    /**
     * Glossare je Handle, ebenfalls nur fuer die Dauer des Requests.
     *
     * @var array<string, GlossaryElement|null>
     */
    private array $glossariesByHandle = [];

    /**
     * Glossare je ID. Term::getFieldLayout() laeuft hier durch und wird beim
     * Rendern je Treffer aufgerufen.
     *
     * @var array<int, GlossaryElement|null>
     */
    private array $glossariesById = [];

    /**
     * Returns the default glossary.
     *
     * @return GlossaryElement|null
     */
    public function getDefaultGlossary(): ?GlossaryElement
    {
        if (!$this->defaultGlossaryLoaded) {
            $this->defaultGlossary = GlossaryElement::find()
                ->default(true)
                ->one();
            $this->defaultGlossaryLoaded = true;
        }

        return $this->defaultGlossary;
    }

    /**
     * Returns a glossary by its ID.
     *
     * @param int $glossaryId The ID of the glossary to return.
     *
     * @return GlossaryElement|null
     */
    public function getGlossaryById(int $glossaryId): ?GlossaryElement
    {
        if (!array_key_exists($glossaryId, $this->glossariesById)) {
            $this->glossariesById[$glossaryId] = GlossaryElement::find()
                ->id($glossaryId)
                ->one();
        }

        return $this->glossariesById[$glossaryId];
    }

    /**
     * Returns a glossary by its handle.
     *
     * @param string $handle The handle of the glossary to return.
     *
     * @return GlossaryElement|null
     */
    public function getGlossaryByHandle(string $handle): ?GlossaryElement
    {
        if (!array_key_exists($handle, $this->glossariesByHandle)) {
            $this->glossariesByHandle[$handle] = GlossaryElement::find()
                ->handle($handle)
                ->one();
        }

        return $this->glossariesByHandle[$handle];
    }

    /**
     * Returns all available glosseries.
     *
     * @return GlossaryElement[]
     */
    public function getAllGlossaries(): array
    {
        return GlossaryElement::find()
            ->orderBy('title asc')
            ->all();
    }
}
