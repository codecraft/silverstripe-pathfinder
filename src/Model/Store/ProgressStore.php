<?php

namespace CodeCraft\Pathfinder\Model\Store;

use CodeCraft\Pathfinder\Control\PathfinderRequestHandler;
use CodeCraft\Pathfinder\Model\Answer;
use CodeCraft\Pathfinder\Model\Pathfinder;
use CodeCraft\Pathfinder\Model\Question;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;

/**
 * A storage model for tracking a Pathfinder's previously answered questions
 */
class ProgressStore
{
    use Configurable;
    use Injectable;
    use Extensible;

    /**
     * @var string
     */
    private static $storage_name = 'PathfinderProgress';

    /**
     * @var bool
     */
    private static $validate_sequence = true;

    /**
     * @var array
     */
    protected $entries = [];

    /**
     * @var Pathfinder
     */
    protected $handler;

    /**
     * @param RequestHandler $handler
     *
     * @return void
     */
    public function initAfterRequestHandler($handler)
    {
        // Do any useful things to initialise with the handler
        $this->setHandler($handler);
    }

    /**
     * Add an item to the current progress
     *
     * @param ProgressEntry $entry
     *
     * @return ProgressStore $this
     */
    public function add(ProgressEntry $entry)
    {
        // Definitely don't eat our ->toArray() dog food here,
        // we want the store in its full form
        $entries = $this->get();

        // Add the entry
        $entries[] = $entry;

        // Update our local reference
        $this->entries = $entries;

        return $this;
    }

    /**
     * Get the current progress
     *
     * @return array|ProgressEntry[]
     */
    public function get()
    {
        return $this->entries;
    }

    /**
     * Set the current progress
     *
     * @param array $entries
     *
     * @return ProgressStore $this
     */
    public function set(array $entries)
    {
        $this->entries = $entries;

        return $this;
    }

    /**
     * Clear the current progress store
     *
     * @return ProgressStore
     */
    public function clear()
    {
        $this->set([]);

        return $this;
    }

    /**
     * @param int $pos
     * @return ProgressStore
     */
    public function clearAfterPos($pos)
    {
        if ($pos <= 0) {
            return $this->clear();
        }

        if ($this->count() < $pos) {
            return $this;
        }

        $this->set(array_slice($this->get(), 0, $pos));

        return $this;
    }

    /**
     * Add progress by question, answer and choices
     *
     * @param Question $question
     * @param Answer $answer
     * @param DataList|Choice[] $choices
     *
     * @return ProgressStore $this
     */
    public function addProgress(Question $question, Answer $answer, DataList $choices)
    {
        $entry = ProgressEntry::create([
            'QuestionID' => $question->ID,
            'AnswerID' => $answer->ID,
            'ChoiceIDs' => $choices->column()
        ]);

        $this->add($entry);

        return $this;
    }

    /**
     * @return Pathfinder
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @param PathfinderRequestHandler $handler
     * @return ProgressStore $this
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Invoked as part of {@see PathfinderRequestHandler::Form()}
     *
     * @return void
     */
    public function updateForm($form)
    {
        return $form;
    }

    /**
     * Modify the redirect url used to continue to the next step in the pathfinder
     *
     * @param string $url
     * @param Form $form
     * @return string
     */
    public function augmentURL($url, $form = null)
    {
        return $url;
    }

    /**
     * @return string
     */
    public function getStorageName()
    {
        return sprintf(
            '%s%s',
            $this->config()->get('storage_name'),
            $this->getHandler() ? $this->getHandler()->ID : ''
        );
    }

    /**
     * @param int $pos 0 = First item
     * @return ProgressEntry|null
     */
    public function getByPos($pos)
    {
        $progress = $this->get();
        $key = $pos - 1;

        if (!is_array($progress) || !count($progress) || !array_key_exists($key, $progress)) {
            return null;
        }

        $entry = $progress[$key];
        if (!$entry instanceof ProgressEntry) {
            throw new \Exception(sprintf(
                'Unexpected item in set of progress entries. Expected all entries to be a "%s"',
                ProgressEntry::class
            ));
        }

        return $entry;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $entries = [];

        foreach ($this->get() as $entry) {
            if (!$entry instanceof ProgressEntry) {
                throw new \Exception(sprintf(
                    'Unexpected item in set of progress entries. Expected all entries to be a "%s"',
                    ProgressEntry::class
                ));
            }

            $entries[] = $entry->toArray();
        }

        return $entries;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->get());
    }

    /**
     * @return ProgressEntry|null
     */
    public function last()
    {
        return $this->getByPos($this->count());
    }

    /**
     * A heuristic for whether this story can/should determine if a specific
     * question is the next in sequence
     *
     * @return bool
     */
    public function canValidateSequence()
    {
        return $this->config()->get('validate_sequence');
    }

    /**
     * Determines if a question is the next in sequence
     *
     * @return bool|null
     */
    public function isInSequence($question, $pos)
    {
        if ($pos == 1) {
            // The first question is always in sequence
            return true;
        }

        if ($pos > $this->count() + 1) {
            // Position is too far ahead
            return false;
        }

        $entry = $this->getByPos($pos - 1);
        $answer = Answer::get()->byID($entry ? $entry->AnswerID : 0);

        if (!$answer || !$answer->getNextQuestion()) {
            // No previous answer led to his question
            return false;
        }

        // Does the previous answer lead to the matching question?
        return $answer->getNextQuestion()->ID === $question->ID;
    }
}
