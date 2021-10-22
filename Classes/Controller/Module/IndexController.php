<?php
namespace Gerdemann\GoogleIndex\Controller\Module;

use Gerdemann\GoogleIndex\Service\IndexingService;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\CreateContentContextTrait;
use Neos\Neos\Controller\Module\AbstractModuleController;

/**
 * @Flow\Scope("singleton")
 */
class IndexController extends AbstractModuleController
{
    use CreateContentContextTrait;

    const UPDATE = 'URL_UPDATED';
    const DELETE = 'URL_UPDATED';

    /**
     * @var FusionView
     */
    protected $view;

    /**
     * @var string
     */
    protected $defaultViewObjectName = FusionView::class;

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'html' => FusionView::class,
    ];

    /**
     * @Flow\Inject
     * @var IndexingService
     */
    protected $indexingService;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * Sets the Fusion path pattern on the view to avoid conflicts with the frontend fusion
     *
     * This is not needed if your package does not register itself to `Neos.Neos.fusion.autoInclude.*`
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
        $view->setFusionPathPattern('resource://Gerdemann.GoogleIndex/Private/Fusion');
    }

    /**
     * Index action
     *
     * @param int $page
     * @param int $limit
     */
    public function indexAction(int $page = 1, int $limit = 20)
    {
        $offset = ($page - 1) * $limit;
        $context = $this->createContentContext('live');
        $pages = (new FlowQuery([$context->getCurrentSiteNode()]))->find('[instanceof Neos.Neos:Document]')->sort('_lastPublicationDateTime', 'DESC')->slice($offset, $offset + $limit)->get();
        $pageCount = ceil((new FlowQuery([$context->getCurrentSiteNode()]))->find('[instanceof Neos.Neos:Document]')->count() / $limit);

        $paginationLinks = [];
        if ($pageCount > 1) {
            for ($i = 1; $i <= $pageCount; $i++) {
                if ($pageCount > 10) {
                    if ($i > 2 && $i < ($pageCount - 1) && $i != $page && $i != ($page - 1) && $i != ($page + 1) && $i != ($page - 2) && $i != ($page + 2)) {
                        continue;
                    }
                }
                $paginationLinks[] = [
                    'uri' => $this->uriBuilder->reset()->uriFor('index', ['page' => $i]),
                    'page' => $i,
                    'current' => $page === $i,
                ];
            }
        }

        $this->view->assignMultiple([
            'documentNode' => $context->getCurrentSiteNode(),
            'pages' => $pages,
            'paginationLinks' => $paginationLinks,
            'flashMessages' => $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush(),
        ]);
    }

    /**
     * @param string $url
     * @param string $title
     */
    public function updateGoogleAction(string $uri, string $title)
    {
        try {
            $this->notifyGoogle($uri, self::UPDATE);
            $message = $this->translateById('successfully.updated', [$title]);
            $this->addFlashMessage('', $message, Message::SEVERITY_OK);
        } catch (\Google_Service_Exception $error) {
            $error = json_decode($error->getMessage(), true);
            $message = $this->translateById('failed.updated', [$title]);
            $this->addFlashMessage('', $message . '<br />(' . (!empty($error['error']['message']) ? $error['error']['message'] : '') . ')', Message::SEVERITY_ERROR);
        }

        $this->redirect('index');
    }

    /**
     * @param string $url
     * @param string $title
     */
    public function deleteGoogleAction(string $uri, string $title)
    {
        try {
            $this->notifyGoogle($uri, self::DELETE);
            $message = $this->translateById('successfully.deleted', [$title]);
            $this->addFlashMessage('', $message, Message::SEVERITY_OK);
        } catch (\Google_Service_Exception $error) {
            $error = json_decode($error->getMessage(), true);
            $message = $this->translateById('failed.deleted', [$title]);
            $this->addFlashMessage('', $message . '<br />(' . (!empty($error['error']['message']) ? $error['error']['message'] : '') . ')', Message::SEVERITY_ERROR);
        }

        $this->redirect('index');
    }

    /**
     * @param string $uri
     * @param string $type
     */
    protected function notifyGoogle(string $uri, string $type = self::UPDATE)
    {
        $urlNotification = new \Google_Service_Indexing_UrlNotification();
        $today = new \DateTime();
        $urlNotification->setNotifyTime($today->format(\DateTime::RFC3339));
        $urlNotification->setUrl($uri);
        $urlNotification->setType($type);
        $this->indexingService->getUrlNotifications()->publish($urlNotification);
    }

    /**
     * @param string|null $id
     * @param array $arguments
     * @return string
     */
    protected function translateById(string $id, array $arguments = []): ?string
    {
        return $this->translator->translateById($id, $arguments, null, null, 'Main', 'Gerdemann.GoogleIndex');
    }
}
