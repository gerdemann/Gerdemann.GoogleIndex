<?php
namespace Gerdemann\GoogleIndex\Service;

use Google_Client;
use Neos\Flow\Annotations as Flow;

/**
 *
 * @Flow\Scope("singleton")
 */
class IndexingService extends \Google_Service_Indexing
{
    /**
     * @inheritdoc
     */
    public function __construct(Google_Client $client)
    {
        parent::__construct($client);
        $client->addScope(\Google_Service_Indexing::INDEXING);
    }

    /**
     * @return \Google_Service_Indexing_Resource_UrlNotifications
     */
    public function getUrlNotifications() {
        return $this->urlNotifications;
    }
}
