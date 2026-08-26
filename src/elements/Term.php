<?php

namespace codemonauts\glossary\elements;

use codemonauts\glossary\elements\db\TermQuery;
use codemonauts\glossary\elements\Glossary as GlossaryElement;
use codemonauts\glossary\records\Term as TermRecord;
use codemonauts\glossary\Glossary as GlossaryPlugin;
use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use yii\base\Exception;

class Term extends Element
{
    /**
     * Eigene Attribute, die in `glossary_terms` liegen und deren Aenderung sich
     * auf die Ausgabe auswirkt. `enabled` fehlt bewusst: Statuswechsel erkennt
     * Craft von sich aus.
     */
    private const TRACKED_ATTRIBUTES = [
        'term',
        'synonyms',
        'glossaryId',
        'caseSensitive',
        'matchSubstring',
    ];

    /**
     * @var string The term to match.
     */
    public string $term = '';

    /**
     * @var string|null Synonyms to match.
     */
    public ?string $synonyms = null;

    /**
     * @var int|null The glossary ID the term is associated with.
     */
    public ?int $glossaryId = null;

    /**
     * @var bool Should the term and synonyms match case sensitive?
     */
    public bool $caseSensitive = false;

    /**
     * @var bool Match as substring?
     */
    public bool $matchSubstring = false;

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->term;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('glossary', 'Term');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return 'term';
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('glossary', 'Terms');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return 'terms';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function cpEditUrl(): string
    {
        return UrlHelper::cpUrl('glossary/term/' . $this->canonicalId);
    }

