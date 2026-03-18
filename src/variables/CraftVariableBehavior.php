<?php

namespace codemonauts\glossary\variables;

use codemonauts\glossary\elements\Term;
use codemonauts\glossary\elements\Glossary;
use codemonauts\glossary\elements\db\TermQuery;
use codemonauts\glossary\elements\db\GlossaryQuery;

use Craft;
use yii\base\Behavior;

class CraftVariableBehavior extends Behavior
{
    public function terms(array $criteria = []): TermQuery
    {
        // Create a query via your element type, and apply any passed criteria:
        return Craft::configure(Term::find(), $criteria);
    }
    public function glossaries(array $criteria = []): GlossaryQuery
    {
        // Create a query via your element type, and apply any passed criteria:
        return Craft::configure(Glossary::find(), $criteria);
    }
}