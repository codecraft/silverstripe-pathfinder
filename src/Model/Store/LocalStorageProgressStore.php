<?php

namespace CodeCraft\Pathfinder\Model\Store;

use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\HiddenField;
use SilverStripe\View\Requirements;

/**
 * Uses the user's local storage to store a Pathfinder user's progress
 *
 * This store uses an id and a timestamp to "sync" with the front end.
 * When the request provides an encoded copy, we pass it to the front-end
 * where it will be verified
 */
class LocalStorageProgressStore extends RequestVarProgressStore
{
    /**
     * Should include the `.js` in the filename
     *
     * @var string
     */
    private static $require_form_js = 'vendor/codecraft/silverstripe-pathfinder/client/dist/js/LocalStorageProgressStore.js';

    /**
     * @var string
     */
    protected $timestamp;

    /**
     * {@inheritDoc}
     */
    public function initAfterRequestHandler($handler)
    {
        parent::initAfterRequestHandler($handler);

        $formJs = $this->config()->get('require_form_js');

        if ($formJs) {
            Requirements::javascriptTemplate(
                $formJs,
                ['StorageName' => $this->getStorageName()],
                $this->getHandler() ? $this->getHandler()->ID : null
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateForm($form)
    {
        $form->Fields()->add(
            HiddenField::create($this->getProgressVarName(), null, $this->getEncodedStore())
        );

        return $form;
    }

    /**
     * @return string
     */
    public function getTimestamp()
    {
        if (!$this->timestamp) {
            $this->timestamp = time();
        }

        return $this->timestamp;
    }

    /**
     * @return string|void
     * @throws \Exception
     */
    public function getEncodedStore()
    {
        // Add some details to help the front-end
        $progress = [
            'timestamp' => $this->getTimestamp(),
            'store' => $this->toArray()
        ];

        return static::encode($progress);
    }

    /**
     * {@inheritDoc}
     */
    public function getDecodedStore()
    {
        $encoded = $this->getEncodedProgress();

        if (!$encoded) {
            return [];
        }

        $decoded = static::decode($encoded);

        if (!is_array($decoded) || !array_key_exists('store', $decoded)) {
            Injector::inst()->get(LoggerInterface::class)
                ->warning(sprintf('Unexpected value found in request for "%s::decode()"', self::class));
            return [];
        }

        if (array_key_exists('timestamp', $decoded)) {
            // Hydrate our timestamp based on the
            $this->timestamp = $decoded['timestamp'];
        }

        // We only need the store here
        return $decoded['store'];
    }
}