    /**
     * @inheritDoc
     * @return TermQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new TermQuery(static::class);
    }

    /**
     * @inheritDoc
     */
    public static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('glossary', 'All terms'),
                'criteria' => [],
            ],
            [
                'heading' => Craft::t('glossary', 'Glossaries'),
            ],
        ];

        $glossaries = GlossaryPlugin::$plugin->getGlossaries()->getAllGlossaries();
        foreach ($glossaries as $glossary) {
            $sources[] = [
                'key' => $glossary->handle,
                'label' => $glossary->title,
                'criteria' => [
                    'glossaryId' => $glossary->id,
                ],
            ];
        }

        return $sources;
    }

    /**
     * @inheritDoc
     */
    public static function defineTableAttributes(): array
    {
        return [
            'term' => Craft::t('glossary', 'Term'),
            'caseSensitive' => Craft::t('glossary', 'Case Sensitive'),
            'matchSubstring' => Craft::t('glossary', 'Match as substring'),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'term',
            'caseSensitive',
            'matchSubstring',
        ];
    }

    /**
     * @inheritDoc
     */
    public static function defineSortOptions(): array
    {
        return [
            'term' => Craft::t('glossary', 'Term'),
        ];
    }

    /**
     * @inheritDoc
     */
    public static function defineSearchableAttributes(): array
    {
        return [
            'term',
            'synonyms',
        ];
    }

    /**
     * @inheritDoc
     */
    public function tableAttributeHtml(string $attribute): string
    {
        if ($attribute === 'caseSensitive') {
            return $this->caseSensitive ? '<div data-icon="check" aria-label="' . Craft::t('app', 'Yes') . '""></div>' : '';
        }

        if ($attribute === 'matchSubstring') {
            return $this->matchSubstring ? '<div data-icon="check" aria-label="' . Craft::t('app', 'Yes') . '""></div>' : '';
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @inheritDoc
     */
    protected static function defineFieldLayouts(string $source): array
    {
        if ($source === '*') {
            $glossaries = GlossaryPlugin::getInstance()->getGlossaries()->getAllGlossaries();
        } else {
            $glossary = GlossaryPlugin::getInstance()->getGlossaries()->getGlossaryByHandle($source);
            $glossaries = [$glossary];
        }

        $fieldLayouts = [];
        foreach ($glossaries as $glossary) {
            $fieldLayouts[] = $glossary->getFieldLayout();
        }

        return $fieldLayouts;
    }

    /**
     * @inheritDoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        // Ueber den Service statt per findOne(): renderTerms() ruft je Treffer
        // getFieldValues() auf, und das laeuft hier durch. Der Service merkt
        // sich das Glossar fuer die Dauer des Requests, direkte Queries wuerden
        // pro Treffer und Textelement erneut abfragen.
        $glossaries = GlossaryPlugin::getInstance()->getGlossaries();

        $glossary = $this->glossaryId
            ? $glossaries->getGlossaryById($this->glossaryId)
            : $glossaries->getDefaultGlossary();

        if (!$glossary) {
            $glossary = Glossary::findOne();
        }

        return $glossary?->getFieldLayout();
    }

    /**
     * @inheritDoc
     */
    public function beforeSave(bool $isNew): bool
    {
        $this->markChangedAttributesAsDirty($isNew);

        return parent::beforeSave($isNew);
    }

    /**
     * Meldet Craft, welche Attribute sich tatsaechlich geaendert haben.
     *
     * Craft fuellt `_dirtyAttributes` ausschliesslich ueber `setDirtyAttributes()`.
     * Der Element-Editor setzt die Attribute jedoch per `setAttributes()`, also
     * ohne jede Markierung — `getDirtyAttributes()` liefert dadurch immer ein
     * leeres Array. Cache-Plugins wie Blitz halten deshalb jedes Speichern fuer
     * eine Nulländerung und invalidieren die Seiten nicht, auf denen der Term
     * ausgezeichnet ist. Custom Fields sind nicht betroffen, die laufen ueber
     * `setFieldValue()` und markieren sich selbst.
     *
     * Der Vergleich gehoert in beforeSave(): danach schreibt afterSave() den
     * Record, und `markAsClean()` raeumt erst nach EVENT_AFTER_SAVE_ELEMENT auf.
     *
     * @param bool $isNew
     *
     * @return void
     */
    private function markChangedAttributesAsDirty(bool $isNew): void
    {
        if ($isNew) {
            $this->setDirtyAttributes(self::TRACKED_ATTRIBUTES);

            return;
        }

        $record = TermRecord::findOne($this->id);

        if ($record === null) {
            // Kein Vergleichsstand vorhanden: lieber zu viel melden als zu wenig.
            $this->setDirtyAttributes(self::TRACKED_ATTRIBUTES);

            return;
        }

        $changed = [];

        foreach (self::TRACKED_ATTRIBUTES as $attribute) {
            if (self::normalize($attribute, $record->$attribute) !== self::normalize($attribute, $this->$attribute)) {
                $changed[] = $attribute;
            }
        }

        if ($changed !== []) {
            $this->setDirtyAttributes($changed);
        }
    }

    /**
     * Bringt Element- und Record-Wert auf denselben Typ.
     *
     * Ohne das vergleicht man Datenbank-Rueckgaben gegen typisierte Properties:
     * je nach Treiber kommt aus einer Boolean-Spalte `true`, `1` oder `'t'`
     * zurueck, aus einer Integer-Spalte auch mal ein String.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool|int|string
     */
    private static function normalize(string $attribute, mixed $value): bool|int|string
    {
        return match ($attribute) {
            'caseSensitive', 'matchSubstring' => is_string($value)
                ? !in_array($value, ['', '0', 'f', 'false'], true)
                : (bool)$value,
            'glossaryId' => (int)$value,
            default => (string)$value,
        };
    }

    /**
     * @inheritDoc
     */
    public function afterSave(bool $isNew): void
    {
        if (!$isNew) {
            $record = TermRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid term ID: ' . $this->id);
            }
        } else {
            $record = new TermRecord();
            $record->id = (int)$this->id;
        }

        $record->term = $this->term;
        $record->synonyms = $this->synonyms;
        $record->glossaryId = $this->glossaryId;
        $record->caseSensitive = $this->caseSensitive;
        $record->matchSubstring = $this->matchSubstring;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * @inheritDoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['term'], 'required', 'on' => self::SCENARIO_LIVE];
        $rules[] = [['term', 'synonyms'], 'trim'];
        $rules[] = [['glossaryId'], 'integer'];
        $rules[] = [['caseSensitive', 'matchSubstring'], 'boolean'];

        return $rules;
    }

    /**
     * @inerhitdoc
     */
    public function canView(User $user): bool
    {
        return $user->can('glossary:termEdit');
    }

    /**
     * @inerhitdoc
     */
    public function canSave(User $user): bool
    {
        return $user->can('glossary:termEdit');
    }

    /**
     * @inerhitdoc
     */
    public function canDelete(User $user): bool
    {
        return $user->can('glossary:termEdit');
    }

    /**
     * @inheritdoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return $user->can('glossary:termEdit');
    }

    /**
     * @inheritdoc
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $fields = [];

        $options = [];
        $glossaries = GlossaryElement::findAll();
        foreach ($glossaries as $glossary) {
            $options[] = [
                'label' => Craft::t('site', $glossary->title),
                'value' => $glossary->id,
            ];
        }

        if (!$static) {
            $view = Craft::$app->getView();
            $glossaryInputId = $view->namespaceInputId('glossary');
            $js = <<<EOD
(() => {
    const \$typeInput = $('#$glossaryInputId');
    const editor = \$typeInput.closest('form').data('elementEditor');
    if (editor) {
        editor.checkForm();
    }
})();
EOD;
            $view->registerJs($js);
        }

        $fields[] = Cp::selectFieldHtml([
            'label' => Craft::t('glossary', 'Glossary'),
            'id' => 'glossary',
            'name' => 'glossaryId',
            'value' => $this->glossaryId,
            'options' => $options,
            'disabled' => $static,
        ]);

        $fields[] = parent::metaFieldsHtml($static);

        return implode("\n", $fields);
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl("glossary/terms");
    }
}
